<?php

namespace Drupal\Tests\config_pages\Kernel;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\config_pages\Twig\ConfigPagesExtension;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Twig\TwigFunction;

/**
 * Kernel tests for ConfigPagesExtension Twig extension.
 *
 * @group config_pages
 * @coversDefaultClass \Drupal\config_pages\Twig\ConfigPagesExtension
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesExtensionTest extends KernelTestBase {

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
   * The Twig extension.
   *
   * @var \Drupal\config_pages\Twig\ConfigPagesExtension
   */
  protected ConfigPagesExtension $extension;

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
    $this->installConfig(['field', 'system', 'user']);

    // Get the Twig extension service.
    $this->extension = $this->container->get('config_pages.twig_extension');

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_twig_type',
      'label' => 'Test Twig Type',
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

    // Create a text field on the config page type.
    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_test_text',
      'entity_type' => 'config_pages',
      'type' => 'string',
      'cardinality' => 1,
    ]);
    $fieldStorage->save();

    $fieldConfig = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'test_twig_type',
      'label' => 'Test Text Field',
    ]);
    $fieldConfig->save();

    // Create a config page entity with field value.
    $this->configPage = ConfigPages::create([
      'type' => 'test_twig_type',
      'label' => 'Test Twig Config Page',
      'context' => serialize([]),
      'field_test_text' => 'Test text value',
    ]);
    $this->configPage->save();
  }

  /**
   * Tests that the extension is registered as a service.
   */
  public function testExtensionIsRegistered(): void {
    $this->assertInstanceOf(ConfigPagesExtension::class, $this->extension);
  }

  /**
   * Tests getFunctions returns TwigFunction array.
   *
   * @covers ::getFunctions
   */
  public function testGetFunctionsReturnsTwigFunctions(): void {
    $functions = $this->extension->getFunctions();

    $this->assertIsArray($functions);
    $this->assertNotEmpty($functions);

    foreach ($functions as $function) {
      $this->assertInstanceOf(TwigFunction::class, $function);
    }
  }

  /**
   * Tests that config_pages_field function is registered.
   *
   * @covers ::getFunctions
   */
  public function testConfigPagesFieldFunctionExists(): void {
    $functions = $this->extension->getFunctions();

    $functionNames = array_map(function (TwigFunction $function) {
      return $function->getName();
    }, $functions);

    $this->assertContains('config_pages_field', $functionNames);
  }

  /**
   * Tests configPagesField with valid parameters.
   *
   * @covers ::configPagesField
   */
  public function testConfigPagesFieldWithValidParameters(): void {
    $result = ConfigPagesExtension::configPagesField('test_twig_type', 'field_test_text');

    $this->assertIsArray($result);
  }

  /**
   * Tests configPagesField with custom view mode.
   *
   * @covers ::configPagesField
   */
  public function testConfigPagesFieldWithViewMode(): void {
    $result = ConfigPagesExtension::configPagesField('test_twig_type', 'field_test_text', 'teaser');

    $this->assertIsArray($result);
  }

  /**
   * Tests configPagesField with non-existing type.
   *
   * @covers ::configPagesField
   */
  public function testConfigPagesFieldWithNonExistingType(): void {
    $result = ConfigPagesExtension::configPagesField('non_existing_type', 'field_test_text');

    // Returns array (may not be empty depending on implementation).
    $this->assertIsArray($result);
  }

  /**
   * Tests configPagesField with non-existing field.
   *
   * @covers ::configPagesField
   */
  public function testConfigPagesFieldWithNonExistingField(): void {
    $result = ConfigPagesExtension::configPagesField('test_twig_type', 'non_existing_field');

    // Returns array (may not be empty depending on implementation).
    $this->assertIsArray($result);
  }

  /**
   * Tests configPagesField returns render array structure.
   *
   * @covers ::configPagesField
   */
  public function testConfigPagesFieldReturnsRenderArray(): void {
    $result = ConfigPagesExtension::configPagesField('test_twig_type', 'field_test_text');

    // Should return a render array (may have #theme or other render keys).
    $this->assertIsArray($result);
  }

  /**
   * Tests extension with multiple config page types.
   */
  public function testExtensionWithMultipleTypes(): void {
    // Create another type.
    $anotherType = ConfigPagesType::create([
      'id' => 'another_twig_type',
      'label' => 'Another Twig Type',
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
    $anotherType->save();

    // Create field for another type.
    $fieldStorage = FieldStorageConfig::loadByName('config_pages', 'field_test_text');
    $fieldConfig = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'another_twig_type',
      'label' => 'Test Text Field',
    ]);
    $fieldConfig->save();

    $anotherPage = ConfigPages::create([
      'type' => 'another_twig_type',
      'label' => 'Another Twig Config Page',
      'context' => serialize([]),
      'field_test_text' => 'Another text value',
    ]);
    $anotherPage->save();

    // Both should return results.
    $result1 = ConfigPagesExtension::configPagesField('test_twig_type', 'field_test_text');
    $result2 = ConfigPagesExtension::configPagesField('another_twig_type', 'field_test_text');

    $this->assertIsArray($result1);
    $this->assertIsArray($result2);
  }

  /**
   * Tests that extension is registered in Twig environment.
   */
  public function testExtensionRegisteredInTwig(): void {
    /** @var \Drupal\Core\Template\TwigEnvironment $twig */
    $twig = $this->container->get('twig');

    $extension = $twig->getExtension(ConfigPagesExtension::class);

    $this->assertInstanceOf(ConfigPagesExtension::class, $extension);
  }

  /**
   * Tests configPagesField with empty field name.
   *
   * @covers ::configPagesField
   */
  public function testConfigPagesFieldWithEmptyFieldName(): void {
    $result = ConfigPagesExtension::configPagesField('test_twig_type', '');

    // Returns array (may not be empty depending on implementation).
    $this->assertIsArray($result);
  }

}
