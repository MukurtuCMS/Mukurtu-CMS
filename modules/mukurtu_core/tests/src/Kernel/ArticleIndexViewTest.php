<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Articles page and the Latest Articles block.
 *
 * The view is installed by mukurtu_core_update_40230(), from the same
 * config/optional file a new site gets, so the hook and the shipped config
 * are tested together.
 *
 * @see mukurtu_core_update_40230()
 */
#[Group('mukurtu_core')]
class ArticleIndexViewTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'filter',
    'text',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'views']);
    $this->container->get('node.grant_storage')->writeDefault();

    // Required directly rather than via loadInclude(); see
    // CategoryAdminLinksCascadeUpdateTest::setUp() for why.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';

    // The grid_browse view mode ships in mukurtu_core's config/install.
    $record = (new FileStorage($module_path . '/config/install'))->read('core.entity_view_mode.node.grid_browse');
    \Drupal::entityTypeManager()->getStorage('entity_view_mode')->createFromStorageRecord($record)->save();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();

    // User 1 bypasses access checks.
    $this->createUser();
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * Creates a node.
   */
  protected function createNode(string $type, string $title, int $created, bool $published = TRUE): Node {
    $node = Node::create([
      'type' => $type,
      'title' => $title,
      'created' => $created,
      'status' => (int) $published,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Returns the titles a view display lists, in order.
   */
  protected function titles(string $display): array {
    $view = Views::getView('mukurtu_articles');
    $view->setDisplay($display);
    $view->execute();
    return array_map(fn ($row) => $row->_entity->label(), $view->result);
  }

  /**
   * The page lists published articles only, newest first.
   */
  public function testPageListsPublishedArticlesNewestFirst(): void {
    $this->assertSame('Added the Articles page at /articles and a Latest Articles block.', mukurtu_core_update_40230());

    $this->createNode('article', 'Oldest', 1000);
    $this->createNode('article', 'Newest', 3000);
    $this->createNode('article', 'Middle', 2000);
    $this->createNode('article', 'Draft', 4000, FALSE);
    $this->createNode('page', 'A basic page', 5000);

    $this->assertSame(['Newest', 'Middle', 'Oldest'], $this->titles('page'));

    $view = Views::getView('mukurtu_articles');
    $view->setDisplay('page');
    $this->assertSame('articles', $view->getPath());
    $this->assertSame('Articles', $view->getTitle());
    $this->assertSame('grid_browse', $view->display_handler->getOption('row')['options']['view_mode']);
  }

  /**
   * One row per article, rendered in the content language.
   *
   * See docs/content-language-policy.md.
   */
  public function testFollowsContentLanguagePolicy(): void {
    mukurtu_core_update_40230();
    $view = Views::getView('mukurtu_articles');
    foreach (['page', 'latest_articles_block'] as $display) {
      $view->setDisplay($display);
      $this->assertSame('***LANGUAGE_language_content***', $view->display_handler->getOption('rendering_language'));
      $this->assertSame('1', $view->display_handler->getOption('filters')['default_langcode']['value']);
    }
  }

  /**
   * The block shows the three newest and always links to the page.
   */
  public function testBlockShowsThreeNewestWithLink(): void {
    mukurtu_core_update_40230();
    foreach (range(1, 5) as $i) {
      $this->createNode('article', "Article $i", $i * 1000);
    }

    $this->assertSame(['Article 5', 'Article 4', 'Article 3'], $this->titles('latest_articles_block'));

    $view = Views::getView('mukurtu_articles');
    $view->setDisplay('latest_articles_block');
    $view->execute();
    $this->assertSame('Latest Articles', $view->getTitle());
    $this->assertTrue($view->display_handler->getOption('use_more_always'));
    $this->assertSame('View all articles', $view->display_handler->useMoreText());
    $more = $view->display_handler->renderMoreLink();
    $this->assertSame('/articles', $more['#url']->toString());
    // An empty block is hidden rather than showing the page's empty text.
    $this->assertTrue($view->display_handler->getOption('block_hide_empty'));
    $this->assertSame([], $view->display_handler->getOption('empty'));
  }

  /**
   * The page shows a message when there are no articles.
   */
  public function testPageEmptyText(): void {
    mukurtu_core_update_40230();
    $view = Views::getView('mukurtu_articles');
    $view->setDisplay('page');
    $empty = $view->display_handler->getOption('empty');
    $this->assertSame('No articles have been published yet.', $empty['area']['content']['value']);
  }

  /**
   * The update hook adds nothing a site already has, or without articles.
   */
  public function testUpdateHookSkipsExistingConfig(): void {
    mukurtu_core_update_40230();
    $this->assertNull(mukurtu_core_update_40230());
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('entity_view_display')->load('node.article.grid_browse'));

    NodeType::load('article')->delete();
    \Drupal::entityTypeManager()->getStorage('view')->load('mukurtu_articles')?->delete();
    $this->assertNull(mukurtu_core_update_40230());
    $this->assertNull(Views::getView('mukurtu_articles'));
  }

}
