<?php

namespace Drupal\Tests\search_api\Kernel\Views;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\ViewEntityInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the correct query type is stored with views.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class ViewsQueryTypeTest extends KernelTestBase {

  /**
   * The view configuration array as created by views.
   *
   * @var array
   */
  protected $originalView;

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
   * Tests that a new view with default incorrect query gets corrected.
   */
  public function testViewInsert() {
    $view_yml = file_get_contents(\Drupal::service('extension.list.module')->getPath('search_api') . '/tests/fixtures/views.view.search_api_query_type_test.yml');
    $values = Yaml::decode($view_yml);
    $view = View::create($values);
    $this->assertTrue($view->isNew());
    $view->save();

    // Check that the altered metadata is now present in the view and the
    // configuration.
    $view = \Drupal::getContainer()
      ->get('entity_type.manager')
      ->getStorage('view')
      ->load($values['id']);
    assert($view instanceof ViewEntityInterface);
    $executable = \Drupal::getContainer()->get('views.executable')->get($view);
    $display = $executable->getDisplay();
    $this->assertEquals('search_api_query', $display->getOption('query')['type']);
    $this->assertEquals('search_api_none', $display->getOption('cache')['type']);
  }

}
