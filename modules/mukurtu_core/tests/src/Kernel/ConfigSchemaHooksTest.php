<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the schema ConfigSchemaHooks adds for contrib config.
 *
 * @see \Drupal\mukurtu_core\Hook\ConfigSchemaHooks
 */
#[Group('mukurtu_core')]
class ConfigSchemaHooksTest extends KernelTestBase {

  use SchemaCheckTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'consent_popup',
    'ctools',
    'geofield',
    'language',
    'leaflet',
    'mukurtu_core',
    'path',
    'path_alias',
    'pathauto',
    'system',
    'token',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'language']);
  }

  /**
   * Reads a config file the profile ships.
   */
  protected function shipped(string $name): array {
    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $data = (new FileStorage($profile_path . '/config/install'))->read($name);
    $this->assertIsArray($data, $name);
    return $data;
  }

  /**
   * Asserts the data matches its schema, listing the errors if it doesn't.
   */
  protected function assertMatchesSchema(string $name, array $data): void {
    $result = $this->checkConfigSchema(\Drupal::service('config.typed'), $name, $data);
    $this->assertTrue($result, is_array($result) ? implode("\n", $result) : "$name has no schema");
  }

  /**
   * The shipped consent popup block matches the schema.
   */
  public function testShippedConsentPopupBlock(): void {
    $name = 'block.block.mukurtu_v4_consentpopup';
    $this->assertMatchesSchema($name, $this->shipped($name));
  }

  /**
   * Text for a language added after install matches the schema.
   */
  public function testConsentPopupTextInAddedLanguage(): void {
    $name = 'block.block.mukurtu_v4_consentpopup';
    $data = $this->shipped($name);
    $data['settings']['mi'] = $data['settings']['en'];
    $this->assertFalse($this->checkConfigSchema(\Drupal::service('config.typed'), $name, $data) === TRUE, 'Text for a language the site does not have is rejected.');

    ConfigurableLanguage::createFromLangcode('mi')->save();

    $this->assertMatchesSchema($name, $data);
  }

  /**
   * Pathauto patterns with a condition uuid match the schema.
   */
  public function testPathautoConditionUuid(): void {
    $name = 'pathauto.pattern.mukurtu_basic_pages_pattern';
    $data = $this->shipped($name);
    $this->assertMatchesSchema($name, $data);

    foreach ($data['selection_criteria'] as $uuid => $condition) {
      $data['selection_criteria'][$uuid]['uuid'] = $uuid;
    }
    $this->assertMatchesSchema($name, $data);
  }

}
