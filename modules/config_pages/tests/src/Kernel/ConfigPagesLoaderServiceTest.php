<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\ConfigPagesLoaderServiceInterface;
use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesLoaderService.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\ConfigPagesLoaderService
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesLoaderServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'config_pages',
  ];

  /**
   * The config_pages loader service.
   *
   * @var \Drupal\config_pages\ConfigPagesLoaderServiceInterface
   */
  protected ConfigPagesLoaderServiceInterface $loaderService;

  /**
   * The config page type.
   *
   * @var \Drupal\config_pages\Entity\ConfigPagesType
   */
  protected ConfigPagesType $configPageType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system']);

    $this->loaderService = \Drupal::service('config_pages.loader');

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_loader',
      'label' => 'Test Loader Config Page',
      'context' => [
        'show_warning' => '',
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $this->configPageType->save();

    // Create a text field storage.
    $fieldStorage = FieldStorageConfig::create([
      'entity_type' => 'config_pages',
      'field_name' => 'field_test',
      'type' => 'text',
      'cardinality' => -1,
    ]);
    $fieldStorage->save();

    // Create field instance.
    $field = FieldConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'config_pages',
      'bundle' => 'test_loader',
      'label' => 'Test Field',
      'required' => FALSE,
    ]);
    $field->save();
  }

  /**
   * Tests the load() method with a valid type.
   *
   * @covers ::load
   */
  public function testLoadWithValidType(): void {
    // Create a config page.
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Load the config page by type.
    $loaded = $this->loaderService->load('test_loader');

    $this->assertNotNull($loaded);
    $this->assertInstanceOf(ConfigPages::class, $loaded);
    $this->assertEquals($configPage->id(), $loaded->id());
  }

  /**
   * Tests the load() method with an invalid type.
   *
   * @covers ::load
   */
  public function testLoadWithInvalidType(): void {
    $loaded = $this->loaderService->load('nonexistent_type');

    $this->assertNull($loaded);
  }

  /**
   * Tests the load() method with an empty type.
   *
   * @covers ::load
   */
  public function testLoadWithEmptyType(): void {
    $loaded = $this->loaderService->load('');

    $this->assertNull($loaded);
  }

  /**
   * Tests getValue() with a valid field and single value.
   *
   * @covers ::getValue
   */
  public function testGetValueSingleValue(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'field_test' => ['value' => 'Test value 1'],
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Get all values without delta or key.
    $values = $this->loaderService->getValue('test_loader', 'field_test');
    $this->assertIsArray($values);
    $this->assertCount(1, $values);
    $this->assertEquals('Test value 1', $values[0]['value']);
  }

  /**
   * Tests getValue() with multiple values.
   *
   * @covers ::getValue
   */
  public function testGetValueMultipleValues(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'field_test' => [
        ['value' => 'Value 0'],
        ['value' => 'Value 1'],
        ['value' => 'Value 2'],
      ],
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Get all values.
    $values = $this->loaderService->getValue('test_loader', 'field_test');
    $this->assertCount(3, $values);
    $this->assertEquals('Value 0', $values[0]['value']);
    $this->assertEquals('Value 1', $values[1]['value']);
    $this->assertEquals('Value 2', $values[2]['value']);
  }

  /**
   * Tests getValue() with specific delta as integer.
   *
   * @covers ::getValue
   */
  public function testGetValueWithSingleDelta(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'field_test' => [
        ['value' => 'Value 0'],
        ['value' => 'Value 1'],
        ['value' => 'Value 2'],
      ],
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Get specific delta (integer).
    $value = $this->loaderService->getValue('test_loader', 'field_test', 1);
    $this->assertIsArray($value);
    $this->assertEquals('Value 1', $value['value']);
  }

  /**
   * Tests getValue() with specific deltas as array.
   *
   * @covers ::getValue
   */
  public function testGetValueWithMultipleDeltas(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'field_test' => [
        ['value' => 'Value 0'],
        ['value' => 'Value 1'],
        ['value' => 'Value 2'],
      ],
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Get specific deltas (array).
    $values = $this->loaderService->getValue('test_loader', 'field_test', [0, 2]);
    $this->assertIsArray($values);
    $this->assertArrayHasKey(0, $values);
    $this->assertArrayHasKey(2, $values);
    $this->assertArrayNotHasKey(1, $values);
    $this->assertEquals('Value 0', $values[0]['value']);
    $this->assertEquals('Value 2', $values[2]['value']);
  }

  /**
   * Tests getValue() with key extraction.
   *
   * @covers ::getValue
   */
  public function testGetValueWithKeyExtraction(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'field_test' => [
        ['value' => 'Value 0'],
        ['value' => 'Value 1'],
      ],
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Get all values with key extraction.
    $values = $this->loaderService->getValue('test_loader', 'field_test', [], 'value');
    $this->assertIsArray($values);
    $this->assertEquals('Value 0', $values[0]);
    $this->assertEquals('Value 1', $values[1]);
  }

  /**
   * Tests getValue() with single delta and key extraction.
   *
   * @covers ::getValue
   */
  public function testGetValueWithDeltaAndKey(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'field_test' => [
        ['value' => 'Value 0'],
        ['value' => 'Value 1'],
      ],
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Get specific delta with key extraction.
    $value = $this->loaderService->getValue('test_loader', 'field_test', 1, 'value');
    $this->assertEquals('Value 1', $value);
  }

  /**
   * Tests getValue() with non-existing field.
   *
   * @covers ::getValue
   */
  public function testGetValueNonExistingField(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Non-existing field with array deltas returns empty array.
    $values = $this->loaderService->getValue('test_loader', 'field_nonexistent');
    $this->assertIsArray($values);
    $this->assertEmpty($values);

    // Non-existing field with single delta returns null when key is specified.
    $value = $this->loaderService->getValue('test_loader', 'field_nonexistent', 0, 'value');
    $this->assertNull($value);

    // Non-existing field with single delta returns empty array when no key.
    $value = $this->loaderService->getValue('test_loader', 'field_nonexistent', 0);
    $this->assertIsArray($value);
    $this->assertEmpty($value);
  }

  /**
   * Tests getValue() with non-existing config page type.
   *
   * @covers ::getValue
   */
  public function testGetValueNonExistingType(): void {
    // Non-existing type returns empty array.
    $values = $this->loaderService->getValue('nonexistent_type', 'field_test');
    $this->assertIsArray($values);
    $this->assertEmpty($values);

    // Non-existing type with single delta and key returns null.
    $value = $this->loaderService->getValue('nonexistent_type', 'field_test', 0, 'value');
    $this->assertNull($value);
  }

  /**
   * Tests getValue() with ConfigPages object passed directly.
   *
   * @covers ::getValue
   */
  public function testGetValueWithConfigPagesObject(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'field_test' => ['value' => 'Direct object value'],
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Pass object directly instead of type string.
    $values = $this->loaderService->getValue($configPage, 'field_test');
    $this->assertIsArray($values);
    $this->assertEquals('Direct object value', $values[0]['value']);
  }

  /**
   * Tests getValue() with non-existing delta.
   *
   * @covers ::getValue
   */
  public function testGetValueNonExistingDelta(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'field_test' => ['value' => 'Only value'],
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Request delta that doesn't exist.
    $values = $this->loaderService->getValue('test_loader', 'field_test', [5]);
    $this->assertIsArray($values);
    $this->assertArrayHasKey(5, $values);
    $this->assertEmpty($values[5]);
  }

  /**
   * Tests getFieldView() with valid config page and field.
   *
   * @covers ::getFieldView
   */
  public function testGetFieldViewValid(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'field_test' => 'Test content',
      'context' => serialize([]),
    ]);
    $configPage->save();

    $result = $this->loaderService->getFieldView($configPage, 'field_test');

    $this->assertIsArray($result);
    $this->assertArrayHasKey('#cache', $result);
    $this->assertContains('config_pages:' . $configPage->id(), $result['#cache']['tags']);
  }

  /**
   * Tests getFieldView() with type string instead of object.
   *
   * @covers ::getFieldView
   */
  public function testGetFieldViewWithTypeString(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'field_test' => 'Test content',
      'context' => serialize([]),
    ]);
    $configPage->save();

    $result = $this->loaderService->getFieldView('test_loader', 'field_test');

    $this->assertIsArray($result);
    $this->assertArrayHasKey('#cache', $result);
    $this->assertContains('config_pages:' . $configPage->id(), $result['#cache']['tags']);
  }

  /**
   * Tests getFieldView() with non-existing field.
   *
   * @covers ::getFieldView
   */
  public function testGetFieldViewNonExistingField(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'context' => serialize([]),
    ]);
    $configPage->save();

    $result = $this->loaderService->getFieldView('test_loader', 'field_nonexistent');

    $this->assertIsArray($result);
    $this->assertArrayHasKey('#cache', $result);
    $this->assertContains('config_pages_list:test_loader', $result['#cache']['tags']);
  }

  /**
   * Tests getFieldView() with non-existing config page type.
   *
   * @covers ::getFieldView
   */
  public function testGetFieldViewNonExistingType(): void {
    $result = $this->loaderService->getFieldView('nonexistent_type', 'field_test');

    $this->assertIsArray($result);
    $this->assertArrayHasKey('#cache', $result);
    $this->assertContains('config_pages_list:nonexistent_type', $result['#cache']['tags']);
  }

  /**
   * Tests getFieldView() with custom view mode.
   *
   * @covers ::getFieldView
   */
  public function testGetFieldViewWithViewMode(): void {
    $configPage = ConfigPages::create([
      'type' => 'test_loader',
      'label' => 'Test Page',
      'field_test' => 'Test content',
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Default view mode is 'full'.
    $result = $this->loaderService->getFieldView($configPage, 'field_test', 'default');

    $this->assertIsArray($result);
    $this->assertArrayHasKey('#cache', $result);
  }

}
