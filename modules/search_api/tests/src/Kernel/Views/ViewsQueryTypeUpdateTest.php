<?php

namespace Drupal\Tests\search_api\Kernel\Views;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\ViewEntityInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the Search API Views are updated correctly.
 *
 * @covers search_api_post_update_views_query_type
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class ViewsQueryTypeUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'search_api',
    'search_api_db',
    'search_api_test_node_indexing',
    'system',
    'text',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('search_api_task');

    $this->installConfig([
      'search_api',
      'search_api_test_node_indexing',
    ]);
  }

  /**
   * Tests that an existing view is updated with correct query and cache plugin.
   */
  public function testViewUpdate() {
    // Create the view with faulty properties.
    $module_path = \Drupal::service('extension.list.module')->getPath('search_api');
    $view_yml = file_get_contents("$module_path/tests/fixtures/views.view.search_api_query_type_test.yml");
    $values = Yaml::decode($view_yml);
    $view_id = $values['id'];
    $config = \Drupal::configFactory()->getEditable('views.view.' . $view_id);
    $config->setData($values);
    $config->save();

    require "$module_path/search_api.post_update.php";
    search_api_post_update_views_query_type();

    // Check that the altered metadata is now present in the view and the
    // configuration.
    $view = \Drupal::getContainer()
      ->get('entity_type.manager')
      ->getStorage('view')
      ->load($view_id);
    assert($view instanceof ViewEntityInterface);
    $executable = \Drupal::getContainer()->get('views.executable')->get($view);
    $display = $executable->getDisplay();
    $this->assertEquals('search_api_query', $display->getOption('query')['type']);
    $this->assertEquals('none', $display->getOption('cache')['type']);
  }

}
