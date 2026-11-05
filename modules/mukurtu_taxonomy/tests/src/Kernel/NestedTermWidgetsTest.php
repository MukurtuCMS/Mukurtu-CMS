<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how term fields on content forms show nesting (#2523).
 *
 * @see \Drupal\mukurtu_taxonomy\NestedTermOptions
 * @see mukurtu_dictionary_language_field_config_helper()
 * @see patches/tagify-nested-groups.patch
 */
#[Group('mukurtu_taxonomy')]
class NestedTermWidgetsTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'field',
    'text',
    'filter',
    'options',
    'language',
    'tagify',
    'flat_taxonomy',
    'mukurtu_taxonomy',
  ];

  /**
   * {@inheritdoc}
   *
   * The flat_taxonomy module's own schema file is never discovered, and
   * mukurtu_core, which supplies it, isn't enabled here. Neither is
   * mukurtu_dictionary, whose settings one test writes.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Category terms: Arts > Weaving > Baskets, and Music.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['taxonomy', 'language']);
    ConfigurableLanguage::createFromLangcode('es')->save();
    Vocabulary::create(['vid' => 'category', 'name' => 'Category'])->save();
    $this->setUpCurrentUser([], ['access content', 'administer entity_test content', 'view test entity']);

    $arts = $this->createTerm('Arts', 'Artes');
    $weaving = $this->createTerm('Weaving', 'Tejido', $arts);
    $baskets = $this->createTerm('Baskets', 'Cestas', $weaving);
    $music = $this->createTerm('Music', 'Música');
    $this->terms = compact('arts', 'weaving', 'baskets', 'music');
  }

  /**
   * Creates a category with a Spanish translation.
   */
  protected function createTerm(string $name, string $spanish, ?TermInterface $parent = NULL): TermInterface {
    $term = Term::create(['vid' => 'category', 'name' => $name, 'parent' => $parent?->id() ?? 0]);
    $term->save();
    $term->addTranslation('es', ['name' => $spanish])->save();
    return $term;
  }

  /**
   * Switches the current content language to Spanish.
   */
  protected function useSpanish(): void {
    $this->config('system.site')->set('default_langcode', 'es')->save();
    $this->container->get('language.default')->set(ConfigurableLanguage::load('es'));
    $this->container->get('language_manager')->reset();
  }

  /**
   * Gets Tagify's suggestion for a term, as the autocomplete returns it.
   */
  protected function suggestion(string $key): array {
    $matches = $this->container->get('tagify.autocomplete_matcher')->getMatches(
      'taxonomy_term',
      'default:taxonomy_term',
      ['target_bundles' => ['category' => 'category'], 'match_operator' => 'CONTAINS'],
      $this->terms[$key]->getName(),
    );
    foreach ($matches as $match) {
      if ((string) $match['entity_id'] === (string) $this->terms[$key]->id()) {
        return $match;
      }
    }
    $this->fail("No suggestion for $key.");
  }

  /**
   * Tagify groups a suggestion under its direct parent, named by its path.
   *
   * The path is in the current language, so groups match the suggestions.
   */
  public function testTagifyGroupsByTranslatedParentPath(): void {
    $baskets = $this->suggestion('baskets');
    $this->assertSame('Arts › Weaving', $baskets['parent_name']);
    $this->assertSame((string) $this->terms['weaving']->id(), $baskets['parent_id']);

    $this->assertSame('Arts', $this->suggestion('weaving')['parent_name']);
    $this->assertSame('', $this->suggestion('arts')['parent_name']);
    $this->assertSame('', $this->suggestion('arts')['parent_id']);

    $this->useSpanish();
    $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->resetCache();
    $manager = $this->container->get('tagify.hierarchical_term_manager');
    $this->assertSame('Artes › Tejido', $manager->getParentName((string) $this->terms['baskets']->id(), 'category'));
  }

  /**
   * Adds a term field with the checkboxes and radios widget to entity_test.
   */
  protected function addField(string $field_name, int $cardinality): void {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'cardinality' => $cardinality,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => $field_name,
      'settings' => ['handler_settings' => ['target_bundles' => ['category' => 'category']]],
    ])->save();
    $display = EntityFormDisplay::load('entity_test.entity_test.default') ?? EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setComponent($field_name, ['type' => 'options_buttons'])->save();
  }

  /**
   * Gets the built widget element of a field on the entity_test form.
   */
  protected function widget(string $field_name): array {
    $form = $this->container->get('entity.form_builder')->getForm(EntityTest::create());
    return $form[$field_name]['widget'];
  }

  /**
   * Checkboxes and radios have no dashes, and nested terms are grouped.
   */
  public function testCheckboxesAndRadiosGroupNestedTerms(): void {
    $this->addField('field_categories', -1);
    $this->addField('field_category', 1);

    foreach (['field_categories', 'field_category'] as $field_name) {
      $widget = $this->widget($field_name);
      $ids = array_map(fn ($term) => $term->id(), $this->terms);

      $this->assertSame('Weaving', (string) $widget[$ids['weaving']]['#title'], $field_name);
      $this->assertSame('Baskets', (string) $widget[$ids['baskets']]['#title'], $field_name);
      $this->assertStringContainsString('role="group" aria-label="Arts"', $widget[$ids['weaving']]['#prefix'] ?? '', $field_name);
      $list_class = $field_name === 'field_categories' ? 'form-checkboxes' : 'form-radios';
      $this->assertStringContainsString('class="' . $list_class . ' mukurtu-nested-term-options__group"', $widget[$ids['weaving']]['#prefix'] ?? '', $field_name);
      $this->assertStringContainsString('role="group" aria-label="Weaving"', $widget[$ids['baskets']]['#prefix'] ?? '', $field_name);
      // Baskets is the last nested term under Arts, so both groups close.
      $this->assertSame('</div></div>', $widget[$ids['baskets']]['#suffix'] ?? '', $field_name);
      $this->assertArrayNotHasKey('#prefix', $widget[$ids['music']], $field_name);
    }
  }

  /**
   * A site's dictionary language limit keeps the translated, ordered options.
   */
  public function testDictionaryLanguageLimitKeepsOptions(): void {
    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.module')->getPath('mukurtu_dictionary') . '/mukurtu_dictionary.module';
    $ids = array_map(fn ($term) => (int) $term->id(), $this->terms);
    $this->config('mukurtu_dictionary_languages.settings')
      ->set('available_languages', [$ids['weaving'] => $ids['weaving'], $ids['music'] => $ids['music']])
      ->save();
    $options = [
      '_none' => '- None -',
      $ids['arts'] => 'Artes',
      $ids['weaving'] => 'Tejido',
      $ids['music'] => 'Música',
    ];
    $form = ['field_dictionary_word_language' => ['widget' => ['#options' => $options]]];

    mukurtu_dictionary_language_field_config_helper($form, new FormState());

    $this->assertSame(['_none' => '- None -', $ids['weaving'] => 'Tejido', $ids['music'] => 'Música'], $form['field_dictionary_word_language']['widget']['#options']);
  }

}
