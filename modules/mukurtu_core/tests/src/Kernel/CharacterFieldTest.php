<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Form\CharacterPaletteSettingsForm;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Characters field on language terms and its site setting (#2560).
 */
#[Group('mukurtu_core')]
class CharacterFieldTest extends KernelTestBase {

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
  }

  /**
   * Reads a config file shipped in a config/install directory.
   */
  protected function shipped(string $name, string $module = 'mukurtu_core'): array {
    $path = $module === 'profile'
      ? dirname(__DIR__, 5) . '/config/install/'
      : $this->container->get('extension.list.module')->getPath($module) . '/config/install/';
    return Yaml::decode(file_get_contents($path . $name . '.yml'));
  }

  /**
   * Creates the shipped Characters field on language terms.
   */
  protected function createField(): void {
    FieldStorageConfig::create($this->shipped('field.storage.taxonomy_term.field_characters'))->save();
    FieldConfig::create($this->shipped('field.field.taxonomy_term.language.field_characters'))->save();
  }

  /**
   * Tests that characters come back exactly as entered.
   */
  public function testCharactersRoundTrip(): void {
    $this->createField();

    $entries = [
      // Tlingit: x, a combining macron below and a modifier letter apostrophe.
      ['character' => "x\u{0331}\u{02BC}", 'name' => 'x underbar ejective'],
      // Arabic script, written right to left.
      ['character' => "\u{06AD}", 'name' => NULL],
      // Osage, outside the Basic Multilingual Plane.
      ['character' => "\u{104B0}", 'name' => 'Osage capital letter A'],
    ];
    $term = Term::create(['vid' => 'language', 'name' => 'Test', 'field_characters' => $entries]);
    $term->save();

    $loaded = Term::load($term->id());
    $this->assertSame($entries, array_map(
      fn(array $item): array => ['character' => $item['character'], 'name' => $item['name']],
      $loaded->get('field_characters')->getValue(),
    ));
  }

  /**
   * Tests that empty rows are dropped and names are tidied.
   */
  public function testEmptyRowsAndNames(): void {
    $this->createField();

    $term = Term::create([
      'vid' => 'language',
      'name' => 'Test',
      'field_characters' => [
        ['character' => ' ł ', 'name' => '   '],
        ['character' => '', 'name' => 'A name with nothing to insert'],
        ['character' => '   ', 'name' => NULL],
      ],
    ]);
    $term->save();

    $items = Term::load($term->id())->get('field_characters')->getValue();
    $this->assertCount(1, $items, 'Rows with no character are not saved.');
    $this->assertSame('ł', $items[0]['character'], 'Spaces around a character are trimmed.');
    $this->assertNull($items[0]['name'], 'A blank name is stored as no name.');
  }

  /**
   * Tests that a character entry can't hold a whole word.
   */
  public function testCharacterLengthLimit(): void {
    $this->createField();

    $term = Term::create([
      'vid' => 'language',
      'name' => 'Test',
      'field_characters' => [
        ['character' => str_repeat('a', 17)],
      ],
    ]);
    $violations = $term->validate();
    $this->assertCount(1, $violations);
    $this->assertSame('field_characters.0.character', $violations[0]->getPropertyPath());
  }

  /**
   * Tests that the palette ships off and managers can turn it on.
   */
  public function testShippedSettings(): void {
    $this->assertSame(['enabled' => FALSE], $this->shipped(CharacterPaletteSettingsForm::SETTINGS));
    $this->assertContains('administer mukurtu character palette', $this->shipped('user.role.mukurtu_manager', 'profile')['permissions']);
  }

  /**
   * Tests that the update hook sets up an existing site, once.
   */
  public function testUpdateHook(): void {
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();

    $this->container->get('module_handler')->loadInclude('mukurtu_core', 'install');
    $message = mukurtu_core_update_40223();

    $this->assertFalse($this->config(CharacterPaletteSettingsForm::SETTINGS)->get('enabled'), 'The palette stays off.');
    $this->assertNotNull($this->config(CharacterPaletteSettingsForm::SETTINGS)->get('enabled'), 'The setting exists.');
    $this->assertTrue(Role::load('mukurtu_manager')->hasPermission('administer mukurtu character palette'));
    $this->assertNotNull(FieldConfig::load('taxonomy_term.language.field_characters'));
    $this->assertStringContainsString('Characters field', $message);

    $this->assertSame('The character palette was already set up; nothing to change.', mukurtu_core_update_40223());
  }

  /**
   * Tests that a site turned on before the update keeps its choice.
   */
  public function testUpdateHookKeepsExistingSetting(): void {
    $this->config(CharacterPaletteSettingsForm::SETTINGS)->set('enabled', TRUE)->save();

    $this->container->get('module_handler')->loadInclude('mukurtu_core', 'install');
    mukurtu_core_update_40223();

    $this->assertTrue($this->config(CharacterPaletteSettingsForm::SETTINGS)->get('enabled'));
  }

}
