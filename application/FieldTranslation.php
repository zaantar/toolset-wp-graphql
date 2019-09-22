<?php

use OTGS\Toolset\Common\PublicAPI\CustomFieldInstance;

/**
 * Translates a Toolset custom field instance into data for WPGraphQL.
 */
class FieldTranslation {


	/** @var array */
	private $singleFieldStructure;


	/**
	 * FieldTranslation constructor.
	 *
	 * @param array $singleFieldStructure WPGraphQL *singular* field structure for given field (the type must match).
	 */
	public function __construct( $singleFieldStructure ) {
		$this->singleFieldStructure = $singleFieldStructure;
	}


	/**
	 * Translate the custom field value.
	 *
	 * @param CustomFieldInstance $fieldInstance
	 * @return array
	 */
	public function translate( CustomFieldInstance $fieldInstance ) {
		// Let Toolset do most of the magic, actually.
		$restOutput = $fieldInstance->render( \OTGS\Toolset\Common\PublicAPI\CustomFieldRenderPurpose\REST );

		if ( $fieldInstance->get_definition()->is_repeatable() ) {
			return $this->translateRepeatableField( $restOutput );
		}

		return $this->translateSingularField( $restOutput );
	}


	/**
	 * Translate a singular field value to WPGraphQL.
	 *
	 * @param array $restOutput Output from the REST field renderer.
	 *
	 * @return array
	 */
	private function translateSingularField( $restOutput ) {
		$result = [];

		// For now, this is quite straightforward because all those values for all Toolset field types
		// are always scalar (string or int).
		foreach( array_keys( $this->singleFieldStructure ) as $output_key ) {
			$result[ $output_key ] = $restOutput[ $output_key ];
		}

		return $result;
	}


	/**
	 * Translate a repeatable field value to WPGraphQL.
	 *
	 * @param array $restOutput Output from the REST field renderer.
	 *
	 * @return array
	 */
	private function translateRepeatableField( $restOutput ) {
		// If the field in REST only contains the "raw" value, the "repeatable" element would be missing
		// for repeatable fields. Since we don't really want that in GraphQL, we emulate it in such case.
		$repeatableFieldData = (
			array_key_exists( 'repeatable', $restOutput )
				? $restOutput['repeatable']
				: array_map( function( $rawValue ) { return [ 'raw' => $rawValue ]; }, $restOutput['raw'] )
		);

		$result = [
			// This is always supposed to be an array of scalar (string) values.
			'raw' => $restOutput['raw'],

			// In GraphQL, this will be simply a list of singular fields.
			'repeatable' => array_map( function( $singleValueRestOutput ) {
				return $this->translateSingularField( $singleValueRestOutput );
			}, $repeatableFieldData )
		];

		return $result;
	}

}

