<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
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
   * @param array $values
   *   The submitted values.
   * @param array $empty_rows
   *   Empty rows per group index, as if "Add new row" had been pressed.
   *
   * @return \Drupal\Core\Form\FormState
   *   The submitted form state, for checking errors.
   */
  protected function submit(array $values, array $empty_rows = []): FormState {
    // A programmed submission counts any value for a checkbox, even 0, as
    // checked, so unchecked boxes are left out of $values.
    $form_state = (new FormState())->setValues($values + ['op' => 'Save configuration']);
    $form_state->set('empty_rows', $empty_rows);
    $this->container->get('form_builder')->submitForm(CharacterPaletteSettingsForm::class, $form_state);
    return $form_state;
  }

  /**
   * Builds the rows of a submitted group, ending with one empty row.
   */
  protected function rows(array $rows): array {
    return array_merge($rows, [['character' => '', 'name' => '']]);
  }

  /**
   * Counts the rows in a built group's table.
   */
  protected function countRows(array $group): int {
    return count(array_filter(array_keys($group['characters']), 'is_int'));
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
    ], [0 => 4]);

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
      ],
    ], [0 => 4]);

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
   * Tests that "Add new row" adds an empty row to its own group only.
   */
  public function testAddNewRow(): void {
    $this->config(CharacterPaletteSettingsForm::SETTINGS)->set('groups', [
      ['label' => 'Tlingit', 'characters' => [['character' => 'ł', 'name' => '']]],
    ])->save();
    $form_builder = $this->container->get('form_builder');
    $form_object = $this->container->get('class_resolver')->getInstanceFromDefinition(CharacterPaletteSettingsForm::class);
    $form_state = (new FormState())->setFormObject($form_object);
    $form = $form_builder->buildForm($form_object, $form_state);

    $this->assertSame(2, $this->countRows($form['groups'][0]), 'A group ends with one empty row.');
    $this->assertSame(1, $this->countRows($form['groups'][1]), 'The new group starts with one empty row.');
    $button = $form['groups'][0]['add_row'];
    $this->assertSame('Add new row to Tlingit', (string) $button['#attributes']['aria-label']);
    $this->assertSame([], $button['#limit_validation_errors'], 'Adding a row never trips validation.');

    $form_state->setTriggeringElement($button);
    $form_object->addRow($form, $form_state);
    $this->assertTrue($form_state->isRebuilding());
    $rebuilt = $form_builder->retrieveForm($form_object->getFormId(), $form_state);

    $this->assertSame(3, $this->countRows($rebuilt['groups'][0]), 'The pressed group gets another empty row.');
    $this->assertSame(1, $this->countRows($rebuilt['groups'][1]), 'Other groups are unchanged.');

    $this->config(CharacterPaletteSettingsForm::SETTINGS)->set('groups', [
      ['label' => '<em>Tlingit</em>', 'characters' => []],
    ])->save();
    $tagged_state = new FormState();
    $tagged = $form_builder->buildForm($form_object, $tagged_state);
    $this->assertSame('&lt;em&gt;Tlingit&lt;/em&gt;', (string) $tagged['groups'][0]['#title'], 'The legend shows a name exactly as typed.');
  }

  /**
   * Tests that rows save in the order the form submits them.
   */
  public function testRowsSaveInSubmittedOrder(): void {
    $this->submit([
      'groups' => [
        [
          'label' => 'Test',
          'characters' => [
            ['character' => 'a', 'name' => 'First', 'weight' => 3],
            // Osage, outside the Basic Multilingual Plane.
            ['character' => "\u{104B0}", 'name' => 'Second', 'weight' => 1],
            ['character' => '', 'name' => '', 'weight' => 2],
            ['character' => 'c', 'name' => 'Third', 'weight' => 0],
          ],
        ],
      ],
    ], [0 => 4]);

    $this->assertSame([
      ['character' => 'c', 'name' => 'Third'],
      ['character' => "\u{104B0}", 'name' => 'Second'],
      ['character' => 'a', 'name' => 'First'],
    ], $this->config(CharacterPaletteSettingsForm::SETTINGS)->get('groups')[0]['characters']);
  }

  /**
   * Tests that a long list, past any select's limit, can still move its rows.
   */
  public function testLongListKeepsItsOrder(): void {
    $rows = [];
    foreach (range(0, 150) as $number) {
      $rows[] = ['character' => "c$number", 'name' => '', 'weight' => 150 - $number];
    }
    $form_state = $this->submit(['groups' => [['label' => 'Test', 'characters' => $rows]]], [0 => 151]);

    $this->assertSame([], $form_state->getErrors());
    $saved = $this->config(CharacterPaletteSettingsForm::SETTINGS)->get('groups')[0]['characters'];
    $this->assertSame(['c150', 'c149'], array_column(array_slice($saved, 0, 2), 'character'));
    $this->assertSame('c0', $saved[150]['character']);
  }

  /**
   * Tests that a rebuild keeps unsaved moves and puts a new row last.
   *
   * Pressing "Add new row" rebuilds the form, and so does a failed save, so
   * both come through here.
   */
  public function testRebuildKeepsSubmittedOrder(): void {
    $this->config(CharacterPaletteSettingsForm::SETTINGS)->set('groups', [
      [
        'label' => 'Test',
        'characters' => [
          ['character' => 'a', 'name' => ''],
          ['character' => 'b', 'name' => ''],
        ],
      ],
    ])->save();
    $form_state = (new FormState())->setUserInput([
      'groups' => [
        [
          'characters' => [
            ['character' => 'a', 'name' => '', 'weight' => '2'],
            ['character' => 'b', 'name' => '', 'weight' => '1'],
            ['character' => '', 'name' => '', 'weight' => '3'],
          ],
        ],
      ],
    ]);
    $form_state->set('empty_rows', [0 => 2]);
    $form_object = $this->container->get('class_resolver')->getInstanceFromDefinition(CharacterPaletteSettingsForm::class);
    $form_state->setFormObject($form_object);
    $table = $form_object->buildForm([], $form_state)['groups'][0]['characters'];

    $deltas = array_values(array_filter(array_keys($table), 'is_int'));
    $this->assertSame([1, 0, 2, 3], $deltas, 'Rows show in submitted order, with the new row last.');
    $this->assertSame('b', $table[1]['character']['#default_value']);
    $this->assertSame('Character 1 in Test', (string) $table[1]['character']['#title'], 'Labels number the rows as shown.');
    $this->assertSame('Order for character 2 in Test', (string) $table[0]['weight']['#title']);
  }

  /**
   * Tests that translated names stay with their characters.
   */
  public function testTranslationsFollowTheirCharacters(): void {
    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('es')->save();
    $this->config(CharacterPaletteSettingsForm::SETTINGS)->set('groups', [
      [
        'label' => 'Removed',
        'characters' => [['character' => 'z', 'name' => 'Zed']],
      ],
      [
        'label' => 'Kept',
        'characters' => [
          ['character' => 'a', 'name' => 'Aye'],
          ['character' => 'b', 'name' => 'Bee'],
          ['character' => 'c', 'name' => 'Cee'],
        ],
      ],
    ])->save();
    $language_manager = $this->container->get('language_manager');
    $override = $language_manager->getLanguageConfigOverride('es', CharacterPaletteSettingsForm::SETTINGS);
    $override->set('groups', [
      0 => ['label' => 'Quitado', 'characters' => [0 => ['name' => 'Zeta']]],
      1 => [
        'label' => 'Guardado',
        'characters' => [
          0 => ['name' => 'A'],
          1 => ['name' => 'Be'],
          2 => ['name' => 'Ce'],
        ],
      ],
    ])->save();

    // Delete the first group and a row, and swap the other two rows.
    $this->submit([
      'groups' => [
        [
          'label' => 'Removed',
          'delete' => 1,
          'characters' => $this->rows([['character' => 'z', 'name' => 'Zed']]),
        ],
        [
          'label' => 'Kept',
          'characters' => [
            ['character' => 'a', 'name' => 'Aye', 'weight' => 2],
            ['character' => '', 'name' => '', 'weight' => 1],
            ['character' => 'c', 'name' => 'Cee', 'weight' => 0],
          ],
        ],
      ],
    ], [1 => 3]);

    $override = $language_manager->getLanguageConfigOverride('es', CharacterPaletteSettingsForm::SETTINGS);
    $this->assertEquals([
      0 => [
        'label' => 'Guardado',
        'characters' => [
          0 => ['name' => 'Ce'],
          1 => ['name' => 'A'],
        ],
      ],
    ], $override->get('groups'));

    // Removing everything translated leaves no translation behind.
    $this->submit(['groups' => [['label' => 'Kept', 'delete' => 1]]]);
    $this->assertTrue($language_manager->getLanguageConfigOverride('es', CharacterPaletteSettingsForm::SETTINGS)->isNew());
  }

  /**
   * Tests that a group with characters needs a name.
   */
  public function testGroupNeedsName(): void {
    $form_state = $this->submit([
      'enabled' => 1,
      'groups' => [['label' => '  ', 'characters' => $this->rows([['character' => 'ł', 'name' => '']])]],
    ]);

    $this->assertSame('Language name is required. Enter the language name.', (string) ($form_state->getErrors()['groups][0][label'] ?? ''));
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
    mukurtu_core_update_40225();

    $config = $this->config(CharacterPaletteSettingsForm::SETTINGS);
    $this->assertFalse($config->get('enabled'), 'The palette stays off.');
    $this->assertSame([], $config->get('groups'));
    $this->assertTrue(Role::load('mukurtu_manager')->hasPermission('administer mukurtu character palette'));

    $this->assertSame('The character palette was already set up; nothing to change.', mukurtu_core_update_40225());
  }

  /**
   * Tests that a site set up before the update keeps its settings.
   */
  public function testUpdateHookKeepsExistingSettings(): void {
    $groups = [['label' => 'Test', 'characters' => [['character' => 'ł', 'name' => '']]]];
    $this->config(CharacterPaletteSettingsForm::SETTINGS)->set('enabled', TRUE)->set('groups', $groups)->save();

    $this->container->get('module_handler')->loadInclude('mukurtu_core', 'install');
    mukurtu_core_update_40225();

    $this->assertTrue($this->config(CharacterPaletteSettingsForm::SETTINGS)->get('enabled'));
    $this->assertSame($groups, $this->config(CharacterPaletteSettingsForm::SETTINGS)->get('groups'));
  }

}
