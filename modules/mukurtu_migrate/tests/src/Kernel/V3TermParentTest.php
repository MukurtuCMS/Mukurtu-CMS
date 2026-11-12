<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the v3 term migrations keep each term's parent (#2525).
 *
 * Runs the shipped parent mapping, with the real flat_taxonomy module, on terms
 * shaped as the d7_taxonomy_term source returns them.
 *
 * @see \Drupal\mukurtu_migrate\Plugin\migrate\process\V3TermParent
 * @see mukurtu_migrate_update_40210()
 */
#[Group('mukurtu_migrate')]
class V3TermParentTest extends MigrateTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'taxonomy',
    'flat_taxonomy',
    'migrate',
    'migrate_drupal',
    'migrate_plus',
    'search_api',
    'mukurtu_migrate',
  ];

  /**
   * {@inheritdoc}
   *
   * The flat_taxonomy module's own schema file is never discovered, and
   * mukurtu_core, which supplies it, isn't enabled here.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    Vocabulary::create([
      'vid' => 'category',
      'name' => 'Category',
      'third_party_settings' => ['flat_taxonomy' => ['flat' => TRUE]],
    ])->save();
  }

  /**
   * Gets a term migration's shipped config.
   */
  protected function shippedMigration(string $name): array {
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    return Yaml::decode(file_get_contents("$path/config/install/migrate_plus.migration.$name.yml"));
  }

  /**
   * Runs the shipped category parent mapping on some source terms.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface
   *   The migration that ran.
   */
  protected function migrate(array $rows) {
    $shipped = $this->shippedMigration('mukurtu_cms_v3_terms_category');
    $migration = $this->container->get('plugin.manager.migration')->createStubMigration([
      'id' => 'v3_terms_test',
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => $rows,
        'ids' => ['tid' => ['type' => 'integer']],
      ],
      'process' => array_intersect_key($shipped['process'], array_flip(['tid', 'vid', 'name', 'parent'])),
      'destination' => $shipped['destination'],
    ]);
    $this->executeMigration($migration);
    return $migration;
  }

  /**
   * Gets a term's parent ID, fresh from storage.
   */
  protected function parentOf(int $tid): int {
    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $storage->resetCache();
    return (int) $storage->load($tid)->get('parent')->target_id;
  }

  /**
   * Gets the text of a migration's messages.
   */
  protected function messages($migration): array {
    return array_map(fn ($message) => $message->message, iterator_to_array($migration->getIdMap()->getMessages()));
  }

  /**
   * A nested vocabulary keeps its structure, whatever order terms arrive in.
   */
  public function testNestedVocabularyKeepsItsStructure(): void {
    $migration = $this->migrate([
      ['tid' => 1, 'name' => 'Hillyard', 'parent' => [3]],
      ['tid' => 2, 'name' => 'Washington', 'parent' => [0]],
      ['tid' => 3, 'name' => 'Spokane', 'parent' => [2]],
    ]);

    $this->assertSame(3, $this->parentOf(1));
    $this->assertSame(2, $this->parentOf(3));
    $this->assertSame(0, $this->parentOf(2));

    $tree = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadTree('category');
    $this->assertSame(['Washington' => 0, 'Spokane' => 1, 'Hillyard' => 2], array_column(array_map(fn ($term) => (array) $term, $tree), 'depth', 'name'));

    $this->assertEmpty(Vocabulary::load('category')->getThirdPartySetting('flat_taxonomy', 'flat'));
    $this->assertSame(['Turned on nested terms for the Category vocabulary, because the Mukurtu CMS v3 terms in it are nested.'], $this->messages($migration));
  }

  /**
   * A flat vocabulary stays flat.
   */
  public function testFlatVocabularyStaysFlat(): void {
    $migration = $this->migrate([
      ['tid' => 1, 'name' => 'Baskets', 'parent' => [0]],
      ['tid' => 2, 'name' => 'Weaving', 'parent' => [0]],
    ]);

    $this->assertSame(0, $this->parentOf(1));
    $this->assertSame(0, $this->parentOf(2));
    $this->assertNotEmpty(Vocabulary::load('category')->getThirdPartySetting('flat_taxonomy', 'flat'));
    $this->assertSame([], $this->messages($migration));
  }

  /**
   * A term with several parents keeps the first and logs the rest.
   */
  public function testSeveralParentsKeepTheFirst(): void {
    $migration = $this->migrate([
      ['tid' => 1, 'name' => 'Washington', 'parent' => [0]],
      ['tid' => 2, 'name' => 'Oregon', 'parent' => [0]],
      ['tid' => 3, 'name' => 'Idaho', 'parent' => [0]],
      ['tid' => 4, 'name' => 'Spokane', 'parent' => [1, 2, 3]],
    ]);

    $this->assertSame(1, $this->parentOf(4));
    $this->assertContains('The term "Spokane" has more than one parent in Mukurtu CMS v3. It was kept under term 1, and these parents were left out: 2, 3.', $this->messages($migration));
  }

  /**
   * Every shipped term migration uses the mapping, after the vocabulary.
   */
  public function testEveryShippedMigrationKeepsParents(): void {
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    $files = glob("$path/config/install/migrate_plus.migration.mukurtu_cms_v3_terms_*.yml");
    $this->assertCount(13, $files);
    foreach ($files as $file) {
      $process = Yaml::decode(file_get_contents($file))['process'];
      $this->assertSame(['plugin' => 'mukurtu_v3_term_parent', 'source' => 'parent'], $process['parent'], $file);
      $this->assertArrayNotHasKey('parent_id', $process, $file);
      $keys = array_keys($process);
      $this->assertLessThan(array_search('parent', $keys, TRUE), array_search('vid', $keys, TRUE), $file);
    }
  }

  /**
   * The update replaces the old lookup and leaves customized mappings alone.
   */
  public function testUpdateHook(): void {
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_migrate');
    require_once \Drupal::root() . '/' . $path . '/mukurtu_migrate.install';

    $old = [
      'tid' => 'tid',
      'parent_id' => [
        ['plugin' => 'skip_on_empty', 'method' => 'process', 'source' => 'parent'],
        ['plugin' => 'migration_lookup', 'migration' => 'd7_taxonomy_term'],
      ],
      'parent' => ['plugin' => 'default_value', 'default_value' => 0, 'source' => '@parent_id'],
    ];
    $custom = ['tid' => 'tid', 'parent' => 'my_parent'];
    $factory = $this->container->get('config.factory');
    $factory->getEditable('migrate_plus.migration.mukurtu_cms_v3_terms_category')->set('process', $old)->save();
    $factory->getEditable('migrate_plus.migration.mukurtu_cms_v3_terms_keywords')->set('process', $custom)->save();

    $this->assertNotNull(mukurtu_migrate_update_40210());

    $this->assertSame(
      ['tid' => 'tid', 'parent' => ['plugin' => 'mukurtu_v3_term_parent', 'source' => 'parent']],
      $factory->get('migrate_plus.migration.mukurtu_cms_v3_terms_category')->get('process'),
    );
    $this->assertSame($custom, $factory->get('migrate_plus.migration.mukurtu_cms_v3_terms_keywords')->get('process'));
    $this->assertNull(mukurtu_migrate_update_40210());
  }

}
