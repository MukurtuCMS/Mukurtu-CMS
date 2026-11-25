<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_dictionary\Entity\DictionaryWord;
use Drupal\mukurtu_person\Entity\Person;
use Drupal\mukurtu_place\Entity\Place;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the shipped base field overrides allow what their fields do.
 *
 * Core applies a base field override only to a base field, and these fields
 * come from the bundle classes, so the overrides' target lists have no
 * effect. They're kept matching anyway, so they don't mislead anyone reading
 * them: Person's and Place's Media Assets once left out External Embed.
 *
 * The overrides are read from config/optional, not installed: installing
 * them needs most of Mukurtu.
 */
#[Group('mukurtu_multilingual')]
class BaseFieldOverrideTargetBundlesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'field', 'text'];

  /**
   * Node bundle classes with shipped base field overrides.
   */
  private const BUNDLE_CLASSES = [
    'dictionary_word' => DictionaryWord::class,
    'person' => Person::class,
    'place' => Place::class,
  ];

  /**
   * Each node override that limits a field's targets matches the field.
   */
  public function testOverridesMatchBundleFields(): void {
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_multilingual');
    $storage = new FileStorage($this->root . '/' . $path . '/config/optional');
    $entity_type = \Drupal::entityTypeManager()->getDefinition('node');

    $checked = 0;
    foreach ($storage->listAll('core.base_field_override.node.') as $name) {
      $override = $storage->read($name);
      $override_targets = $override['settings']['handler_settings']['target_bundles'] ?? NULL;
      if ($override_targets === NULL) {
        continue;
      }
      $class = self::BUNDLE_CLASSES[$override['bundle']] ?? NULL;
      $this->assertNotNull($class, "$name: add its bundle class to BUNDLE_CLASSES.");

      $definitions = $class::bundleFieldDefinitions($entity_type, $override['bundle'], []);
      $field = $definitions[$override['field_name']] ?? NULL;
      $this->assertNotNull($field, "$name: the bundle class doesn't define the field.");
      $field_targets = $field->getSetting('handler_settings')['target_bundles'] ?? [];

      ksort($override_targets);
      ksort($field_targets);
      $this->assertSame($field_targets, $override_targets, "$name allows different types from the field.");
      $checked++;
    }
    $this->assertGreaterThan(0, $checked, 'No overrides were checked.');
  }

}
