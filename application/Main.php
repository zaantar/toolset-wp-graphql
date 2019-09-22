<?php
namespace OTGS\Toolset\WpGraphQl;

use OTGS\Toolset\Common\PublicAPI\CustomFieldDefinition;
use OTGS\Toolset\Common\PublicAPI\CustomFieldGroup;

/**
 * Main controller.
 *
 * Handle the initialization and integration between Toolset and WpGraphQL.
 */
class Main {


	/** @var GraphQlNamingService */
	private $naming;


	/** @var TypeRepository */
	private $typeRepository;


	/**
	 * Main constructor.
	 */
	public function __construct() {
		$this->naming = new GraphQlNamingService();
		$this->typeRepository = new TypeRepository( $this->naming, new FieldStructureProvider() );
	}


	/**
	 * Check if we can function at all.
	 *
	 * @return bool
	 */
	private function isEnvironmentReady() {
		return apply_filters( 'types_is_active', false ) && defined( 'WPGRAPHQL_VERSION' );
	}


	/**
	 * Let the magic happen.
	 */
	public function initialize() {

		// Show an admin notice if required plugins are not available.
		//
		//
		add_action( 'admin_notices', static function () {
			if ( ! apply_filters( 'types_is_active', false ) ) {
				printf(
					'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
					__( 'The Toolset WPGraphQL plugin cannot function properly if Toolset Types is not active.', 'toolset-wp-graphql' )
				);
			}

			if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
				printf(
					'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
					__( 'The Toolset WPGraphQL plugin cannot function properly if WPGrapQL is not active.', 'toolset-wp-graphql' )
				);
			}
		} );


		// Expose custom post types in WPGraphQL.
		//
		//
		add_filter( 'wpcf_type', function( $post_type_definition, $post_type_slug ) {
			$showInRest = (bool) $post_type_definition['show_in_rest'] ?? false;

			/**
			 * toolset_wpgraphql_show
			 *
			 * Determine whether a certain element type from Toolset should be exposed in WPGrapQL.
			 *
			 * @param bool $defaultValue By default, the behaviour is controlled by the show_in_rest option.
			 * @param string $slug Slug of the element type.
			 * @param string $context Constant determining what domain are we dealing with (posts, taxonomies, ...)
			 *
			 * @return bool True if the element type should be exposed.
			 */
			$showInGraphql = (bool) apply_filters( 'toolset_wpgraphql_show', $showInRest, $post_type_slug, GraphQlNamingService::CONTEXT_POST_TYPE );

			if( $showInGraphql ) {
				return $this->augmentDefinition( $post_type_definition, $post_type_slug, GraphQlNamingService::CONTEXT_POST_TYPE );
			}

			return $post_type_definition;
		}, 100, 2 ); // Priority 100 to allow for other adjustments.


		// Expose custom taxonomies in WPGraphQL.
		//
		//
		add_filter( 'wpcf_taxonomy_data', function( $taxonomy_definition, $taxonomy_slug ) {
			$show_in_rest = (bool) $taxonomy_definition['show_in_rest'] ?? false;
			// The filter is documented above.
			$show_in_graphql = (bool) apply_filters( 'toolset_wpgraphql_show', $show_in_rest, $taxonomy_slug, GraphQlNamingService::CONTEXT_TAXONOMY );

			if( $show_in_graphql ) {
				return $this->augmentDefinition( $taxonomy_definition, $taxonomy_slug, GraphQlNamingService::CONTEXT_TAXONOMY );
			}

			return $taxonomy_definition;
		}, 100, 2 ); // Priority 100 to allow for other adjustments.


