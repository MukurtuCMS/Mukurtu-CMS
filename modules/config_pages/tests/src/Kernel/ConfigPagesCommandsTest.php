<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for ConfigPagesCommands Drush commands.
 *
 * Tests the underlying functionality that Drush commands use.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesCommandsTest extends KernelTestBase {

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

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_drush_type',
      'label' => 'Test Drush Type',
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
      'field_name' => 'field_drush_test',
      'entity_type' => 'config_pages',
      'type' => 'string',
      'cardinality' => 1,
    ]);
    $fieldStorage->save();

    $fieldConfig = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'test_drush_type',
      'label' => 'Drush Test Field',
    ]);
    $fieldConfig->save();
  }

  /**
   * Tests config_pages_config function exists and works.
   */
  public function testConfigPagesConfigFunction(): void {
    // Create a config page.
    $configPage = ConfigPages::create([
      'type' => 'test_drush_type',
      'label' => 'Test Drush Config Page',
      'context' => serialize([]),
      'field_drush_test' => 'initial_value',
    ]);
    $configPage->save();

    // Test config_pages_config function (used by Drush commands).
    $loaded = config_pages_config('test_drush_type');

    $this->assertNotNull($loaded);
    $this->assertEquals($configPage->id(), $loaded->id());
  }

  /**
   * Tests setting field value (simulates drush cpsfv).
   */
  public function testSetFieldValue(): void {
    // Create a config page.
    $configPage = ConfigPages::create([
      'type' => 'test_drush_type',
      'label' => 'Test Drush Config Page',
      'context' => serialize([]),
      'field_drush_test' => 'initial_value',
    ]);
    $configPage->save();

    // Simulate what Drush command does.
    $loaded = config_pages_config('test_drush_type');
    $loaded->set('field_drush_test', 'new_value');
    $loaded->save();

    // Verify.
    $reloaded = ConfigPages::load($configPage->id());
    $this->assertEquals('new_value', $reloaded->get('field_drush_test')->value);
  }

  /**
   * Tests getting field value (simulates drush cpgfv).
   */
  public function testGetFieldValue(): void {
    // Create a config page.
    $configPage = ConfigPages::create([
      'type' => 'test_drush_type',
      'label' => 'Test Drush Config Page',
      'context' => serialize([]),
      'field_drush_test' => 'test_value_for_get',
    ]);
    $configPage->save();

    // Simulate what Drush command does.
    $loaded = config_pages_config('test_drush_type');
    $value = $loaded->get('field_drush_test')->value;

    $this->assertEquals('test_value_for_get', $value);
  }

  /**
   * Tests creating config page when it does not exist.
   */
  public function testCreateConfigPageWhenNotExists(): void {
    // Verify no config page exists yet.
    $loaded = config_pages_config('test_drush_type');
    $this->assertNull($loaded);

    // Simulate what Drush command does when config page doesn't exist.
    $type = ConfigPagesType::load('test_drush_type');
    $configPage = ConfigPages::create([
      'type' => 'test_drush_type',
      'label' => $type->label(),
      'context' => $type->getContextData(),
    ]);
    $configPage->save();

    // Verify it was created.
    $loaded = config_pages_config('test_drush_type');
    $this->assertNotNull($loaded);
    $this->assertEquals('Test Drush Type', $loaded->label());
  }

  /**
   * Tests append functionality.
   */
  public function testAppendValue(): void {
    // Create a config page.
    $configPage = ConfigPages::create([
      'type' => 'test_drush_type',
      'label' => 'Test Drush Config Page',
      'context' => serialize([]),
      'field_drush_test' => 'initial',
    ]);
    $configPage->save();

    // Simulate append functionality.
    $loaded = config_pages_config('test_drush_type');
    $currentValue = $loaded->get('field_drush_test')->getString();
    $newValue = $currentValue . '_appended';
    $loaded->set('field_drush_test', $newValue);
    $loaded->save();

    // Verify.
    $reloaded = ConfigPages::load($configPage->id());
    $this->assertEquals('initial_appended', $reloaded->get('field_drush_test')->value);
  }

  /**
   * Tests newline replacement in values.
   */
  public function testNewlineReplacement(): void {
    // Create a config page.
    $configPage = ConfigPages::create([
      'type' => 'test_drush_type',
      'label' => 'Test Drush Config Page',
      'context' => serialize([]),
    ]);
    $configPage->save();

    // Simulate newline replacement (as done in Drush command).
    $value = 'line1\nline2';
    $processedValue = str_replace('\n', PHP_EOL, $value);

    $loaded = config_pages_config('test_drush_type');
    $loaded->set('field_drush_test', $processedValue);
    $loaded->save();

    // Verify newlines are replaced.
    $reloaded = ConfigPages::load($configPage->id());
    $this->assertStringContainsString(PHP_EOL, $reloaded->get('field_drush_test')->value);
  }

  /**
   * Tests getting value from non-existing config page.
   */
  public function testGetValueFromNonExistingConfigPage(): void {
    $loaded = config_pages_config('test_drush_type');

    $this->assertNull($loaded);
  }

  /**
   * Tests with non-existing type.
   */
  public function testWithNonExistingType(): void {
    $loaded = config_pages_config('non_existing_type');

    $this->assertNull($loaded);
  }

}
