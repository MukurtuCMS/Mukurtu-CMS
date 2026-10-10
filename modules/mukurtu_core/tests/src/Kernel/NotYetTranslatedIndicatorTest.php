<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_core\Hook\NotYetTranslatedIndicatorHooks;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests NotYetTranslatedIndicatorHooks::preprocessNode().
 *
 * @see \Drupal\mukurtu_core\Hook\NotYetTranslatedIndicatorHooks
 */
#[Group('mukurtu_core')]
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
    $this->installSchema('node', ['node_access']);
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
  private function preprocess(Node $node, ?string $url = NULL): array {
    $variables = ['elements' => ['#node' => $node], 'title_suffix' => []];
    if ($url !== NULL) {
      $variables['url'] = $url;
    }
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

  /**
   * Turns on URL-prefix language negotiation.
   *
   * A link's language then shows up as a /es prefix. Without it every
   * language generates the same URL and the link tests could not tell them
   * apart.
   */
  private function enableUrlLanguagePrefixes(): void {
    $this->installConfig(['language']);
    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => '', 'es' => 'es'])
      ->save();
    $this->container->get('language_negotiator')
      ->saveConfiguration(LanguageInterface::TYPE_INTERFACE, ['language-url' => 0]);
    $this->container->get('kernel')->rebuildContainer();
  }

  /**
   * A link to an untranslated node keeps the active content language.
   *
   * Core builds {{ url }} with $node->toUrl(), which pins it to the node's
   * own language, so following a card from /es/browse used to drop the /es
   * prefix.
   */
  public function testLinkKeepsActiveLanguageWhenNoTranslationExists(): void {
    $this->enableUrlLanguagePrefixes();
    $node = Node::create(['type' => 'article', 'title' => 'Original title', 'langcode' => 'en']);
    $node->save();
    $original_url = $node->toUrl()->toString();

    $this->setActiveContentLanguage('es');
    $variables = $this->preprocess($node, $original_url);

    $expected = $node->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage('es')])->toString();
    $this->assertNotSame($original_url, $expected, 'Sanity check: URL language prefixes are not active, so this test proves nothing.');
    $this->assertSame($expected, $variables['url']);
    $this->assertStringStartsWith('/es/', parse_url($variables['url'], PHP_URL_PATH) ?? '');
  }

  /**
   * A translated node's link is left exactly as core built it.
   */
  public function testLinkUntouchedWhenTranslationExists(): void {
    $this->enableUrlLanguagePrefixes();
    $node = Node::create(['type' => 'article', 'title' => 'Original title', 'langcode' => 'en']);
    $node->save();
    $node->addTranslation('es', ['title' => 'Título traducido'])->save();

    $this->setActiveContentLanguage('es');
    $variables = $this->preprocess($node, '/core-built-url');

    $this->assertSame('/core-built-url', $variables['url']);
  }

  /**
   * Builds the hooks object as if the given node's page were being viewed.
   */
  private function hooksOnNodePage(Node $node, string $route_name = 'entity.node.canonical'): NotYetTranslatedIndicatorHooks {
    $route_match = new RouteMatch($route_name, new Route('/node/{node}'), ['node' => $node]);
    return new NotYetTranslatedIndicatorHooks(\Drupal::languageManager(), $route_match);
  }

  /**
   * An untranslated node's page title and breadcrumb carry its language.
   *
   * The page itself is in the visitor's language, so without this the
   * original-language title would be announced in the wrong language.
   */
  public function testPageTitleAndBreadcrumbMarkedWhenNoTranslationExists(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Original title', 'langcode' => 'en']);
    $node->save();
    $this->setActiveContentLanguage('es');
    $hooks = $this->hooksOnNodePage($node);

    $title = ['title_attributes' => []];
    $hooks->preprocessPageTitle($title);
    $this->assertSame('en', $title['title_attributes']['lang']);
    $this->assertSame('ltr', $title['title_attributes']['dir']);

    $breadcrumb = [];
    $hooks->preprocessBreadcrumb($breadcrumb);
    $this->assertSame(['langcode' => 'en', 'direction' => 'ltr'], $breadcrumb['current_page_language']);
  }

  /**
   * A translated node's page title and breadcrumb are left alone.
   */
  public function testPageTitleAndBreadcrumbUntouchedWhenTranslationExists(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Original title', 'langcode' => 'en']);
    $node->save();
    $node->addTranslation('es', ['title' => 'Título traducido'])->save();
    $this->setActiveContentLanguage('es');
    $hooks = $this->hooksOnNodePage($node);

    $title = ['title_attributes' => []];
    $hooks->preprocessPageTitle($title);
    $this->assertSame([], $title['title_attributes']);

    $breadcrumb = [];
    $hooks->preprocessBreadcrumb($breadcrumb);
    $this->assertArrayNotHasKey('current_page_language', $breadcrumb);
  }

  /**
   * Other routes that carry a node, such as its edit form, are left alone.
   */
  public function testPageTitleUntouchedOffTheNodePage(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Original title', 'langcode' => 'en']);
    $node->save();
    $this->setActiveContentLanguage('es');

    $title = ['title_attributes' => []];
    $this->hooksOnNodePage($node, 'entity.node.edit_form')->preprocessPageTitle($title);
    $this->assertSame([], $title['title_attributes']);
  }

}
