<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests where the language fields sit on the language term form.
 *
 * Each field reaches a site two ways: config/install on a fresh install, and
 * an update hook on an existing one. Both have to put it on the form in the
 * same place, or editors have no way to set it. Read right to left comes
 * directly after the language code (#2354, mukurtu_core_update_40218()), and
 * Characters directly after that (#2560, mukurtu_core_update_40223()).
 */
#[Group('mukurtu_core')]
class LanguageFormDisplayTest extends KernelTestBase {

  /**
   * The language term form's components, in the order they should render.
   */
  protected const FIELDS = [
    'name',
    'field_language_code',
    'field_language_rtl',
    'field_characters',
    'description',
    'langcode',
    'path',
    'status',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'path_alias',
    'path',
    // Without it core pins the term's Language select at weight 2 whatever
    // the config says, which no Mukurtu site does, since the profile requires
    // it.
    'language',
    'taxonomy',
    'geofield',
    'leaflet',
    'node',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'field', 'filter']);

    Vocabulary::create(['vid' => 'language', 'name' => 'Language'])->save();
    FieldStorageConfig::create($this->shipped('field.storage.taxonomy_term.field_language_code'))->save();
    FieldConfig::create($this->shipped('field.field.taxonomy_term.language.field_language_code'))->save();
  }

  /**
   * Reads a config file shipped in mukurtu_core's config/install.
   */
  protected function shipped(string $name): array {
    $path = $this->container->get('extension.list.module')->getPath('mukurtu_core') . '/config/install/';
    return Yaml::decode(file_get_contents($path . $name . '.yml'));
  }

  /**
   * Returns a form display's component names in the order they render.
   */
  protected function order(EntityFormDisplay $display): array {
    $components = $display->getComponents();
    uasort($components, fn(array $a, array $b): int => $a['weight'] <=> $b['weight']);
    return array_keys($components);
  }

  /**
   * Creates a shipped field's storage and its field on the language terms.
   */
  protected function createShippedField(string $field_name): void {
    FieldStorageConfig::create($this->shipped('field.storage.taxonomy_term.' . $field_name))->save();
    FieldConfig::create($this->shipped('field.field.taxonomy_term.language.' . $field_name))->save();
  }

  /**
   * Tests that a fresh install puts both fields on the form.
   */
  public function testShippedFormDisplayIncludesCheckbox(): void {
    $this->createShippedField('field_language_rtl');
    $this->createShippedField('field_characters');
    $display = EntityFormDisplay::create($this->shipped('core.entity_form_display.taxonomy_term.language.default'));
    $display->save();

    $component = $display->getComponent('field_language_rtl');
    $this->assertNotNull($component, 'The shipped form display shows the checkbox.');
    $this->assertSame('boolean_checkbox', $component['type']);
    $component = $display->getComponent('field_characters');
    $this->assertNotNull($component, 'The shipped form display shows the Characters field.');
    $this->assertSame('mukurtu_character', $component['type']);

    $this->assertSame(
      self::FIELDS,
      array_values(array_intersect($this->order($display), self::FIELDS)),
      'The checkbox comes directly after the language code, and Characters directly after it.',
    );
    $weights = array_column($display->getComponents(), 'weight');
    $this->assertSame(count($weights), count(array_unique($weights)), 'No two components share a weight.');
  }

  /**
   * Tests that the update hooks give an existing site the same order.
   */
  public function testUpdateHookMatchesShippedOrder(): void {
    // The display as existing sites have it: the shipped one before either
    // field existed, with its weights from then.
    $values = $this->shipped('core.entity_form_display.taxonomy_term.language.default');
    unset($values['content']['field_language_rtl'], $values['content']['field_characters']);
    $values['dependencies']['config'] = array_values(array_diff($values['dependencies']['config'], [
      'field.field.taxonomy_term.language.field_language_rtl',
      'field.field.taxonomy_term.language.field_characters',
    ]));
    $before = ['name' => 0, 'field_language_code' => 1, 'description' => 2, 'langcode' => 3, 'path' => 4, 'status' => 5];
    foreach ($before as $name => $weight) {
      $values['content'][$name]['weight'] = $weight;
    }
    EntityFormDisplay::create($values)->save();

    $this->container->get('module_handler')->loadInclude('mukurtu_core', 'install');
    mukurtu_core_update_40218();
    mukurtu_core_update_40223();

    $updated = EntityFormDisplay::load('taxonomy_term.language.default');
    $shipped = EntityFormDisplay::create($this->shipped('core.entity_form_display.taxonomy_term.language.default'));
    $this->assertNotNull($updated->getComponent('field_characters'), 'The update hook adds the Characters field to the form.');
    $this->assertSame($this->order($shipped), $this->order($updated));
    $weights = function (EntityFormDisplay $display): array {
      $weights = array_map(fn(array $options): int => $options['weight'], $display->getComponents());
      ksort($weights);
      return $weights;
    };
    $this->assertSame($weights($shipped), $weights($updated), 'Every component lands on the same weight as a fresh install.');
  }

}
