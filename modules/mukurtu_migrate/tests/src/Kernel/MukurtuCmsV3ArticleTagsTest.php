<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Mukurtu 3 article migration's Tags mapping and its update hook.
 *
 * Mukurtu 3 article categories and tags are migrated into the Category and
 * Keywords vocabularies first, so the article migration looks each one up,
 * then files it under an Article Tags term of the same name.
 *
 * @see mukurtu_migrate_update_40212()
 */
#[Group('mukurtu_migrate')]
class MukurtuCmsV3ArticleTagsTest extends MukurtuCmsV3UsersMigrationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'migrate',
    'migrate_drupal',
    'migrate_plus',
    'search_api',
    'mukurtu_migrate',
    'node',
    'filter',
    'text',
    'taxonomy',
  ];

  /**
   * The config name of the article migration.
   */
  const CONFIG = 'migrate_plus.migration.mukurtu_cms_v3_article';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node']);

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_migrate.install';

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    foreach (['category', 'keywords', 'article_tags'] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }
    FieldStorageConfig::create([
      'field_name' => 'field_article_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_article_tags',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();
  }

  /**
   * Reads the shipped article migration.
   */
  protected function shippedArticleMigration(): array {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    return Yaml::decode(file_get_contents($module_path . '/config/install/migrate_plus.migration.mukurtu_cms_v3_article.yml'));
  }

  /**
   * Records that a Mukurtu 3 term was migrated to the given term.
   */
  protected function mapTerm(string $migration_id, int $source_tid, Term $term): void {
    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
    $migration->getIdMap()->saveIdMapping(new Row(['tid' => $source_tid], ['tid' => ['type' => 'integer']]), [$term->id()]);
  }

  /**
   * Categories and tags become Article Tags, one per name.
   */
  public function testCategoriesAndTagsBecomeArticleTags(): void {
    $this->installMigrationConfig('mukurtu_cms_v3_terms_category');
    $this->installMigrationConfig('mukurtu_cms_v3_terms_keywords');
    $news = Term::create(['vid' => 'category', 'name' => 'News']);
    $news->save();
    $news_tag = Term::create(['vid' => 'keywords', 'name' => 'news']);
    $news_tag->save();
    $launch = Term::create(['vid' => 'keywords', 'name' => 'launch']);
    $launch->save();
    $this->mapTerm('mukurtu_cms_v3_terms_category', 10, $news);
    $this->mapTerm('mukurtu_cms_v3_terms_keywords', 20, $news_tag);
    $this->mapTerm('mukurtu_cms_v3_terms_keywords', 21, $launch);

    // The shipped Tags pipeline, fed from embedded rows instead of a
    // Mukurtu 3 database.
    $shipped = $this->shippedArticleMigration();
    $definition = [
      'id' => 'article_tags_pipeline',
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [
          [
            'nid' => 1,
            'title' => 'Website Now Live!',
            'field_category' => [['tid' => 10]],
            'field_tags' => [['tid' => 20], ['tid' => 21], ['tid' => 99]],
          ],
          ['nid' => 2, 'title' => 'No terms', 'field_category' => [], 'field_tags' => []],
        ],
        'ids' => ['nid' => ['type' => 'integer']],
      ],
      'process' => [
        'title' => 'title',
        '_article_category_tags' => $shipped['process']['_article_category_tags'],
        '_article_keyword_tags' => $shipped['process']['_article_keyword_tags'],
        'field_article_tags' => $shipped['process']['field_article_tags'],
      ],
      'destination' => $shipped['destination'],
    ];
    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($definition);
    (new MigrateExecutable($migration))->import();

    $nodes = Node::loadMultiple();
    $this->assertCount(2, $nodes);
    $by_title = [];
    foreach ($nodes as $node) {
      $names = array_map(fn ($term) => $term->getName(), $node->get('field_article_tags')->referencedEntities());
      sort($names);
      $by_title[$node->label()] = $names;
    }
    // "News" and "news" are one tag, and the unmapped term 99 is skipped.
    $this->assertSame(['News', 'launch'], $by_title['Website Now Live!']);
    $this->assertSame([], $by_title['No terms']);
    $this->assertCount(2, \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'article_tags']));
  }

  /**
   * The update hook swaps the shipped Category/Keywords mappings for Tags.
   */
  public function testUpdateReplacesShippedMappings(): void {
    $this->writeOldArticleMigration();

    $message = mukurtu_migrate_update_40212();

    $process = $this->config(self::CONFIG)->get('process');
    $this->assertArrayNotHasKey('field_article_category', $process);
    $this->assertArrayNotHasKey('field_article_keywords', $process);
    $shipped = $this->shippedArticleMigration()['process'];
    foreach (['_article_category_tags', '_article_keyword_tags', 'field_article_tags'] as $key) {
      $this->assertSame($shipped[$key], $process[$key]);
    }
    // Existing sites keep the Image field, so its mapping stays.
    $this->assertArrayHasKey('field_article_image', $process);
    $this->assertNotNull($message);

    // Nothing left to do on a second run.
    $this->assertNull(mukurtu_migrate_update_40212());
  }

  /**
   * A mapping a site customized is left alone.
   */
  public function testCustomizedMappingIsKept(): void {
    $this->writeOldArticleMigration(['plugin' => 'get', 'source' => 'field_category']);

    $this->assertNull(mukurtu_migrate_update_40212());

    $process = $this->config(self::CONFIG)->get('process');
    $this->assertSame(['plugin' => 'get', 'source' => 'field_category'], $process['field_article_category']);
    $this->assertArrayNotHasKey('field_article_tags', $process);
  }

  /**
   * Writes the article migration as it shipped before the update.
   *
   * @param array|null $category
   *   The field_article_category mapping, or NULL for the shipped one.
   */
  protected function writeOldArticleMigration(?array $category = NULL): void {
    $data = $this->shippedArticleMigration();
    foreach (['_article_category_tags', '_article_keyword_tags', 'field_article_tags'] as $key) {
      unset($data['process'][$key]);
    }
    $data['process']['field_article_image'] = [
      'plugin' => 'migration_lookup',
      'migration' => ['mukurtu_cms_v3_file_private', 'mukurtu_cms_v3_file'],
      'source' => 'field_image/0/fid',
    ];
    $data['process']['field_article_category'] = $category ?? [
      'plugin' => 'migration_lookup',
      'migration' => 'mukurtu_cms_v3_terms_category',
      'source' => 'field_category',
    ];
    $data['process']['field_article_keywords'] = [
      'plugin' => 'migration_lookup',
      'migration' => 'mukurtu_cms_v3_terms_keywords',
      'source' => 'field_tags',
    ];
    \Drupal::entityTypeManager()->getStorage('migration')->create($data)->save();
  }

}
