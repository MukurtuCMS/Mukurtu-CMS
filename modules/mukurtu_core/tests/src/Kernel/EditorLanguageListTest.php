<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the CKEditor Language dropdown built from the language vocabulary.
 *
 * CKEditor 5's Language plugin marks a run of text with a lang attribute,
 * which is what SC 3.1.2 needs for material that changes language mid-field.
 * Core offers three sources for the dropdown and none of them holds
 * Indigenous languages, so mukurtu_core replaces the list with the site's own
 * language terms (#2354).
 *
 * @see \Drupal\mukurtu_core\Hook\EditorLanguageListHooks::editorJsSettingsAlter()
 */
#[Group('mukurtu_core')]
class EditorLanguageListTest extends KernelTestBase {

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
    'taxonomy',
    // Installed rather than loaded: the list is replaced by an OOP #[Hook],
    // which is never discovered unless the module is enabled, and every
    // assertion here would then pass against settings nothing had touched.
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
    FieldStorageConfig::create([
      'field_name' => 'field_language_code',
      'entity_type' => 'taxonomy_term',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_language_code',
      'entity_type' => 'taxonomy_term',
      'bundle' => 'language',
      'label' => 'Language code',
    ])->save();
  }

  /**
   * Creates a language term.
   */
  protected function language(string $name, ?string $code): void {
    $values = ['vid' => 'language', 'name' => $name];
    if ($code !== NULL) {
      $values['field_language_code'] = $code;
    }
    Term::create($values)->save();
  }

  /**
   * Settings shaped the way the editor module hands them to the hook.
   *
   * The path was confirmed against a running editor rather than assumed:
   * drupalSettings.editor.formats.<id>.editorSettings.config.language
   * .textPartLanguage.
   */
  protected function settingsWithDropdown(): array {
    return [
      'editor' => [
        'formats' => [
          'basic_html' => [
            'editorSettings' => [
              'config' => [
                'language' => [
                  // Core's default, the United Nations list, standing in for
                  // whatever core put there.
                  'textPartLanguage' => [
                    ['title' => 'Arabic', 'languageCode' => 'ar', 'textDirection' => 'rtl'],
                    ['title' => 'English', 'languageCode' => 'en'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Runs the hook over a settings array.
   */
  protected function alter(array $settings): array {
    \Drupal::moduleHandler()->alter('editor_js_settings', $settings);
    return $settings;
  }

  /**
   * Returns the dropdown entries for a format.
   */
  protected function dropdown(array $settings, string $format = 'basic_html'): array {
    return $settings['editor']['formats'][$format]['editorSettings']['config']['language']['textPartLanguage'];
  }

  /**
   * Tests that the site's languages replace core's list.
   */
  public function testVocabularyReplacesCoreList(): void {
    $this->language('Hawaiian', 'haw');
    $this->language('Te Reo Māori', 'mi');

    $list = $this->dropdown($this->alter($this->settingsWithDropdown()));

    $this->assertSame([
      ['title' => 'Hawaiian', 'languageCode' => 'haw'],
      ['title' => 'Te Reo Māori', 'languageCode' => 'mi'],
    ], $list, "The site's languages are offered, sorted by name, under their own names.");
  }

  /**
   * Tests that a term with no code is left out.
   *
   * An empty lang attribute marks nothing, and the field deliberately allows
   * blank, so offering such a term would produce markup that says nothing.
   */
  public function testTermWithoutCodeIsSkipped(): void {
    $this->language('Hawaiian', 'haw');
    $this->language('Unnamed language', NULL);
    $this->language('Blank code', '');

    $list = $this->dropdown($this->alter($this->settingsWithDropdown()));

    $this->assertSame([['title' => 'Hawaiian', 'languageCode' => 'haw']], $list);
  }

  /**
   * Tests that no coded language empties the list rather than inheriting.
   *
   * Leaving core's list would offer the United Nations' six languages as
   * though they were the site's, in exactly the context where that is most
   * misleading.
   */
  public function testNoCodedLanguagesEmptiesTheList(): void {
    $this->language('Unnamed language', NULL);

    $list = $this->dropdown($this->alter($this->settingsWithDropdown()));

    $this->assertSame([], $list);
  }

  /**
   * Tests that a format without the dropdown is left alone.
   *
   * Only formats whose toolbar offers the button carry this key, so the
   * vocabulary must not be queried, or the settings rewritten, for the rest.
   */
  public function testFormatWithoutDropdownIsUntouched(): void {
    $this->language('Hawaiian', 'haw');
    $before = [
      'editor' => [
        'formats' => [
          'plain_text' => ['editorSettings' => ['config' => ['toolbar' => ['items' => []]]]],
        ],
      ],
    ];

    $this->assertSame($before, $this->alter($before));
  }

}
