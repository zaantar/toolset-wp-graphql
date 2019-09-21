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


	private $fieldTypeMap = [];


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
			$fieldGraphqlName = $this->makeGraphqlName( $fieldDefinition->get_name(), CONTEXT_FIELD_NAME );
			$this->addToMap( $fieldDefinition->get_slug(), $fieldGraphqlName, CONTEXT_FIELD_NAME );
			register_graphql_field(
				$postTypeGraphqlName,
				$fieldGraphqlName,
				[
					'type' => $this->getTypeForField( $fieldDefinition ),
					'description' => __( 'Toolset field', 'toolset-wp-graphql' ) . ': ' . $fieldDefinition->get_slug(),
					'resolve' => function( $post ) use( $fieldDefinition ) {
						$value = $fieldDefinition->instantiate( $post->ID )->render( \OTGS\Toolset\Common\PublicAPI\CustomFieldRenderPurpose\REST );
						return [ 'restValue' => json_encode( $value ) ];
					}
				]
			);
		}
	}


	private function getTypeForField( CustomFieldDefinition $fieldDefinition ) {
		$typeSlug = $fieldDefinition->get_type_slug();
		$isRepeatableKey = ( $fieldDefinition->is_repeatable() ? 'repeatable' : 'single-value' );
		if( ! array_key_exists( $typeSlug, $this->fieldTypeMap ) ) {
			$this->fieldTypeMap[ $typeSlug ] = [];
		}

		if ( ! array_key_exists( $isRepeatableKey, $this->fieldTypeMap[ $typeSlug ] ) ) {
			$graphqlTypeName = sprintf(
				'ToolsetField%s%s',
				$this->makeGraphqlName( $fieldDefinition->get_type()->get_display_name(), CONTEXT_FIELD_TYPE_NAME ),
				$fieldDefinition->is_repeatable() ? 'Repeatable' : ''
			);

			register_graphql_object_type(
				$graphqlTypeName,
				[
					'description' => __( 'Toolset field type', 'toolset-wp-graphql' ) . ': ' . $typeSlug,
					'fields' => [
						'restValue' => [
							'type' => 'String',
							'description' => __( 'JSON-encoded string as exposed in the REST API.', 'toolset-wp-graphql' )
						]
					]
				]
			);

			$this->fieldTypeMap[ $typeSlug ][ $isRepeatableKey ] = $graphqlTypeName;
		}

		return $this->fieldTypeMap[ $typeSlug ][ $isRepeatableKey ];
	}


	private function addToMap( $originalSlug, $graphqlName, $context ) {
		if( ! array_key_exists( $context, $this->nameMap ) ) {
			$this->nameMap[ $context ] = [];
		}

		$this->nameMap[ $context ][ $originalSlug ] = $graphqlName;
	}


	private function augmentDefinition( $definition, $originalSlug, $context ) {
		$singleName = $this->makeGraphqlName(
			$definition['labels']['singular_name'] ?? $originalSlug, $context
		);

		$this->addToMap( $originalSlug, $singleName, $context );

		return array_merge(
			$definition,
			[
				'show_in_graphql' => true,
				'graphql_single_name' => $singleName,
				'graphql_plural_name' => $this->makeGraphqlName(
					$definition['labels']['name'] ?? $originalSlug, $context
				),
			]
		);
	}

	/**
	 * WPGraphQL requires "camel case string with no punctuation or spaces".
	 *
	 * @param string $original_name
	 * @param string $context Context for the toolset_wpgraphql_name filter.
	 *
	 * @return string
	 */
	private function makeGraphqlName( $original_name, $context ) {
		// Get rid of non-ascii characters in the best way we can.
		$no_accents = remove_accents( $original_name ); // THANK YOU, WORDPRESS
		$ascii = iconv( 'UTF-8', 'ASCII//TRANSLIT', $no_accents );

		// Get rid of everything that's not a letter or a whitespace.
		$only_letters = preg_replace('/[^a-zA-Z\s]/u', ' ', $ascii );

		$all_lowercase = strtolower( $only_letters );

		$split_by_words = array_filter(
			explode( ' ', $all_lowercase ),
			static function( $word ) { return ! empty( $word ); }
		);

		// Make first letters uppercase, except the first word
		$first_word = array_shift( $split_by_words );
		$uppercase = array_map( static function( $word ) {
			return ucfirst( $word );
		}, $split_by_words );

		// Glue everything into a CamelCase
		$candidate = $first_word . implode( $uppercase );

		// Allow to override the result in case it doesn't really work for the user.
		$result = apply_filters( 'toolset_wpgraphql_name', $candidate, $original_name, $context );
		if( ! is_string( $result ) ) {
			throw new \InvalidArgumentException( 'Wrong result of the toolset_wpgraphql_name filter.' );
		}
		return $result;
	}

}
