<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Form\CharacterPaletteSettingsForm;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the character palette's settings and character groups (#2560).
 */
#[Group('mukurtu_core')]
class CharacterPaletteSettingsTest extends KernelTestBase {

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
    $this->installConfig(['system']);
    $this->config(CharacterPaletteSettingsForm::SETTINGS)
      ->setData($this->shipped(CharacterPaletteSettingsForm::SETTINGS))
      ->save();
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
   * Submits the settings form with the given values.
   *
   * @return \Drupal\Core\Form\FormState
   *   The submitted form state, for checking errors.
   */
  protected function submit(array $values): FormState {
    // A programmed submission counts any value for a checkbox, even 0, as
    // checked, so unchecked boxes are left out of $values.
    $form_state = (new FormState())->setValues($values + ['op' => 'Save configuration']);
    $this->container->get('form_builder')->submitForm(CharacterPaletteSettingsForm::class, $form_state);
    return $form_state;
  }

  /**
   * Builds the rows of a submitted group, padded with empty ones.
   */
  protected function rows(array $rows): array {
    return array_merge($rows, array_fill(0, CharacterPaletteSettingsForm::EMPTY_ROWS, ['character' => '', 'name' => '']));
  }

  /**
   * Tests that the palette ships off, empty, and that managers can set it up.
   */
  public function testShippedSettings(): void {
    $this->assertSame(['enabled' => FALSE, 'groups' => []], $this->shipped(CharacterPaletteSettingsForm::SETTINGS));
    $this->assertContains('administer mukurtu character palette', $this->shipped('user.role.mukurtu_manager', 'profile')['permissions']);
  }

  /**
   * Tests that characters come back exactly as entered.
   */
  public function testCharactersRoundTrip(): void {
    $characters = [
      // Tlingit: x, a combining macron below and a modifier letter apostrophe.
      ['character' => "x\u{0331}\u{02BC}", 'name' => 'x underbar ejective'],
      // Arabic script, written right to left.
      ['character' => "\u{06AD}", 'name' => ''],
      // Osage, outside the Basic Multilingual Plane.
      ['character' => "\u{104B0}", 'name' => 'Osage capital letter A'],
    ];
    $form_state = $this->submit([
      'enabled' => 1,
      'groups' => [['label' => 'Test', 'characters' => $this->rows($characters)]],
    ]);

    $this->assertSame([], $form_state->getErrors());
    $config = $this->config(CharacterPaletteSettingsForm::SETTINGS);
    $this->assertTrue($config->get('enabled'));
    $this->assertSame([['label' => 'Test', 'characters' => $characters]], $config->get('groups'));
  }

  /**
   * Tests that empty rows and groups are dropped and the rest is trimmed.
   */
  public function testEmptyRowsAndGroupsAreDropped(): void {
    $this->submit([
      'groups' => [
        [
          'label' => ' Tlingit ',
          'characters' => $this->rows([
            ['character' => ' ł ', 'name' => '   '],
            ['character' => '', 'name' => 'A name with nothing to insert'],
            ['character' => 'ḵ', 'name' => ' k underbar '],
          ]),
        ],
        // The empty new group.
        ['label' => '', 'characters' => $this->rows([])],
      ],
    ]);

    $this->assertSame([
      [
        'label' => 'Tlingit',
        'characters' => [
          ['character' => 'ł', 'name' => ''],
          ['character' => 'ḵ', 'name' => 'k underbar'],
        ],
      ],
    ], $this->config(CharacterPaletteSettingsForm::SETTINGS)->get('groups'));
  }

  /**
   * Tests that a group can be deleted and a new one added in one save.
   */
  public function testDeleteAndAddGroups(): void {
    $this->config(CharacterPaletteSettingsForm::SETTINGS)->set('groups', [
      ['label' => 'Old', 'characters' => [['character' => 'ł', 'name' => '']]],
      ['label' => 'Kept', 'characters' => [['character' => 'ḵ', 'name' => '']]],
    ])->save();

    $this->submit([
      'enabled' => 1,
      'groups' => [
        ['label' => 'Old', 'characters' => $this->rows([['character' => 'ł', 'name' => '']]), 'delete' => 1],
        ['label' => 'Kept', 'characters' => $this->rows([['character' => 'ḵ', 'name' => '']])],
        ['label' => 'New', 'characters' => $this->rows([['character' => 'ʼ', 'name' => 'glottal stop']])],
      ],
    ]);

    $this->assertSame(['Kept', 'New'], array_column($this->config(CharacterPaletteSettingsForm::SETTINGS)->get('groups'), 'label'));
  }

  /**
   * Tests that a group with characters needs a name.
   */
  public function testGroupNeedsName(): void {
    $form_state = $this->submit([
      'enabled' => 1,
      'groups' => [['label' => '  ', 'characters' => $this->rows([['character' => 'ł', 'name' => '']])]],
    ]);

    $this->assertArrayHasKey('groups][0][label', $form_state->getErrors());
    $this->assertSame([], $this->config(CharacterPaletteSettingsForm::SETTINGS)->get('groups'), 'Nothing is saved.');
  }

  /**
   * Tests that a character entry can't hold a whole word.
   */
  public function testCharacterLengthLimit(): void {
    $too_long = ['character' => str_repeat('a', 17), 'name' => ''];
    $form_state = $this->submit([
      'enabled' => 1,
      'groups' => [['label' => 'Test', 'characters' => $this->rows([$too_long])]],
    ]);

    $this->assertArrayHasKey('groups][0][characters][0][character', $form_state->getErrors());
  }

  /**
   * Tests that the update hook sets up an existing site, once.
   */
  public function testUpdateHook(): void {
    $this->config(CharacterPaletteSettingsForm::SETTINGS)->delete();
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();

    $this->container->get('module_handler')->loadInclude('mukurtu_core', 'install');
    mukurtu_core_update_40223();

    $config = $this->config(CharacterPaletteSettingsForm::SETTINGS);
    $this->assertFalse($config->get('enabled'), 'The palette stays off.');
    $this->assertSame([], $config->get('groups'));
    $this->assertTrue(Role::load('mukurtu_manager')->hasPermission('administer mukurtu character palette'));

    $this->assertSame('The character palette was already set up; nothing to change.', mukurtu_core_update_40223());
  }

  /**
   * Tests that a site set up before the update keeps its settings.
   */
  public function testUpdateHookKeepsExistingSettings(): void {
    $groups = [['label' => 'Test', 'characters' => [['character' => 'ł', 'name' => '']]]];
    $this->config(CharacterPaletteSettingsForm::SETTINGS)->set('enabled', TRUE)->set('groups', $groups)->save();

    $this->container->get('module_handler')->loadInclude('mukurtu_core', 'install');
    mukurtu_core_update_40223();

    $this->assertTrue($this->config(CharacterPaletteSettingsForm::SETTINGS)->get('enabled'));
    $this->assertSame($groups, $this->config(CharacterPaletteSettingsForm::SETTINGS)->get('groups'));
  }

}
