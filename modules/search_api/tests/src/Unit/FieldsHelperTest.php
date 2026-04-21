<?php

namespace Drupal\Tests\search_api\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\search_api\Utility\DataTypeHelperInterface;
use Drupal\search_api\Utility\FieldsHelper;
use Drupal\search_api\Utility\ThemeSwitcherInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the fields helper utility class.
 *
 * @coversDefaultClass \Drupal\search_api\Utility\FieldsHelper
 *
 * @group search_api
 */
class FieldsHelperTest extends UnitTestCase {

  /**
   * The field object being tested.
   *
   * @var \Drupal\search_api\Utility\FieldsHelper
   */
  protected $fieldsHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_field_manager = $this->createMock(EntityFieldManagerInterface::class);
    $entity_type_info = $this->createMock(EntityTypeBundleInfoInterface::class);
    $data_type_helper = $this->createMock(DataTypeHelperInterface::class);
    $theme_switcher = $this->createMock(ThemeSwitcherInterface::class);
    $this->fieldsHelper = new FieldsHelper(
      $entity_type_manager,
      $entity_field_manager,
      $entity_type_info,
      $data_type_helper,
      $theme_switcher,
    );
  }

  /**
   * Tests extracting field values.
   *
   * @covers ::extractFieldValues
   */
  public function testExtractFieldValues() {
    $field_data = $this->createMock(ComplexDataInterface::class);

    $field_data_definition = $this->createMock(ComplexDataDefinitionInterface::class);
    $field_data_definition->expects($this->any())
      ->method('isList')
      ->willReturn(FALSE);

    $field_data_definition->expects($this->any())
      ->method('getMainPropertyName')
      ->willReturn('value');

    $field_data->expects($this->any())
      ->method('getDataDefinition')
      ->willReturn($field_data_definition);

    $value_definition = $this->createMock(DataDefinitionInterface::class);
    $value_definition->expects($this->any())
      ->method('isList')
      ->willReturn(FALSE);

    $value = $this->createMock(TypedDataInterface::class);
    $value->expects($this->any())
      ->method('getValue')
      ->willReturn('asd');

    $value->expects($this->any())
      ->method('getDataDefinition')
      ->willReturn($value_definition);

    // Mock variants for with and without computed data.
    $field_data->expects($this->any())
      ->method('getProperties')
      ->willReturnMap([
        [FALSE, []],
        [TRUE, ['value' => $value]],
      ]);

    $this->assertEquals(['asd'], $this->fieldsHelper->extractFieldValues($field_data));
  }

}
