<?php

// PHP 7.1 is now possible here, yay!
namespace OTGS\Toolset\WpGraphQl;

use OTGS\Toolset\Common\PublicAPI\CustomFieldDefinition;
use OTGS\Toolset\Common\PublicAPI\CustomFieldGroup;

const CONTEXT_POST_TYPE = 'post_type';
const CONTEXT_TAXONOMY = 'taxonomy';
const CONTEXT_FIELD_NAME = 'custom_field';
const CONTEXT_FIELD_TYPE_NAME = 'custom_field_type';

class Main {

	private $nameMap = [];

	/** @var GraphQlNamingService */
	private $naming;

	/** @var TypeRepository */
	private $typeRepository;

	public function __construct() {
		$this->naming = new GraphQlNamingService();
		$this->typeRepository = new TypeRepository( $this->naming, new FieldStructureProvider() );
	}

	public function initialize() {

		add_action( 'admin_notices', static function () {
			if ( apply_filters( 'types_is_active', false ) ) {
				return;
			}

			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				__( 'The Toolset WPGraphQL plugin cannot function properly if Toolset Types is not active.', 'toolset-wp-graphql' )
			);
		} );

		add_filter( 'wpcf_type', function( $post_type_definition, $post_type_slug ) {
			$showInRest = (bool) $post_type_definition['show_in_rest'] ?? false;
			$showInGraphql = (bool) apply_filters( 'toolset_wpgraphql_show', $showInRest, $post_type_slug, CONTEXT_POST_TYPE );

			if( $showInGraphql ) {
				return $this->augmentDefinition( $post_type_definition, $post_type_slug, CONTEXT_POST_TYPE );
			}

			return $post_type_definition;
		}, 100, 2 ); // Priority 100 to allow for other adjustments.


		add_filter( 'wpcf_taxonomy_data', function( $taxonomy_definition, $taxonomy_slug ) {
			$show_in_rest = (bool) $taxonomy_definition['show_in_rest'] ?? false;
			$show_in_graphql = (bool) apply_filters( 'toolset_wpgraphql_show', $show_in_rest, $taxonomy_slug, CONTEXT_TAXONOMY );

			if( $show_in_graphql ) {
				return $this->augmentDefinition( $taxonomy_definition, $taxonomy_slug, CONTEXT_TAXONOMY );
			}

			return $taxonomy_definition;
		}, 100, 2 ); // Priority 100 to allow for other adjustments.

		add_action( 'init', function() {
			// Post types and taxonomies have already been registered at this point.
			$this->registerCustomFields();
		}, 12 ); // At init:11, Types is being initialized.
	}


	private function registerCustomFields() {
		if( ! array_key_exists( CONTEXT_POST_TYPE, $this->nameMap ) ) {
			return;
		}
		$post_types = \WPGraphQL::get_allowed_post_types();
		foreach( $post_types as $postTypeSlug ) {
			$postTypeObject = get_post_type_object( $postTypeSlug );
			$postTypeGraphqlName = $postTypeObject->graphql_single_name;
			$this->registerCustomFieldsForPostType( $postTypeSlug, $postTypeGraphqlName );
		}
	}


	private function registerCustomFieldsForPostType( $postTypeSlug, $postTypeGraphqlName ) {
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
			$fieldGraphqlName = $this->naming->makeGraphqlName( $fieldDefinition->get_name(), CONTEXT_FIELD_NAME );
			$this->addToMap( $fieldDefinition->get_slug(), $fieldGraphqlName, CONTEXT_FIELD_NAME );
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




	private function addToMap( $originalSlug, $graphqlName, $context ) {
		if( ! array_key_exists( $context, $this->nameMap ) ) {
			$this->nameMap[ $context ] = [];
		}

		$this->nameMap[ $context ][ $originalSlug ] = $graphqlName;
	}


	private function augmentDefinition( $definition, $originalSlug, $context ) {
		$singleName = $this->naming->makeGraphqlName(
			$definition['labels']['singular_name'] ?? $originalSlug, $context
		);

		$this->addToMap( $originalSlug, $singleName, $context );

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
