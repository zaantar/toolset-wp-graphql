<?php

namespace OTGS\Toolset\WpGraphQl;

/**
 * Translates a Toolset field type slug into a field strucure array for WpGraphQL.
 *
 * Note that this is built on the current REST API implementation in Toolset and
 * should be fortified (probably by making Toolset itself handle the field rendering in the
 * required format). Currently, this whole plugin contains a lot of assumptions about the
 * data coming from Toolset.
 *
 * TODO get rid of hard dependencies on the Toolset codebase.
 */
class FieldStructureProvider {

	/**
	 * For a given field type slug, retrieve the array of GraphQL fields
	 * for a *singular* field.
	 *
	 * @param string $fieldTypeSlug
	 * @return array
	 */
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

		/**
		 * toolset_wpgraphql_field_structure_per_type
		 *
		 * Allow altering a WPGraphQL field structure for a given Toolset field type.
		 * This can be used for some troubleshooting in case of backward compatibility breakage.
		 */
		return apply_filters( 'toolset_wpgraphql_field_structure_per_type', $fieldStructure, $fieldTypeSlug );
	}
}
