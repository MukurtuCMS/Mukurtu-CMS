<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\config_pages\Plugin\Condition\ConfigPagesValueAccess;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesValueAccess condition plugin.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\Plugin\Condition\ConfigPagesValueAccess
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesConditionPluginTest extends KernelTestBase {

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
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $conditionManager;

  /**
   * The config page type.
   *
   * @var \Drupal\config_pages\Entity\ConfigPagesType
   */
  protected ConfigPagesType $configPageType;

  /**
   * The config page entity.
   *
   * @var \Drupal\config_pages\Entity\ConfigPages
   */
  protected ConfigPages $configPage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('config_pages');
    $this->installEntitySchema('config_pages_type');
    $this->installConfig(['field', 'system']);

    $this->conditionManager = $this->container->get('plugin.manager.condition');

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_condition_type',
      'label' => 'Test Condition Type',
      'context' => [
        'show_warning' => FALSE,
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

    // Create a string field.
    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_test_string',
      'entity_type' => 'config_pages',
      'type' => 'string',
      'cardinality' => 1,
    ]);
    $fieldStorage->save();

    $fieldConfig = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'test_condition_type',
      'label' => 'Test String Field',
    ]);
    $fieldConfig->save();

    // Create a config page entity with field value.
    $this->configPage = ConfigPages::create([
      'type' => 'test_condition_type',
      'label' => 'Test Condition Config Page',
      'context' => serialize([]),
      'field_test_string' => 'test_value',
    ]);
    $this->configPage->save();
  }

  /**
   * Tests that the plugin is discovered.
   */
  public function testPluginDiscovery(): void {
    $definitions = $this->conditionManager->getDefinitions();

    $this->assertArrayHasKey('config_pages_values_access', $definitions);
  }

  /**
   * Tests plugin definition.
   */
  public function testPluginDefinition(): void {
    $definition = $this->conditionManager->getDefinition('config_pages_values_access');

    $this->assertIsArray($definition);
    $this->assertEquals('config_pages_values_access', $definition['id']);
  }

  /**
   * Tests plugin can be instantiated.
   */
  public function testPluginCanBeInstantiated(): void {
    $plugin = $this->conditionManager->createInstance('config_pages_values_access');

    $this->assertInstanceOf(ConfigPagesValueAccess::class, $plugin);
  }

  /**
   * Tests getOperandOptions returns operators.
   *
   * @covers ::getOperandOptions
   */
  public function testGetOperandOptions(): void {
    $plugin = $this->conditionManager->createInstance('config_pages_values_access');

    $options = $plugin->getOperandOptions();

    $this->assertIsArray($options);
    $this->assertArrayHasKey('==', $options);
    $this->assertArrayHasKey('<', $options);
    $this->assertArrayHasKey('<=', $options);
    $this->assertArrayHasKey('!=', $options);
    $this->assertArrayHasKey('>=', $options);
    $this->assertArrayHasKey('>', $options);
    $this->assertArrayHasKey('isset', $options);
  }

  /**
   * Tests getConfigPageFields returns fields.
   *
   * @covers ::getConfigPageFields
   */
  public function testGetConfigPageFields(): void {
    $plugin = $this->conditionManager->createInstance('config_pages_values_access');

    $fields = $plugin->getConfigPageFields('test_condition_type');

    $this->assertIsArray($fields);
    // Should include field_test_string (string type is allowed).
    $this->assertNotEmpty($fields);
  }

  /**
   * Tests getConfigPageFields with empty type.
   *
   * @covers ::getConfigPageFields
   */
  public function testGetConfigPageFieldsWithEmptyType(): void {
    $plugin = $this->conditionManager->createInstance('config_pages_values_access');

    $fields = $plugin->getConfigPageFields('');

    $this->assertIsArray($fields);
    $this->assertEmpty($fields);
  }

  /**
   * Tests evaluate returns TRUE when no configuration.
   *
   * @covers ::evaluate
   */
  public function testEvaluateWithoutConfiguration(): void {
    $plugin = $this->conditionManager->createInstance('config_pages_values_access');

    $result = $plugin->evaluate();

    $this->assertTrue($result);
  }

  /**
   * Tests evaluate with equals operator.
   *
   * @covers ::evaluate
   */
  public function testEvaluateWithEqualsOperator(): void {
    $plugin = $this->conditionManager->createInstance('config_pages_values_access', [
      'config_page_field' => 'test_condition_type|field_test_string|string',
      'operator' => '==',
      'condition_value' => 'test_value',
    ]);

    $result = $plugin->evaluate();

    $this->assertTrue($result);
  }

  /**
   * Tests evaluate with not equals operator.
   *
   * @covers ::evaluate
   */
  public function testEvaluateWithNotEqualsOperator(): void {
    $plugin = $this->conditionManager->createInstance('config_pages_values_access', [
      'config_page_field' => 'test_condition_type|field_test_string|string',
      'operator' => '!=',
      'condition_value' => 'different_value',
    ]);

    $result = $plugin->evaluate();

    $this->assertTrue($result);
  }

  /**
   * Tests evaluate with empty config_page_field.
   *
   * @covers ::evaluate
   */
  public function testEvaluateWithEmptyField(): void {
    $plugin = $this->conditionManager->createInstance('config_pages_values_access', [
      'config_page_field' => '',
      'operator' => '==',
      'condition_value' => 'test',
    ]);

    $result = $plugin->evaluate();

    $this->assertTrue($result);
  }

  /**
   * Tests summary returns translated string.
   *
   * @covers ::summary
   */
  public function testSummary(): void {
    $plugin = $this->conditionManager->createInstance('config_pages_values_access', [
      'config_page_field' => 'test_condition_type|field_test_string|string',
      'operator' => '==',
      'condition_value' => 'test_value',
    ]);

    $summary = $plugin->summary();

    $this->assertIsObject($summary);
    $summaryString = (string) $summary;
    $this->assertStringContainsString('field_test_string', $summaryString);
  }

  /**
   * Tests compareValues with different operators.
   */
  public function testCompareValuesOperators(): void {
    $plugin = $this->conditionManager->createInstance('config_pages_values_access');

    // Use reflection to test protected method.
    $reflection = new \ReflectionClass($plugin);
    $method = $reflection->getMethod('compareValues');
    $method->setAccessible(TRUE);

    // Test equals.
    $this->assertTrue($method->invoke($plugin, 'test', 'test', '=='));
    $this->assertFalse($method->invoke($plugin, 'test', 'other', '=='));

    // Test not equals.
    $this->assertTrue($method->invoke($plugin, 'test', 'other', '!='));
    $this->assertFalse($method->invoke($plugin, 'test', 'test', '!='));

    // Test less than.
    $this->assertTrue($method->invoke($plugin, '10', '5', '<'));
    $this->assertFalse($method->invoke($plugin, '5', '10', '<'));

    // Test greater than.
    $this->assertTrue($method->invoke($plugin, '5', '10', '>'));
    $this->assertFalse($method->invoke($plugin, '10', '5', '>'));

    // Test less than or equal.
    $this->assertTrue($method->invoke($plugin, '10', '5', '<='));
    $this->assertTrue($method->invoke($plugin, '10', '10', '<='));

    // Test greater than or equal.
    $this->assertTrue($method->invoke($plugin, '5', '10', '>='));
    $this->assertTrue($method->invoke($plugin, '10', '10', '>='));

    // Test isset.
    $this->assertTrue($method->invoke($plugin, '1', 'value', 'isset'));
    $this->assertTrue($method->invoke($plugin, '', '', 'isset'));

    // Test default (unknown operator).
    $this->assertFalse($method->invoke($plugin, 'test', 'test', 'unknown'));
  }

}