		// Register custom fields after all the element types are available.
		//
		//
		add_action( 'init', function() {
			if ( ! $this->isEnvironmentReady() ) {
				return;
			}

			// Post types and taxonomies have already been registered at this point.
			$this->registerCustomFields();
		}, 12 ); // At init:11, Types is being initialized.
	}


	/**
	 * Register custom fields from Toolset.
	 *
	 * ATM, only post fields are supported.
	 */
	private function registerCustomFields() {
		$post_types = \WPGraphQL::get_allowed_post_types();
		foreach( $post_types as $postTypeSlug ) {
			$postTypeObject = get_post_type_object( $postTypeSlug );
			$postTypeGraphqlName = $postTypeObject->graphql_single_name;
			$this->registerCustomFieldsForPostType( $postTypeSlug, $postTypeGraphqlName );
		}
	}


	/**
	 * Register all Toolset custom fields for a given post type.
	 *
	 * @param string $postTypeSlug WordPress slug of the post type.
	 * @param string $postTypeGraphqlName GraphQL name of the post type.
	 */
	private function registerCustomFieldsForPostType( $postTypeSlug, $postTypeGraphqlName ) {

		// Get all field definitions from all field groups assigned to this post type.
		$fieldGroups = toolset_get_field_groups( [
			'domain' => 'posts',
			'is_active' => true,
			'assigned_to_post_type' => $postTypeSlug,
			'purpose' => '*'
		] );

		/** @var CustomFieldDefinition[] $fieldDefinitions */
		$fieldDefinitions = array_reduce( $fieldGroups, static function( $carry, CustomFieldGroup $item ) {
			foreach( $item->get_field_definitions() as $fieldDefinition ) {
				$carry[ $fieldDefinition->get_slug() ] = $fieldDefinition;
			}
			return $carry;
		}, [] );

		foreach( $fieldDefinitions as $fieldDefinition ) {

			// Construct an unique GraphQL name for the field.
			$fieldGraphqlName = $this->naming->makeGraphqlName(
				$fieldDefinition->get_name(), GraphQlNamingService::CONTEXT_FIELD_NAME
			);
			$this->naming->addToMap( // TODO make it so that this is no longer necessary
				$fieldDefinition->get_slug(), $fieldGraphqlName, GraphQlNamingService::CONTEXT_FIELD_NAME
			);

			// We also need a defined GraphQL type for this field.
			$fieldTypeGraphqlName = $this->typeRepository->obtainTypeForToolsetField( $fieldDefinition->get_type(), $fieldDefinition->is_repeatable() );

			$singleFieldStructure = $this->typeRepository->getFieldStructure(
				$this->typeRepository->obtainTypeForToolsetField( $fieldDefinition->get_type(), false )
			);

			register_graphql_field(
				$postTypeGraphqlName,
				$fieldGraphqlName,
				[
					'type' => $fieldTypeGraphqlName,
					'description' => __( 'Toolset field', 'toolset-wp-graphql' ) . ': ' . $fieldDefinition->get_slug(),
					'resolve' => function( $post ) use( $fieldDefinition, $singleFieldStructure ) {
						$fieldTranslation = new \FieldTranslation( $singleFieldStructure );
						return $fieldTranslation->translate( $fieldDefinition->instantiate( $post->ID ) );
					}
				]
			);
		}
	}


	/**
	 * Augment the array that defines a WordPress post type or taxonomy by adding required WPGraphQL properties.
	 *
	 * @param array $definition Definition array.
	 * @param string $originalSlug Slug of the element type.
	 * @param string $context Context constant determining the domain.
	 *
	 * @return array Augmented definition array.
	 */
	private function augmentDefinition( $definition, $originalSlug, $context ) {
		$singleName = $this->naming->makeGraphqlName(
			$definition['labels']['singular_name'] ?? $originalSlug, $context
		);

		$this->naming->addToMap( $originalSlug, $singleName, $context );

		return array_merge(
			$definition,
			[
				'show_in_graphql' => true,
				'graphql_single_name' => $singleName,
				'graphql_plural_name' => $this->naming->makeGraphqlName(
					$definition['labels']['name'] ?? $originalSlug, $context
				),
			]
		);
	}
}
