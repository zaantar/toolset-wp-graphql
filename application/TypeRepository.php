<?php

namespace OTGS\Toolset\WpGraphQl;

use OTGS\Toolset\Common\PublicAPI\CustomFieldTypeDefinition;

/**
 * Manages GraphQL types defined by this plugin.
 */
class TypeRepository {


	/** @var GraphQlNamingService */
	private $naming;


	/**
	 * @var array Indexed by GraphQL type names, each element contains an arrays with that type's fields
	 *     as expected by register_graphql_object_type().
	 */
	private $definedFieldTypes = [];


	/** @var FieldStructureProvider */
	private $fieldStructureProvider;


	/**
	 * TypeRepository constructor.
	 *
	 * @param GraphQlNamingService $naming
	 * @param FieldStructureProvider $fieldStructureProvider
	 */
	public function __construct( GraphQlNamingService $naming, FieldStructureProvider $fieldStructureProvider ) {
		$this->naming = $naming;
		$this->fieldStructureProvider = $fieldStructureProvider;
	}


	/**
	 * Retrieve a GraphQL type name for a given Toolset custom field definition and repeatability.
	 *
	 * If the type is not defined yet, build and define it.
	 *
	 * @param CustomFieldTypeDefinition $typeDefinition
	 * @param bool $isRepeatable Is it a type for repeatable fields?
	 *
	 * @return string GraphQL type name.
	 */
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
				$this->registerType( $graphqlTypeName, $typeDefinition->get_slug(), $fieldStructure );
			} else {
				$fieldStructure = $this->fieldStructureProvider->getStructureForFieldType( $typeDefinition->get_slug() );
				$this->registerType( $graphqlTypeName, $typeDefinition->get_slug(), $fieldStructure );
			}

			$this->definedFieldTypes[ $graphqlTypeName ] = $fieldStructure;
		}

		return $graphqlTypeName;
	}


	/**
	 * For an already existing GraphQL type, return its field structure.
	 *
	 * @param string $graphqlTypeName
	 * @return array|null Field structure as expected by register_graphql_object_type().
	 */
	public function getFieldStructure( $graphqlTypeName ) {
		return $this->definedFieldTypes[ $graphqlTypeName ] ?? null;
	}


	/**
	 * TODO move this to GraphQlNamingService.
	 *
	 * @param CustomFieldTypeDefinition $typeDefinition
	 * @param bool $isRepeatable
	 *
	 * @return string
	 */
	private function toolsetFieldTypeToGraphQlName( CustomFieldTypeDefinition $typeDefinition, $isRepeatable ) {
		return sprintf(
			'ToolsetField%s%s',
			$this->naming->makeGraphqlName( $typeDefinition->get_display_name(), GraphQlNamingService::CONTEXT_FIELD_TYPE_NAME ),
			$isRepeatable ? 'Repeatable' : ''
		);
	}


	/**
	 * Register a GraphQL type.
	 *
	 * @param string $graphQlTypeName
	 * @param string $fieldTypeSlug
	 * @param array $fields
	 */
	private function registerType( $graphQlTypeName, $fieldTypeSlug, $fields ) {
		register_graphql_object_type(
			$graphQlTypeName,
			[
				'description' => __( 'Toolset field type', 'toolset-wp-graphql' ) . ': ' . $fieldTypeSlug,
				'fields' => $fields,
			]
		);
	}

}
