<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_v4's article page and card templates.
 *
 * Articles used to fall back to the bare node.html.twig: no full-node grid,
 * and every component of the shipped display printed, including the "Title"
 * extra field, node links and the export flag. The template now uses the
 * same main column + sidebar grid as Digital Heritage and Person, with tags
 * in the sidebar, and leaves those three components out.
 *
 * The "Title" extra field, links and flag come from modules this test does
 * not install, so they are stood in for by markup under the same render
 * keys; the template drops them by key.
 */
#[Group('mukurtu_core')]
class ArticleFullDisplayTemplateTest extends KernelTestBase {

  use MediaTypeCreationTrait;
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
    'file',
    'image',
    'media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'image', 'media']);

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

    Vocabulary::create(['vid' => 'article_tags', 'name' => 'Article Tags'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_article_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_article_tags',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Article Tags',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['article_tags' => 'article_tags']],
      ],
    ])->save();

    // Media Assets, as basic pages have, on both bundles.
    $this->createMediaType('file', ['id' => 'document']);
    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_page_media_assets',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'media'],
    ])->save();
    foreach (['article', 'page'] as $bundle) {
      FieldConfig::create([
        'field_name' => 'field_page_media_assets',
        'entity_type' => 'node',
        'bundle' => $bundle,
      ])->save();
      \Drupal::service('entity_display.repository')->getViewDisplay('node', $bundle)
        ->setComponent('field_page_media_assets', [
          'type' => 'entity_reference_entity_view',
          'label' => 'hidden',
        ])
        ->save();
    }

    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'article')
      ->setComponent('body', ['type' => 'text_default', 'label' => 'hidden'])
      ->setComponent('field_article_tags', [
        'type' => 'entity_reference_label',
        'label' => 'above',
        'settings' => ['link' => FALSE],
      ])
      ->save();

    $this->setUpCurrentUser([], ['access content', 'view media']);

    // mukurtu_v4 lives inside the profile, and the config install scan only
    // looks in the active profile's directories.
    $this->setInstallProfile('mukurtu');
    $this->container->get('theme_installer')->install(['mukurtu_v4']);
    $this->config('system.theme')->set('default', 'mukurtu_v4')->save();
    $this->container->get('theme.manager')->setActiveTheme(
      $this->container->get('theme.initialization')->initTheme('mukurtu_v4')
    );

    // The card view mode ships in mukurtu_core's config/install. Read after
    // setInstallProfile(): looking up a module path before it caches a module
    // list without the profile, and the theme install then warns that the
    // "mukurtu" module is missing.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $record = (new FileStorage($module_path . '/config/install'))->read('core.entity_view_mode.node.grid_browse');
    \Drupal::entityTypeManager()->getStorage('entity_view_mode')->createFromStorageRecord($record)->save();
  }

  /**
   * Renders a node in full view mode; articles get stand-in extra components.
   */
  protected function renderNode(NodeInterface $node): array {
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('node')
      ->view($node, 'full');
    if ($node->bundle() === 'article') {
      $build['field_title'] = ['#markup' => '<div class="stray-field-title">Stray title</div>'];
      $build['links'] = ['#markup' => '<div class="stray-links">Stray links</div>'];
      $build['flag_export_content'] = ['#markup' => '<div class="stray-flag">Stray flag</div>'];
      $build['comment'] = ['#markup' => '<div class="stand-in-comments">Comments</div>'];
    }
    $this->setRawContent((string) $this->container->get('renderer')->renderInIsolation($build));
    return $build;
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
   * The article uses the full-node grid, with tags in the sidebar.
   */
  public function testArticleUsesFullNodeGrid(): void {
    $term = Term::create(['vid' => 'article_tags', 'name' => 'Announcements']);
    $term->save();
    $this->renderNode($this->createArticle(['field_article_tags' => $term->id()]));

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
    $this->renderNode($this->createArticle());

    $this->assertNoRaw('stray-field-title');
    $this->assertNoRaw('stray-links');
    $this->assertNoRaw('stray-flag');
  }

  /**
   * The sidebar ends with the published date, even with no tags.
   */
  public function testPublishedDateEndsSidebar(): void {
    $created = mktime(12, 0, 0, 11, 13, 2025);
    $build = $this->renderNode($this->createArticle(['created' => $created]));

    $section = $this->cssSelect('.full-node__sidebar .sidebar-section');
    $this->assertCount(1, $section);
    $this->assertCount(0, $this->cssSelect('.field--name-field-article-tags'));
    $fields = $this->cssSelect('.full-node__sidebar .sidebar-section > .field');
    $last = end($fields);
    $this->assertSame('Published', trim((string) $last->div[0]));
    $time = $this->cssSelect('.full-node__sidebar .field--name-created time');
    $this->assertCount(1, $time);
    $this->assertSame('November 13, 2025', trim((string) $time[0]));
    $this->assertSame(gmdate('Y-m-d\\TH:i:s', $created) . '+0000', (string) $time[0]['datetime']);
    // The text follows the viewer's timezone, so the cache must vary by it.
    $this->assertContains('timezone', $build['#cache']['contexts']);
  }

  /**
   * Article media assets don't get the Expand lightbox; basic pages do.
   */
  public function testArticleMediaHasNoLightbox(): void {
    $media = Media::create(['bundle' => 'document', 'name' => 'Sample document']);
    $media->save();

    $build = $this->renderNode($this->createArticle(['field_page_media_assets' => $media->id()]));
    $this->assertCount(1, $this->cssSelect('.full-node__content .field-item__container'));
    $this->assertNotContains('mukurtu_v4/media-glightbox', $build['#attached']['library'] ?? []);

    $page = Node::create([
      'type' => 'page',
      'title' => 'A basic page',
      'field_page_media_assets' => $media->id(),
      'status' => 1,
    ]);
    $page->save();
    $build = $this->renderNode($page);
    $this->assertContains('mukurtu_v4/media-glightbox', $build['#attached']['library'] ?? []);
  }

  /**
   * Renders an article as a card, as the Articles page and block do.
   */
  protected function renderCard(NodeInterface $node): void {
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('node')
      ->view($node, 'grid_browse');
    $this->setRawContent((string) $this->container->get('renderer')->renderInIsolation($build));
  }

  /**
   * A card shows the title link and the published date.
   */
  public function testCardShowsTitleAndDate(): void {
    $node = $this->createArticle(['created' => mktime(12, 0, 0, 11, 13, 2025)]);
    $this->renderCard($node);

    $link = $this->cssSelect('article.vertical-card--article h2 a');
    $this->assertCount(1, $link);
    $this->assertSame('Website Now Live!', trim(strip_tags($link[0]->asXML())));
    $this->assertSame($node->toUrl()->toString(), (string) $link[0]['href']);
    $time = $this->cssSelect('.vertical-card__date time');
    $this->assertCount(1, $time);
    $this->assertSame('November 13, 2025', trim((string) $time[0]));
  }

  /**
   * With no media, a card shows the site's placeholder image.
   */
  public function testCardWithoutMediaShowsPlaceholder(): void {
    $this->renderCard($this->createArticle());

    $img = $this->cssSelect('.vertical-card__media img');
    $this->assertCount(1, $img);
    $this->assertStringContainsString('no-thumbnail.png', (string) $img[0]['src']);
  }

  /**
   * A card shows the first media asset, not the rest.
   */
  public function testCardShowsFirstMediaAsset(): void {
    $first = Media::create(['bundle' => 'document', 'name' => 'First document']);
    $first->save();
    $second = Media::create(['bundle' => 'document', 'name' => 'Second document']);
    $second->save();
    $this->renderCard($this->createArticle(['field_page_media_assets' => [$first->id(), $second->id()]]));

    $media = (string) $this->cssSelect('.vertical-card__media')[0]->asXML();
    $this->assertStringContainsString('media--document', $media);
    $this->assertStringNotContainsString('no-thumbnail.png', $media);
    $this->assertCount(1, $this->cssSelect('.vertical-card__media .media--document'));
  }

}
