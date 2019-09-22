<?php

namespace OTGS\Toolset\WpGraphQl;

use OTGS\Toolset\Common\PublicAPI\CustomFieldTypeDefinition;

class TypeRepository {

	/** @var GraphQlNamingService */
	private $naming;

	private $definedFieldTypes = [];

	public function __construct( GraphQlNamingService $naming ) {
		$this->naming = $naming;
	}


	public function obtainTypeForToolsetField( CustomFieldTypeDefinition $typeDefinition, $isRepeatable ) {
		$graphqlTypeName = $this->toolsetFieldTypeToGraphQlName( $typeDefinition, $isRepeatable );

		if ( ! in_array( $graphqlTypeName, $this->definedFieldTypes, true ) ) {
			if( $isRepeatable ) {
				$singleTypeName = $this->obtainTypeForToolsetField( $typeDefinition, false );
				$this->registerFieldType(
					$graphqlTypeName,
					$typeDefinition->get_slug(),
					[
						'raw' => [
							'type' => 'String',
							'description' => 'JSON-encoded raw field data.',
						],
						'repeatable' => [
							'type' => [ 'list_of' => $singleTypeName ],
							'description' => 'An array of single field values.'
						]
					]
				);
			} else {
				$this->registerFieldType(
					$graphqlTypeName,
					$typeDefinition->get_slug(),
					$this->getTypeDefinitionForSingularField( $typeDefinition )
				);
			}

			$this->definedFieldTypes[] = $graphqlTypeName;
		}

		return $graphqlTypeName;
	}


	private function toolsetFieldTypeToGraphQlName( CustomFieldTypeDefinition $typeDefinition, $isRepeatable ) {
		return sprintf(
			'ToolsetField%s%s',
			$this->naming->makeGraphqlName( $typeDefinition->get_display_name(), CONTEXT_FIELD_TYPE_NAME ),
			$isRepeatable ? 'Repeatable' : ''
		);
	}


	private function getTypeDefinitionForSingularField( CustomFieldTypeDefinition $typeDefinition ) {
		return [
			'restValue' => [
				'type' => 'String',
				'description' => __( 'JSON-encoded string as exposed in the REST API.', 'toolset-wp-graphql' )
			],
			'repeatedValueTest' => [
				'type' => [ 'list_of' => 'String' ],
				'description' => 'aaa'
			]
		];
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
