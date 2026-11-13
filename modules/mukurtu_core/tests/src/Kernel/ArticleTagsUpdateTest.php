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
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40229(): article Category/Keywords become Tags.
 *
 * @see mukurtu_core_update_40229()
 */
#[Group('mukurtu_core')]
class ArticleTagsUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'file',
    'image',
    'media',
    'media_library',
    'views',
    'filter',
    'text',
    'taxonomy',
    'flat_taxonomy',
    'tagify',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'media', 'media_library']);

    // Required directly rather than via loadInclude(); see
    // CategoryAdminLinksCascadeUpdateTest::setUp() for why.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    Vocabulary::create(['vid' => 'category', 'name' => 'Category'])->save();
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    foreach (['field_article_category' => 'category', 'field_article_keywords' => 'keywords'] as $field_name => $vid) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => -1,
        'settings' => ['target_type' => 'taxonomy_term'],
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => 'article',
        'settings' => [
          'handler' => 'default:taxonomy_term',
          'handler_settings' => ['target_bundles' => [$vid => $vid]],
        ],
      ])->save();
    }

    // Media Assets storage ships with basic pages.
    foreach (['image', 'document'] as $media_type) {
      $this->container->get('entity_type.manager')->getStorage('media_type')->create([
        'id' => $media_type,
        'label' => ucfirst($media_type),
        'source' => 'file',
      ])->save();
    }
    FieldStorageConfig::create([
      'field_name' => 'field_page_media_assets',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'media'],
    ])->save();

    // The form as it shipped before: Category and Keywords ahead of Body.
    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'article')
      ->setComponent('title', ['type' => 'string_textfield', 'weight' => 0])
      ->setComponent('field_article_category', ['type' => 'options_buttons', 'weight' => 1])
      ->setComponent('field_article_keywords', ['type' => 'entity_reference_autocomplete', 'weight' => 2])
      ->setComponent('uid', ['type' => 'entity_reference_autocomplete', 'weight' => 2])
      ->setComponent('status', ['type' => 'boolean_checkbox', 'weight' => 5])
      ->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'article')
      ->setComponent('field_article_category', ['type' => 'entity_reference_label'])
      ->setComponent('field_article_keywords', ['type' => 'entity_reference_label'])
      ->save();

    Role::create(['id' => 'mukurtu_manager', 'label' => 'Manager'])->save();
  }

  /**
   * Runs the update hook to completion, as update.php would.
   */
  protected function runUpdate(): ?string {
    $sandbox = [];
    do {
      $message = mukurtu_core_update_40229($sandbox);
    } while (($sandbox['#finished'] ?? 1) < 1);
    return $message;
  }

  /**
   * Returns the names of an article's tags.
   */
  protected function tagNames(NodeInterface $node): array {
    $node = Node::load($node->id());
    $names = array_map(fn ($term) => $term->getName(), $node->get('field_article_tags')->referencedEntities());
    sort($names);
    return $names;
  }

  /**
   * Category and Keywords terms become Article Tags, matched by name.
   */
  public function testTermsAreCopiedIntoTags(): void {
    $news = Term::create(['vid' => 'category', 'name' => 'News']);
    $news->save();
    $news_keyword = Term::create(['vid' => 'keywords', 'name' => 'news']);
    $news_keyword->save();
    $launch = Term::create(['vid' => 'keywords', 'name' => 'launch']);
    $launch->save();

    $both = Node::create([
      'type' => 'article',
      'title' => 'Both',
      'field_article_category' => [$news->id()],
      'field_article_keywords' => [$news_keyword->id(), $launch->id()],
      'changed' => 1000000000,
    ]);
    $both->save();
    $keywords_only = Node::create([
      'type' => 'article',
      'title' => 'Keywords only',
      'field_article_keywords' => [$launch->id()],
    ]);
    $keywords_only->save();
    $neither = Node::create(['type' => 'article', 'title' => 'Neither']);
    $neither->save();
    $changed = Node::load($both->id())->getChangedTime();

    $message = $this->runUpdate();

    // "News" and "news" are one tag; the first spelling seen wins.
    $this->assertSame(['News', 'launch'], $this->tagNames($both));
    $this->assertSame(['launch'], $this->tagNames($keywords_only));
    $this->assertSame([], $this->tagNames($neither));
    $tags = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'article_tags']);
    $this->assertCount(2, $tags);

    // The original terms stay where they were; other content uses them.
    $this->assertNotNull(Term::load($news->id()));
    $this->assertSame('category', Term::load($news->id())->bundle());

    $this->assertSame($changed, Node::load($both->id())->getChangedTime());
    $this->assertNull(FieldConfig::loadByName('node', 'article', 'field_article_category'));
    $this->assertNull(FieldConfig::loadByName('node', 'article', 'field_article_keywords'));
    $this->assertStringContainsString('2 articles', (string) $message);
  }

  /**
   * A tag with the same name a site already has is reused.
   */
  public function testExistingArticleTagIsReused(): void {
    $this->runUpdate();
    $existing = Term::create(['vid' => 'article_tags', 'name' => 'Launch']);
    $existing->save();

    // Put the old field back, as if a second article still had a value.
    FieldStorageConfig::create([
      'field_name' => 'field_article_keywords',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_article_keywords',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();
    $launch = Term::create(['vid' => 'keywords', 'name' => 'launch']);
    $launch->save();
    $node = Node::create([
      'type' => 'article',
      'title' => 'Later',
      'field_article_keywords' => [$launch->id()],
    ]);
    $node->save();

    $this->runUpdate();

    $node = Node::load($node->id());
    $this->assertSame([(int) $existing->id()], array_map('intval', array_column($node->get('field_article_tags')->getValue(), 'target_id')));
  }

  /**
   * The vocabulary, fields, form order and permissions are added.
   */
  public function testConfigIsAdded(): void {
    $this->runUpdate();

    $this->assertNotNull(Vocabulary::load('article_tags'));
    $this->assertNotNull(FieldConfig::loadByName('node', 'article', 'field_article_tags'));
    $this->assertNotNull(FieldConfig::loadByName('node', 'article', 'field_page_media_assets'));

    $form = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'article');
    $weights = array_map(fn ($component) => $component['weight'], $form->getComponents());
    $this->assertSame(0, $weights['title']);
    $this->assertSame(2, $weights['field_page_media_assets']);
    $this->assertSame(3, $weights['field_article_tags']);
    $this->assertGreaterThan(3, $weights['uid']);
    $this->assertNull($form->getComponent('field_article_category'));

    $view = \Drupal::service('entity_display.repository')->getViewDisplay('node', 'article');
    $this->assertSame('media_assets', $view->getComponent('field_page_media_assets')['settings']['view_mode']);
    $this->assertNotNull($view->getComponent('field_article_tags'));
    $this->assertNull($view->getComponent('field_article_keywords'));

    $manager = Role::load('mukurtu_manager');
    foreach (['create', 'delete', 'edit'] as $operation) {
      $this->assertTrue($manager->hasPermission("$operation terms in article_tags"));
    }
  }

  /**
   * Running the update again changes nothing.
   */
  public function testSecondRunIsNoOp(): void {
    $this->runUpdate();
    $form = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'article')->toArray();

    $this->runUpdate();

    $this->assertSame($form['content'], \Drupal::service('entity_display.repository')->getFormDisplay('node', 'article')->toArray()['content']);
  }

  /**
   * Export columns swap Category and Keywords for Media Assets and Tags.
   */
  public function testExportListIsUpdated(): void {
    $list = _mukurtu_core_update_40229_export_list([
      'node__article' => [
        'title' => 'Title',
        'body' => 'Body',
        'field_article_category' => 'Category',
        'field_article_image/target_id' => 'Image > File ID',
        'field_article_keywords' => 'Keywords',
        'uuid' => 'UUID',
      ],
      'taxonomy_term__keywords' => ['name' => 'Name', 'uuid' => 'UUID'],
    ]);

    $this->assertSame([
      'title' => 'Title',
      'body' => 'Body',
      'field_page_media_assets' => 'Media Assets',
      'field_article_tags' => 'Tags',
      'field_article_image/target_id' => 'Image > File ID',
      'uuid' => 'UUID',
    ], $list['node__article']);
    $this->assertSame(['name' => 'Name', 'uuid' => 'UUID'], $list['taxonomy_term__article_tags']);

    // A second pass leaves it as it is.
    $this->assertSame($list, _mukurtu_core_update_40229_export_list($list));
  }

}
