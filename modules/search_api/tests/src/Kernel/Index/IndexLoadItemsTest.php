<?php

namespace Drupal\Tests\search_api\Kernel\Index;

use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_test\PluginTestTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests whether loading items works correctly.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class IndexLoadItemsTest extends KernelTestBase {

  use PluginTestTrait;

  /**
   * The test index object.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('search_api_task');

    $server = Server::create([
      'id' => 'test',
      'backend' => 'search_api_test',
    ]);
    $this->index = Index::create([
      'tracker_settings' => [
        'search_api_test' => [],
      ],
      'datasource_settings' => [
        'search_api_test' => [],
      ],
    ]);
    $this->index->setServer($server);
  }

  /**
   * Verifies that missing items are correctly detected and removed.
   */
  public function testMissingItems() {
    $item_ids = [
      'search_api_test/1',
      'search_api_test/2',
    ];
    $items = $this->index->loadItemsMultiple($item_ids);
    $this->assertEquals([], $items, 'No items loaded from test datasource.');
    $methods = $this->getCalledMethods('tracker');
    $this->assertContains('trackItemsDeleted', $methods, 'Unknown items deleted from tracker.');
    $args = $this->getMethodArguments('tracker', 'trackItemsDeleted');
    $this->assertEquals([$item_ids], $args, 'Correct items deleted from tracker.');
    $methods = $this->getCalledMethods('backend');
    $this->assertContains('deleteItems', $methods, 'Unknown items deleted from server.');

    // If an error occurs while retrieving the datasource (which will happen for
    // "unknown/1"), the items should not be deleted from tracking and the
    // server.
    $expected_deletions = $item_ids;
    $item_ids = [
      'search_api_test/1',
      'search_api_test/2',
      'search_api_test/3',
      'unknown/1',
    ];
    $this->setReturnValue('datasource', 'loadMultiple', ['3' => '']);
    $items = $this->index->loadItemsMultiple($item_ids);
    $expected_items = ['search_api_test/3' => ''];
    $this->assertEquals($expected_items, $items, 'Expected items loaded from test datasource.');
    $methods = $this->getCalledMethods('tracker');
    $this->assertContains('trackItemsDeleted', $methods, 'Unknown items deleted from tracker.');
    $args = $this->getMethodArguments('tracker', 'trackItemsDeleted');
    $this->assertEquals([$expected_deletions], $args, 'Correct items deleted from tracker.');
    $methods = $this->getCalledMethods('backend');
    $this->assertContains('deleteItems', $methods, 'Unknown items deleted from server.');

    // If the option "delete_on_fail" is set to FALSE, trackItemsDeleted should
    // not be called.
    $this->index->setOption('delete_on_fail', FALSE);
    $items = $this->index->loadItemsMultiple($item_ids);
    $this->assertEquals($expected_items, $items, 'Expected items loaded from test datasource.');
    $methods = $this->getCalledMethods('tracker');
    $this->assertNotContains('trackItemsDeleted', $methods, 'trackItemsDeleted was called despite delete_on_fail.');
  }

}
