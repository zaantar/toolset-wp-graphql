<?php

namespace OTGS\Toolset\WpGraphQl;

use OTGS\Toolset\Common\PublicAPI\CustomFieldTypeDefinition;

class TypeRepository {

	/** @var GraphQlNamingService */
	private $naming;

	private $definedFieldTypes = [];

	private $fieldStructureProvider;

	public function __construct( GraphQlNamingService $naming, FieldStructureProvider $fieldStructureProvider ) {
		$this->naming = $naming;
		$this->fieldStructureProvider = $fieldStructureProvider;
	}


	public function obtainTypeForToolsetField( CustomFieldTypeDefinition $typeDefinition, $isRepeatable ) {
		$graphqlTypeName = $this->toolsetFieldTypeToGraphQlName( $typeDefinition, $isRepeatable );

		if ( ! array_key_exists( $graphqlTypeName, $this->definedFieldTypes ) ) {
			if( $isRepeatable ) {
				$singleTypeName = $this->obtainTypeForToolsetField( $typeDefinition, false );
				$fieldStructure = [
					'raw' => [
						'type' => [ 'list_of' => 'String' ],
						'description' => 'Raw field data.',
					],
					'repeatable' => [
						'type' => [ 'list_of' => $singleTypeName ],
						'description' => 'An array of single field values.'
					]
				];
				$this->registerFieldType( $graphqlTypeName, $typeDefinition->get_slug(), $fieldStructure );
			} else {
				$fieldStructure = $this->fieldStructureProvider->getStructureForFieldType( $typeDefinition->get_slug() );
				$this->registerFieldType( $graphqlTypeName, $typeDefinition->get_slug(), $fieldStructure );
			}

			$this->definedFieldTypes[ $graphqlTypeName ] = $fieldStructure;
		}

		return $graphqlTypeName;
	}


	public function getFieldStructure( $graphqlTypeName ) {
		return $this->definedFieldTypes[ $graphqlTypeName ] ?? null;
	}


	private function toolsetFieldTypeToGraphQlName( CustomFieldTypeDefinition $typeDefinition, $isRepeatable ) {
		return sprintf(
			'ToolsetField%s%s',
			$this->naming->makeGraphqlName( $typeDefinition->get_display_name(), GraphQlNamingService::CONTEXT_FIELD_TYPE_NAME ),
			$isRepeatable ? 'Repeatable' : ''
		);
	}


	private function registerFieldType( $name, $fieldTypeSlug, $fields ) {
		register_graphql_object_type(
			$name,
			[
				'description' => __( 'Toolset field type', 'toolset-wp-graphql' ) . ': ' . $fieldTypeSlug,
				'fields' => $fields,
			]
		);
	}

}
