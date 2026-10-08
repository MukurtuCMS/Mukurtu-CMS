<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_dictionary\Entity\DictionaryWord;
use Drupal\mukurtu_dictionary\Hook\LanguageAttributeHooks;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that dictionary text in the word's language carries a lang attribute.
 *
 * @see \Drupal\mukurtu_dictionary\Hook\LanguageAttributeHooks
 * @see \Drupal\mukurtu_core\Plugin\Validation\Constraint\LanguageTagConstraint
 */
#[Group('mukurtu_dictionary')]
class DictionaryWordLangAttributeTest extends DictionaryTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create the field from the shipped config, so the test fails if that
    // config stops being valid.
    $config_path = $this->container->get('extension.list.module')->getPath('mukurtu_core') . '/config/install/';
    FieldStorageConfig::create(Yaml::decode(file_get_contents($config_path . 'field.storage.taxonomy_term.field_language_code.yml')))->save();
    FieldConfig::create(Yaml::decode(file_get_contents($config_path . 'field.field.taxonomy_term.language.field_language_code.yml')))->save();

    $this->language = Term::load($this->language->id());
    $this->language->set('field_language_code', 'haw')->save();
  }

  /**
   * Renders one field of an entity and loads it for CSS selection.
   */
  private function renderField(object $entity, string $field_name, string $label = 'hidden'): void {
    $build = $entity->get($field_name)->view(['label' => $label]);
    $this->setRawContent((string) $this->container->get('renderer')->renderInIsolation($build));
  }

  /**
   * Builds and saves a word with a sample sentence and an additional entry.
   */
  private function createWord(): DictionaryWord {
    $word = $this->buildDictionaryWord('aloha');
    $word->set('field_translation', 'hello');
    $word->set('field_alternate_spelling', 'alohaa');
    $word->set('field_sample_sentences', [
      Paragraph::create(['type' => 'sample_sentence', 'field_sentence' => 'Aloha kakahiaka.']),
    ]);
    $word->set('field_additional_word_entries', [
      Paragraph::create([
        'type' => 'dictionary_word_entry',
        'field_word_entry_term' => 'alohalani',
        'field_translation' => 'heavenly love',
        'field_sample_sentences' => [
          Paragraph::create(['type' => 'sample_sentence', 'field_sentence' => 'He alohalani.']),
        ],
      ]),
    ]);
    $word->save();
    return Node::load($word->id());
  }

  /**
   * The word reports the code from its language term, trimmed.
   */
  public function testGetLanguageCode(): void {
    $word = $this->createWord();
    $this->assertSame('haw', $word->getLanguageCode());
    $this->assertSame($this->language->id(), $word->getLanguageTerm()->id());

    $this->language->set('field_language_code', '  ')->save();
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $word = Node::load($word->id());
    $this->assertNull($word->getLanguageCode());
  }

  /**
   * The headword's single value with a hidden label is marked on its wrapper.
   */
  public function testTitleIsMarked(): void {
    $this->renderField($this->createWord(), 'title');
    $this->assertCount(1, $this->cssSelect('[lang="haw"]'));
    $this->assertStringContainsString('aloha', (string) $this->cssSelect('[lang="haw"]')[0]);
  }

  /**
   * Each value is marked, but a visible label stays in the page language.
   */
  public function testVisibleLabelIsNotMarked(): void {
    $this->renderField($this->createWord(), 'field_alternate_spelling', 'above');
    $marked = $this->xpath('//*[@lang="haw"]');
    $this->assertCount(1, $marked);
    $this->assertSame('alohaa', trim((string) $marked[0]));
    $this->assertCount(0, $this->xpath('//*[@lang][contains(., "Alternate Spelling")]'));
  }

  /**
   * Text in the page language, like the translation, is not marked.
   */
  public function testTranslationIsNotMarked(): void {
    $this->renderField($this->createWord(), 'field_translation');
    $this->assertRaw('hello');
    $this->assertCount(0, $this->cssSelect('[lang]'));
  }

  /**
   * Sample sentences and entry terms find the word through their parents.
   */
  public function testParagraphsAreMarked(): void {
    $word = $this->createWord();

    $sentence = $word->get('field_sample_sentences')->entity;
    $this->renderField($sentence, 'field_sentence');
    $this->assertCount(1, $this->cssSelect('[lang="haw"]'));

    $entry = $word->get('field_additional_word_entries')->entity;
    $this->renderField($entry, 'field_word_entry_term');
    $this->assertCount(1, $this->cssSelect('[lang="haw"]'));
    $this->renderField($entry, 'field_translation');
    $this->assertCount(0, $this->cssSelect('[lang]'));

    // A sample sentence on an additional entry is two parents down.
    $nested = $entry->get('field_sample_sentences')->entity;
    $this->assertSame($word->id(), LanguageAttributeHooks::findWord($nested)?->id());
    $this->renderField($nested, 'field_sentence');
    $this->assertCount(1, $this->cssSelect('[lang="haw"]'));
  }

  /**
   * A language term without a code adds no lang attribute.
   */
  public function testBlankCodeAddsNothing(): void {
    $this->language->set('field_language_code', NULL)->save();
    $word = $this->createWord();

    $this->renderField($word, 'title');
    $this->assertRaw('aloha');
    $this->assertCount(0, $this->cssSelect('[lang]'));

    $this->renderField($word->get('field_sample_sentences')->entity, 'field_sentence');
    $this->assertCount(0, $this->cssSelect('[lang]'));
  }

  /**
   * A rendered word is invalidated when its language term changes.
   */
  public function testRenderedWordDependsOnLanguageTerm(): void {
    $word = $this->createWord();
    $build = $this->container->get('entity_type.manager')->getViewBuilder('node')->view($word, 'teaser');
    $this->container->get('renderer')->renderInIsolation($build);
    $this->assertContains('taxonomy_term:' . $this->language->id(), $build['#cache']['tags']);
  }

  /**
   * The page title on a word's own page is marked.
   */
  public function testPageTitleIsMarked(): void {
    $word = $this->createWord();
    $hooks = new LanguageAttributeHooks($this->routeMatchFor('entity.node.canonical', $word));

    $variables = ['title_attributes' => []];
    $hooks->preprocessPageTitle($variables);
    $this->assertSame('haw', $variables['title_attributes']['lang'] ?? NULL);

    // Other routes for the same word, like its edit form, are left alone.
    $hooks = new LanguageAttributeHooks($this->routeMatchFor('entity.node.edit_form', $word));
    $variables = ['title_attributes' => []];
    $hooks->preprocessPageTitle($variables);
    $this->assertArrayNotHasKey('lang', $variables['title_attributes']);
  }

  /**
   * The Language code field accepts language tags and rejects anything else.
   */
  public function testLanguageCodeValidation(): void {
    foreach (['haw', 'mi', 'qaa', 'x-mylang', 'en-US', 'zh-Hant-TW', 'i-klingon'] as $valid) {
      $this->language->set('field_language_code', $valid);
      $this->assertCount(0, $this->language->validate()->getByField('field_language_code'), "'$valid' was rejected.");
    }

    foreach (['h', 'haw!', '-haw', 'haw-', 'ha w', 'x', 'haw--x', 'abcdefghi', 'x-mylanguage'] as $invalid) {
      $this->language->set('field_language_code', $invalid);
      $violations = $this->language->validate()->getByField('field_language_code');
      $this->assertCount(1, $violations, "'$invalid' was accepted.");
      $this->assertSame(
        "$invalid isn't a valid language code. Use letters, digits, and hyphens, such as <em>haw</em> or <em>x-mylang</em>.",
        (string) $violations[0]->getMessage(),
      );
    }
  }

  /**
   * Spaces around a code are not an error, and are not stored.
   */
  public function testLanguageCodeIsTrimmed(): void {
    $this->language->set('field_language_code', ' haw ');
    $this->assertCount(0, $this->language->validate()->getByField('field_language_code'));
    $this->language->save();
    $this->assertSame('haw', Term::load($this->language->id())->get('field_language_code')->value);

    $this->language->set('field_language_code', '   ')->save();
    $this->assertTrue(Term::load($this->language->id())->get('field_language_code')->isEmpty());
  }

  /**
   * The theme exposes the code to templates that print the title themselves.
   *
   * The word tabs and the featured view mode print raw values rather than
   * fields, so they read word_langcode instead of relying on the field hook.
   */
  public function testThemeExposesWordLangcode(): void {
    $theme_path = $this->container->get('extension.list.theme')->getPath('mukurtu_v4');
    require_once $this->root . '/' . $theme_path . '/mukurtu_v4.theme';

    $variables = ['node' => $this->createWord()];
    mukurtu_v4_preprocess_node($variables);
    $this->assertSame('haw', $variables['word_langcode'] ?? NULL);

    $this->language->set('field_language_code', NULL)->save();
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $variables = ['node' => Node::load($variables['node']->id())];
    mukurtu_v4_preprocess_node($variables);
    $this->assertNull($variables['word_langcode']);
  }

  /**
   * Builds a route match for a node route.
   */
  private function routeMatchFor(string $route_name, DictionaryWord $word): RouteMatchInterface {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn($route_name);
    $route_match->method('getParameter')->willReturnMap([['node', $word]]);
    return $route_match;
  }

}
