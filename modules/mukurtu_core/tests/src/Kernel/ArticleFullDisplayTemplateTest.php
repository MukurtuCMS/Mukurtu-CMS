<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_v4's node--article--full.html.twig.
 *
 * Articles used to fall back to the bare node.html.twig: no full-node grid,
 * and every component of the shipped display printed, including the "Title"
 * extra field, node links and the export flag. The template now uses the
 * same main column + sidebar grid as Digital Heritage and Person, with
 * category and keywords in the sidebar, and leaves those three components
 * out.
 *
 * The "Title" extra field, links and flag come from modules this test does
 * not install, so they are stood in for by markup under the same render
 * keys; the template drops them by key.
 */
#[Group('mukurtu_core')]
class ArticleFullDisplayTemplateTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'filter', 'node']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
    ])->save();

    Vocabulary::create(['vid' => 'category', 'name' => 'Category'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_article_category',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_article_category',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Category',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['category' => 'category']],
      ],
    ])->save();

    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'article')
      ->setComponent('body', ['type' => 'text_default', 'label' => 'hidden'])
      ->setComponent('field_article_category', [
        'type' => 'entity_reference_label',
        'label' => 'above',
        'settings' => ['link' => FALSE],
      ])
      ->save();

    $this->setUpCurrentUser([], ['access content']);

    // mukurtu_v4 lives inside the profile, and the config install scan only
    // looks in the active profile's directories.
    $this->setInstallProfile('mukurtu');
    $this->container->get('theme_installer')->install(['mukurtu_v4']);
    $this->config('system.theme')->set('default', 'mukurtu_v4')->save();
    $this->container->get('theme.manager')->setActiveTheme(
      $this->container->get('theme.initialization')->initTheme('mukurtu_v4')
    );
  }

  /**
   * Renders an article in full view mode, with stand-in extra components.
   */
  protected function renderArticle(NodeInterface $node): void {
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('node')
      ->view($node, 'full');
    $build['field_title'] = ['#markup' => '<div class="stray-field-title">Stray title</div>'];
    $build['links'] = ['#markup' => '<div class="stray-links">Stray links</div>'];
    $build['flag_export_content'] = ['#markup' => '<div class="stray-flag">Stray flag</div>'];
    $build['comment'] = ['#markup' => '<div class="stand-in-comments">Comments</div>'];
    $this->setRawContent((string) $this->container->get('renderer')->renderInIsolation($build));
  }

  /**
   * Creates and saves an article.
   */
  protected function createArticle(array $values = []): NodeInterface {
    $node = Node::create($values + [
      'type' => 'article',
      'title' => 'Website Now Live!',
      'body' => ['value' => 'Article body text', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $node->save();
    return $node;
  }

  /**
   * The article uses the full-node grid, with category in the sidebar.
   */
  public function testArticleUsesFullNodeGrid(): void {
    $term = Term::create(['vid' => 'category', 'name' => 'Announcements']);
    $term->save();
    $this->renderArticle($this->createArticle(['field_article_category' => $term->id()]));

    $this->assertCount(1, $this->cssSelect('.full-node.full-node--article .full-node__grid .full-node__main .full-node__content'));
    $content = (string) $this->cssSelect('.full-node__content')[0]->asXML();
    $this->assertStringContainsString('Article body text', $content);
    $this->assertStringNotContainsString('Announcements', $content);

    $sidebar = $this->cssSelect('.full-node__list aside.full-node__sidebar .sidebar-section');
    $this->assertCount(1, $sidebar);
    $this->assertStringContainsString('Announcements', (string) $sidebar[0]->asXML());

    $comments = $this->cssSelect('.full-node__grid > .full-node__comment .stand-in-comments');
    $this->assertCount(1, $comments);
  }

  /**
   * The Title extra field, node links and export flag are not printed.
   */
  public function testStrayComponentsAreLeftOut(): void {
    $this->renderArticle($this->createArticle());

    $this->assertNoRaw('stray-field-title');
    $this->assertNoRaw('stray-links');
    $this->assertNoRaw('stray-flag');
  }

  /**
   * With no category or keywords, the sidebar is not rendered at all.
   *
   * An empty field still leaves cache metadata in the content array, so a
   * plain "is not empty" check would print an empty sidebar box.
   */
  public function testNoSidebarWithoutCategoryOrKeywords(): void {
    $this->renderArticle($this->createArticle());

    $this->assertCount(1, $this->cssSelect('.full-node__main'));
    $this->assertCount(0, $this->cssSelect('.full-node__list'));
  }

}
