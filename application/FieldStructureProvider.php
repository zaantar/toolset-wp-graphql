<?php

namespace OTGS\Toolset\WpGraphQl;

use OTGS\Toolset\Common\PublicAPI\CustomFieldInstance;

class FieldStructureProvider {

	public function getStructureForFieldType( $fieldTypeSlug ) {
		$fieldStructure = [
			'raw' => [
				'type' => 'String',
				'description' => 'Raw field value.'
			]
		];

		switch( $fieldTypeSlug ) {
			case \Toolset_Field_Type_Definition_Factory::AUDIO:
			case \Toolset_Field_Type_Definition_Factory::IMAGE:
			case \Toolset_Field_Type_Definition_Factory::VIDEO:
			case \Toolset_Field_Type_Definition_Factory::FILE:
				$fieldStructure['attachment_id'] = [
					'type' => 'Integer',
					'description' => 'ID of the attachment if one exists.',
				];
				break;
			case \Toolset_Field_Type_Definition_Factory::CHECKBOXES:
				$fieldStructure['checked'] = [
					'type' => [ 'list_of' => 'String' ],
					'description' => 'Values of checked options.',
				];
				break;
			case \Toolset_Field_Type_Definition_Factory::DATE:
				$fieldStructure['formatted'] = [
					'type' => 'String',
					'description' => 'Formatted date (and time) according to site settings.',
				];
				break;
			case \Toolset_Field_Type_Definition_Factory::SKYPE:
				$fieldStructure['skypename'] = [
					'type' => 'String',
					'description' => 'Skype name even if the raw field value contains a more complex legacy data structure.',
				];
				break;
		}

		return apply_filters( 'toolset_wpgraphql_field_structure_per_type', $fieldStructure, $fieldTypeSlug );
	}


	/*public function xxgetStructureForFieldType( $fieldTypeSlug, $isRepeatable ) {
		$fieldTypeStructure = $this->getBaseStructureForFieldType( $fieldTypeSlug );

		if( ! $isRepeatable ) {
			return $fieldTypeStructure;
		}

		$fieldTypeStructure['raw']['type'] = [ 'list_of' => 'String' ];

		$additionalKeys = array_filter( array_keys( $fieldTypeStructure ), static function( $key ) { return $key !== 'raw' } );
		if( ! empty( $additionalKeys ) ) {
			$fieldTypeStructure['repeatable'] =
		}
	}*/
}
