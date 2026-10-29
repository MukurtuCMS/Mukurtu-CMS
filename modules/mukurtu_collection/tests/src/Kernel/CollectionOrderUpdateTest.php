<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\mukurtu_collection\CollectionOrder;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_collection_update_40029() on a site from before #654.
 */
#[Group('mukurtu_collection')]
class CollectionOrderUpdateTest extends CollectionTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_db',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig(['search_api']);
    $this->container->get('module_handler')->loadInclude('mukurtu_collection', 'install');

    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();

    Server::create([
      'id' => 'mukurtu_database_server',
      'name' => 'Database server',
      'backend' => 'search_api_db',
      'backend_config' => ['database' => 'default:default'],
      'status' => TRUE,
    ])->save();

    Index::create([
      'id' => 'mukurtu_collection_index',
      'name' => 'Mukurtu Collection Index',
      'status' => TRUE,
      'server' => 'mukurtu_database_server',
      'datasource_settings' => [
        'entity:node' => ['bundles' => ['default' => FALSE, 'selected' => ['collection']]],
      ],
      'tracker_settings' => ['default' => []],
      'field_settings' => [
        'title' => [
          'label' => 'Title',
          'datasource_id' => 'entity:node',
          'property_path' => 'title',
          'type' => 'text',
        ],
      ],
    ])->save();

    // The sorts the view shipped with before this change.
    $sort = [
      'table' => 'search_api_index_mukurtu_collection_index',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'search_api',
      'order' => 'ASC',
      'exposed' => FALSE,
    ];
    $this->config('views.view.mukurtu_browse_collections')
      ->setData([
        'id' => 'mukurtu_browse_collections',
        'label' => 'Browse Collections',
        'base_table' => 'search_api_index_mukurtu_collection_index',
        'display' => [
          'default' => [
            'id' => 'default',
            'display_plugin' => 'default',
            'display_options' => [
              'sorts' => [
                'title' => ['id' => 'title', 'field' => 'title'] + $sort,
                'parent_collection_id' => ['id' => 'parent_collection_id', 'field' => 'parent_collection_id'] + $sort,
              ],
            ],
          ],
        ],
      ])
      ->save();
  }

  /**
   * The update adds the settings, index field, view sort, and permission.
   */
  public function testUpdate(): void {
    $this->assertTrue($this->config(CollectionOrder::CONFIG_NAME)->isNew());

    mukurtu_collection_update_40029();
    // Running it again changes nothing.
    mukurtu_collection_update_40029();

    $settings = $this->config(CollectionOrder::CONFIG_NAME);
    $this->assertSame(CollectionOrder::MODE_ALPHABETICAL, $settings->get('sort_mode'));
    $this->assertSame([], $settings->get('order'));

    $index = Index::load('mukurtu_collection_index');
    $this->assertTrue($index->isValidProcessor('mukurtu_collection_display_weight'));
    $field = $index->getField(CollectionOrder::FIELD_NAME);
    $this->assertNotNull($field);
    $this->assertSame('integer', $field->getType());
    $this->assertSame('entity:node', $field->getDatasourceId());

    $sorts = $this->config('views.view.mukurtu_browse_collections')->get('display.default.display_options.sorts');
    $this->assertSame([CollectionOrder::FIELD_NAME, 'title', 'parent_collection_id'], array_keys($sorts));
    $this->assertSame('ASC', $sorts[CollectionOrder::FIELD_NAME]['order']);
    $this->assertSame('search_api_index_mukurtu_collection_index', $sorts[CollectionOrder::FIELD_NAME]['table']);

    $this->assertTrue(Role::load('mukurtu_manager')->hasPermission('administer collection order'));
  }

  /**
   * The update keeps a custom order a site already saved.
   */
  public function testUpdateKeepsExistingSettings(): void {
    $this->config(CollectionOrder::CONFIG_NAME)
      ->set('sort_mode', CollectionOrder::MODE_CUSTOM)
      ->set('order', [5, 3])
      ->save();

    mukurtu_collection_update_40029();

    $settings = $this->config(CollectionOrder::CONFIG_NAME);
    $this->assertSame(CollectionOrder::MODE_CUSTOM, $settings->get('sort_mode'));
    $this->assertSame([5, 3], $settings->get('order'));
  }

}
