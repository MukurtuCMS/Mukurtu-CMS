<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_browse\Kernel;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_browse_update_40019().
 *
 * update_40016()/_40017()/_40018() each call
 * installDefaultConfig('module', 'mukurtu_browse'), which overwrites
 * mukurtu_default_content_index wholesale from config/install and silently
 * strips the mukurtu_multipage_page_index / mukurtu_community_record_flag
 * processors and their is_additional_mpi_page / is_community_record fields,
 * breaking the "Show all pages" and community record browse toggles (issue
 * #2022). update_40019() re-applies both via
 * _mukurtu_browse_ensure_processor_on_index(); this test starts from an
 * index missing both (the post-40016-_40018 state) and confirms the hook
 * restores them.
 *
 * mukurtu_browse itself is not enabled here: its declared dependency chain
 * (mukurtu_dictionary, mukurtu_digital_heritage, facets, layout_builder,
 * paragraphs, blazy, geofield, leaflet) isn't needed to exercise this one
 * update hook and its helper. Both files are required directly instead,
 * mirroring BrowseQuickActionsPreprocessTest.
 *
 * @see mukurtu_browse_update_40019()
 * @see _mukurtu_browse_ensure_processor_on_index()
 */
#[Group('mukurtu_browse')]
class BrowseCollapseIndexRestoreTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_db',
    'mukurtu_multipage_items',
    'mukurtu_community_records',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');

    // A minimal DB-backed server, matching search_api_test_db's shipped
    // config, without importing that module's own index (which targets the
    // entity_test_mulrev_changed datasource we don't otherwise need here).
    Server::create([
      'id' => 'test_search_server',
      'name' => 'Test search server',
      'backend' => 'search_api_db',
      'backend_config' => [
        'database' => 'default:default',
        'min_chars' => 3,
        'matching' => 'words',
        'phrase' => 'bigram',
      ],
      'status' => TRUE,
    ])->save();

    // Recreate mukurtu_default_content_index without the MPI/CR
    // processors/fields, matching the state update_40016()-_40018() leave
    // behind. mukurtu_multipage_items_install()/mukurtu_community_records_install()
    // ran during module installation above, before this index existed, so
    // they were no-ops here too - the same order a fresh profile install
    // hits (see those hooks' own docblocks).
    Index::create([
      'id' => 'mukurtu_default_content_index',
      'name' => 'Mukurtu default content index',
      'status' => TRUE,
      'server' => 'test_search_server',
      'datasource_settings' => [
        'entity:node' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'options' => [
        'cron_limit' => -1,
        'index_directly' => FALSE,
      ],
    ])->save();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_browse');
    require_once $module_path . '/mukurtu_browse.module';
    require_once $module_path . '/mukurtu_browse.install';
  }

  /**
   * Tests that the update hook re-adds the missing processors and fields.
   */
  public function testUpdate40019RestoresMpiAndCrFields(): void {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load('mukurtu_default_content_index');
    $this->assertNull($index->getField('is_additional_mpi_page'));
    $this->assertNull($index->getField('is_community_record'));
    $this->assertArrayNotHasKey('mukurtu_multipage_page_index', $index->getProcessors());
    $this->assertArrayNotHasKey('mukurtu_community_record_flag', $index->getProcessors());

    mukurtu_browse_update_40019();

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = \Drupal::entityTypeManager()
      ->getStorage('search_api_index')
      ->loadUnchanged('mukurtu_default_content_index');

    $mpi_field = $index->getField('is_additional_mpi_page');
    $this->assertNotNull($mpi_field, 'Update hook did not restore is_additional_mpi_page.');
    $this->assertSame('boolean', $mpi_field->getType());
    $this->assertSame('entity:node', $mpi_field->getDatasourceId());

    $cr_field = $index->getField('is_community_record');
    $this->assertNotNull($cr_field, 'Update hook did not restore is_community_record.');
    $this->assertSame('boolean', $cr_field->getType());
    $this->assertSame('entity:node', $cr_field->getDatasourceId());

    $processors = $index->getProcessors();
    $this->assertArrayHasKey('mukurtu_multipage_page_index', $processors);
    $this->assertArrayHasKey('mukurtu_community_record_flag', $processors);
  }

  /**
   * Tests that running the update hook twice does not error or duplicate.
   */
  public function testUpdate40019IsIdempotent(): void {
    mukurtu_browse_update_40019();
    mukurtu_browse_update_40019();

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = \Drupal::entityTypeManager()
      ->getStorage('search_api_index')
      ->loadUnchanged('mukurtu_default_content_index');
    $this->assertNotNull($index->getField('is_additional_mpi_page'));
    $this->assertNotNull($index->getField('is_community_record'));
  }

}
