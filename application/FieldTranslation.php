<?php

use OTGS\Toolset\Common\PublicAPI\CustomFieldInstance;

class FieldTranslation {

	private $singleFieldStructure;

	public function __construct( $singleFieldStructure ) {
		$this->singleFieldStructure = $singleFieldStructure;
	}


	public function translate( CustomFieldInstance $fieldInstance ) {
		$restOutput = $fieldInstance->render( \OTGS\Toolset\Common\PublicAPI\CustomFieldRenderPurpose\REST );

		if ( $fieldInstance->get_definition()->is_repeatable() ) {
			return $this->translateRepeatableField( $restOutput );
		}

		return $this->translateSingleField( $restOutput );
	}


	private function translateSingleField( $restOutput ) {
		$result = [];

		foreach( array_keys( $this->singleFieldStructure ) as $output_key ) {
			$result[ $output_key ] = $restOutput[ $output_key ];
		}

		return $result;
	}


	private function translateRepeatableField( $restOutput ) {
		$repeatableFieldData = (
			array_key_exists( 'repeatable', $restOutput )
				? $restOutput['repeatable']
				: array_map( function( $rawValue ) { return [ 'raw' => $rawValue ]; }, $restOutput['raw'] )
		);

		$result = [
			'raw' => $restOutput['raw'],
			'repeatable' => array_map( function( $singleValueRestOutput ) {
				return $this->translateSingleField( $singleValueRestOutput );
			}, $repeatableFieldData )
		];

		return $result;
	}

}

