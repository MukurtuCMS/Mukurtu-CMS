<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\config_pages\Plugin\Field\FieldFormatter\ConfigPagesReferenceFieldFormatter;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesReferenceFieldFormatter.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\Plugin\Field\FieldFormatter\ConfigPagesReferenceFieldFormatter
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesReferenceFieldFormatterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'config_pages',
  ];

  /**
   * The formatter plugin manager.
   *
   * @var \Drupal\Core\Field\FormatterPluginManager
   */
  protected $formatterManager;

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

    $this->formatterManager = $this->container->get('plugin.manager.field.formatter');

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_formatter_type',
      'label' => 'Test Formatter Type',
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

    // Create a config page entity.
    $this->configPage = ConfigPages::create([
      'type' => 'test_formatter_type',
      'label' => 'Test Config Page',
      'context' => serialize([]),
    ]);
    $this->configPage->save();
  }

  /**
   * Tests that the formatter plugin is discovered.
   */
  public function testFormatterPluginDiscovery(): void {
    $definitions = $this->formatterManager->getDefinitions();

    $this->assertArrayHasKey('cp_entity_reference_label', $definitions);
  }

  /**
   * Tests formatter plugin definition.
   */
  public function testFormatterPluginDefinition(): void {
    $definition = $this->formatterManager->getDefinition('cp_entity_reference_label');

    $this->assertIsArray($definition);
    $this->assertEquals('cp_entity_reference_label', $definition['id']);
    $this->assertContains('entity_reference', $definition['field_types']);
  }

  /**
   * Tests isApplicable with config_pages_type target.
   *
   * @covers ::isApplicable
   */
  public function testIsApplicableWithConfigPagesTypeTarget(): void {
    $fieldDefinition = BaseFieldDefinition::create('entity_reference')
      ->setName('test_field')
      ->setSetting('target_type', 'config_pages_type');

    $result = ConfigPagesReferenceFieldFormatter::isApplicable($fieldDefinition);

    $this->assertTrue($result);
  }

  /**
   * Tests isApplicable with non-config_pages_type target.
   *
   * @covers ::isApplicable
   */
  public function testIsApplicableWithOtherTarget(): void {
    $fieldDefinition = BaseFieldDefinition::create('entity_reference')
      ->setName('test_field')
      ->setSetting('target_type', 'node');

    $result = ConfigPagesReferenceFieldFormatter::isApplicable($fieldDefinition);

    $this->assertFalse($result);
  }

  /**
   * Tests isApplicable with user target type.
   *
   * @covers ::isApplicable
   */
  public function testIsApplicableWithUserTarget(): void {
    $fieldDefinition = BaseFieldDefinition::create('entity_reference')
      ->setName('test_field')
      ->setSetting('target_type', 'user');

    $result = ConfigPagesReferenceFieldFormatter::isApplicable($fieldDefinition);

    $this->assertFalse($result);
  }

  /**
   * Tests formatter can be instantiated.
   */
  public function testFormatterCanBeInstantiated(): void {
    $fieldDefinition = BaseFieldDefinition::create('entity_reference')
      ->setName('test_field')
      ->setSetting('target_type', 'config_pages_type');

    $formatter = $this->formatterManager->createInstance('cp_entity_reference_label', [
      'field_definition' => $fieldDefinition,
      'settings' => [],
      'label' => 'Test',
      'view_mode' => 'default',
      'third_party_settings' => [],
    ]);

    $this->assertInstanceOf(ConfigPagesReferenceFieldFormatter::class, $formatter);
  }

  /**
   * Tests formatter settings summary.
   */
  public function testFormatterSettingsSummary(): void {
    $fieldDefinition = BaseFieldDefinition::create('entity_reference')
      ->setName('test_field')
      ->setSetting('target_type', 'config_pages_type');

    $formatter = $this->formatterManager->createInstance('cp_entity_reference_label', [
      'field_definition' => $fieldDefinition,
      'settings' => [],
      'label' => 'Test',
      'view_mode' => 'default',
      'third_party_settings' => [],
    ]);

    $summary = $formatter->settingsSummary();

    $this->assertIsArray($summary);
  }

  /**
   * Tests formatter default settings.
   */
  public function testFormatterDefaultSettings(): void {
    $settings = ConfigPagesReferenceFieldFormatter::defaultSettings();

    $this->assertIsArray($settings);
  }

}
