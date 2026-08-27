<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_core\Hook\NotYetTranslatedIndicatorHooks;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests NotYetTranslatedIndicatorHooks::preprocessNode().
 *
 * @see \Drupal\mukurtu_core\Hook\NotYetTranslatedIndicatorHooks
 * @group mukurtu_core
 */
class NotYetTranslatedIndicatorTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installConfig(['node']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    // 'en' isn't a real ConfigurableLanguage entity until explicitly
    // created - without it isMultilingual() returns FALSE and the hook
    // would always no-op, same gotcha documented in ProtocolLabelTranslationTest.
    ConfigurableLanguage::createFromLangcode('en')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
  }

  /**
   * Injects the active content language directly, matching
   * ProtocolLabelTranslationTest's approach - there's no real HTTP request
   * to negotiate from in a kernel test, and ConfigurableLanguageManager
   * exposes no public setter for it.
   */
  private function setActiveContentLanguage(string $langcode): void {
    $language_manager = \Drupal::languageManager();
    $property = new \ReflectionProperty($language_manager, 'negotiatedLanguages');
    $property->setAccessible(TRUE);
    $property->setValue($language_manager, [LanguageInterface::TYPE_CONTENT => new Language(['id' => $langcode])]);
  }

  /**
   * Calls the hook the same way Drupal's theme layer does - $variables
   * carries the node at ['elements']['#node'], matching
   * NodeThemeHooks::preprocessNode()'s own source of $variables['node'].
   */
  private function preprocess(Node $node): array {
    $variables = ['elements' => ['#node' => $node], 'title_suffix' => []];
    NotYetTranslatedIndicatorHooks::create(\Drupal::getContainer())->preprocessNode($variables);
    return $variables;
  }

  /**
   * The indicator appears in title_suffix when the active content
   * language has no translation, and reports the language actually shown.
   */
  public function testIndicatorShownWhenNoTranslationExists(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Original title', 'langcode' => 'en']);
    $node->save();

    $this->setActiveContentLanguage('es');

    $variables = $this->preprocess($node);

    $this->assertArrayHasKey('mukurtu_not_yet_translated', $variables['title_suffix']);
    $indicator = $variables['title_suffix']['mukurtu_not_yet_translated'];
    $this->assertSame('mukurtu_not_yet_translated_indicator', $indicator['#theme']);
    $this->assertSame('English', $indicator['#language_name']);
    $this->assertContains('languages:language_content', $indicator['#cache']['contexts']);
  }

  /**
   * The indicator does not appear once a translation exists for the
   * active content language.
   */
  public function testIndicatorNotShownWhenTranslationExists(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Original title', 'langcode' => 'en']);
    $node->save();
    $node->addTranslation('es', ['title' => 'Título traducido'])->save();

    $this->setActiveContentLanguage('es');

    $variables = $this->preprocess($node);

    $this->assertArrayNotHasKey('mukurtu_not_yet_translated', $variables['title_suffix']);
  }

  /**
   * The indicator does not appear when the active content language
   * matches the content's own original language - nothing missing.
   */
  public function testIndicatorNotShownWhenActiveLanguageMatchesOriginal(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Original title', 'langcode' => 'en']);
    $node->save();

    $this->setActiveContentLanguage('en');

    $variables = $this->preprocess($node);

    $this->assertArrayNotHasKey('mukurtu_not_yet_translated', $variables['title_suffix']);
  }

  /**
   * On a single-language site (only 'en' configured), the hook always
   * no-ops - there's no fallback concept to flag.
   */
  public function testIndicatorNotShownOnSingleLanguageSite(): void {
    // Remove the 'es' language configured in setUp() so only 'en' remains.
    ConfigurableLanguage::load('es')->delete();
    $this->assertFalse(\Drupal::languageManager()->isMultilingual());

    $node = Node::create(['type' => 'article', 'title' => 'Original title', 'langcode' => 'en']);
    $node->save();

    $variables = $this->preprocess($node);

    $this->assertArrayNotHasKey('mukurtu_not_yet_translated', $variables['title_suffix']);
  }

}
