<?php

namespace OTGS\Toolset\WpGraphQl;

class GraphQlNamingService {

	const CONTEXT_POST_TYPE = 'post_type';
	const CONTEXT_TAXONOMY = 'taxonomy';
	const CONTEXT_FIELD_NAME = 'custom_field';
	const CONTEXT_FIELD_TYPE_NAME = 'custom_field_type';

	private $nameMap = [];

	public function addToMap( $originalSlug, $graphqlName, $context ) {
		if( ! array_key_exists( $context, $this->nameMap ) ) {
			$this->nameMap[ $context ] = [];
		}

		$this->nameMap[ $context ][ $originalSlug ] = $graphqlName;
	}


	/**
	 * WPGraphQL requires "camel case string with no punctuation or spaces".
	 *
	 * @param string $original_name
	 * @param string $context Context for the toolset_wpgraphql_name filter.
	 *
	 * @return string
	 */
	public function makeGraphqlName( $original_name, $context ) {
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
