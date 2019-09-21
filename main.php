<?php

// PHP 7.1 is now possible here, yay!
namespace OTGS\Toolset\WpGraphQl;

const CONTEXT_POST_TYPE = 'post_type';
const CONTEXT_TAXONOMY = 'taxonomy';

add_action( 'admin_notices', static function () {
	if ( apply_filters( 'types_is_active', false ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
		__( 'The Toolset WPGraphQL plugin cannot function properly if Toolset Types is not active.', 'toolset-wp-graphql' )
	);
} );

add_filter( 'wpcf_type', static function( $post_type_definition, $post_type_slug ) {
	$show_in_rest = (bool) $post_type_definition['show_in_rest'] ?? false;
	$show_in_graphql = (bool) apply_filters( 'toolset_wpgraphql_show', $show_in_rest, $post_type_slug, CONTEXT_POST_TYPE );

	if( $show_in_graphql ) {
		return augment_definition( $post_type_definition, $post_type_slug, CONTEXT_POST_TYPE );
	}

	return $post_type_definition;
}, 100, 2 );


add_filter( 'wpcf_taxonomy_data', static function( $taxonomy_definition, $taxonomy_slug ) {
	$show_in_rest = (bool) $taxonomy_definition['show_in_rest'] ?? false;
	$show_in_graphql = (bool) apply_filters( 'toolset_wpgraphql_show', $show_in_rest, $taxonomy_slug, CONTEXT_TAXONOMY );

	if( $show_in_graphql ) {
		return augment_definition( $taxonomy_definition, $taxonomy_slug, CONTEXT_TAXONOMY );
	}

	return $taxonomy_definition;
}, 100, 2 );


function augment_definition( $definition, $fallback_slug, $context ) {
	return array_merge(
		$definition,
		[
			'show_in_graphql' => true,
			'graphql_single_name' => make_graphql_name( $definition['labels']['singular_name'] ?? $fallback_slug, $context ),
			'graphql_plural_name' => make_graphql_name( $definition['labels']['name'] ?? $fallback_slug, $context ),
		]
	);
}

/**
 * WPGraphQL requires "camel case string with no punctuation or spaces".
 *
 * @param string $original_name
 * @param null|string $context Context for the toolset_wpgraphql_name filter.
 *
 * @return string
 */
function make_graphql_name( $original_name, $context = null ) {
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
