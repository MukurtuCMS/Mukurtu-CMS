<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_taxonomy\Controller\VocabularyPageController;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\VocabularyInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the public vocabulary page (#2612).
 *
 * @see \Drupal\mukurtu_taxonomy\Controller\VocabularyPageController
 */
#[Group('mukurtu_taxonomy')]
class VocabularyPageTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
    'file',
    'geofield',
    'image',
    'language',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'mukurtu_taxonomy',
    'node',
    'og',
    'options',
    'paragraphs',
    'taxonomy',
    'views',
  ];

  /**
   * The vocabulary.
   */
  protected VocabularyInterface $vocabulary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('es')->save();
    $this->vocabulary = Vocabulary::create(['vid' => 'location', 'name' => 'Location', 'description' => 'Places on this site.']);
    $this->vocabulary->save();
    // The first user is user 1, who bypasses access checks.
    $this->createUser();
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * Creates a term.
   */
  protected function createTerm(string $name, ?TermInterface $parent = NULL, array $values = []): TermInterface {
    $term = Term::create($values + ['vid' => 'location', 'name' => $name, 'parent' => $parent?->id() ?? 0]);
    $term->save();
    return $term;
  }

  /**
   * Builds the page.
   */
  protected function buildPage(): array {
    return $this->container->get('class_resolver')
      ->getInstanceFromDefinition(VocabularyPageController::class)
      ->build($this->vocabulary);
  }

  /**
   * Renders the page.
   */
  protected function renderPage(): string {
    $build = $this->buildPage();
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * The route exists for anyone who can view content.
   */
  public function testRouteAccess(): void {
    $url = Url::fromRoute('mukurtu_taxonomy.vocabulary_page', ['taxonomy_vocabulary' => 'location']);
    $this->assertSame('/taxonomy/location', $url->toString());
    $this->assertTrue($url->access($this->createUser(['access content'])));
    $this->assertFalse($url->access($this->createUser()));
  }

  /**
   * A short vocabulary is a plain nested list with no tools.
   */
  public function testShortVocabularyIsAPlainTree(): void {
    $washington = $this->createTerm('Washington');
    $spokane = $this->createTerm('Spokane', $washington);
    $this->createTerm('Hillyard', $spokane);
    $this->createTerm('Alaska');

    $build = $this->buildPage();
    $this->assertSame(['Alaska', 'Washington'], array_column($build['#items'], 'name'));
    $this->assertSame('Spokane', $build['#items'][1]['below'][0]['name']);
    $this->assertSame('Hillyard', $build['#items'][1]['below'][0]['below'][0]['name']);
    $this->assertSame([], $build['#groups']);
    $this->assertSame([], $build['#attached']);

    $html = $this->renderPage();
    $this->assertStringContainsString('Places on this site.', $html);
    $this->assertStringContainsString('Hillyard', $html);
    $this->assertStringNotContainsString('data-vocabulary-page', $html);
    $this->assertStringNotContainsString('Filter terms', $html);
  }

  /**
   * The tools start at exactly TOOLS_MINIMUM terms, counting nested terms.
   */
  public function testToolsStartAtTheMinimum(): void {
    $parent = $this->createTerm('Alaska');
    for ($i = 2; $i < VocabularyPageController::TOOLS_MINIMUM; $i++) {
      $this->createTerm("Term $i", $parent);
    }
    $this->assertSame([], $this->buildPage()['#groups']);

    $this->createTerm('Montana');
    $build = $this->buildPage();
    $this->assertSame(['A', 'M'], array_column($build['#groups'], 'letter'));
    $this->assertSame(['vocabulary-letter-1', 'vocabulary-letter-2'], array_column($build['#groups'], 'id'));
    $this->assertContains('mukurtu_taxonomy/vocabulary-page', $build['#attached']['library']);

    $html = $this->renderPage();
    $this->assertStringContainsString('data-vocabulary-page', $html);
    $this->assertStringContainsString('href="#vocabulary-letter-2"', $html);
  }

  /**
   * Names are translated and ordered in the current language.
   */
  public function testNamesAreTranslated(): void {
    $this->createTerm('Apple')->addTranslation('es', ['name' => 'Zumo'])->save();
    $this->createTerm('Banana')->addTranslation('es', ['name' => 'Banano'])->save();

    $this->config('system.site')->set('default_langcode', 'es')->save();
    $this->container->get('language.default')->set(ConfigurableLanguage::load('es'));
    $this->container->get('language_manager')->reset();

    $this->assertSame(['Banano', 'Zumo'], array_column($this->buildPage()['#items'], 'name'));
  }

  /**
   * Unpublished terms are left out for people who can't see them.
   */
  public function testUnpublishedTermsAreHidden(): void {
    $this->createTerm('Visible');
    $hidden = $this->createTerm('Hidden', NULL, ['status' => FALSE]);
    $this->createTerm('Nested under hidden', $hidden);

    $build = $this->buildPage();
    $this->assertSame(['Visible'], array_column($build['#items'], 'name'));
    $this->assertStringNotContainsString('hidden', strtolower($this->renderPage()));
  }

  /**
   * An empty vocabulary says so.
   */
  public function testEmptyVocabulary(): void {
    $this->assertStringContainsString('This vocabulary has no terms yet.', $this->renderPage());
  }

  /**
   * The page updates when terms or the vocabulary change.
   */
  public function testCacheMetadata(): void {
    $cache = $this->buildPage()['#cache'];
    $this->assertContains('taxonomy_term_list:location', $cache['tags']);
    $this->assertContains('config:taxonomy.vocabulary.location', $cache['tags']);
    $this->assertContains('languages:language_content', $cache['contexts']);
    $this->assertContains('user.permissions', $cache['contexts']);
  }

}
