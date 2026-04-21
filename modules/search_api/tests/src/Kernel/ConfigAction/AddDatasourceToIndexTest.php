<?php

namespace Drupal\Tests\search_api\Kernel\ConfigAction;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\ServerInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the "add datasource to index" config action works correctly.
 *
 * @covers \Drupal\search_api\Plugin\ConfigAction\AddDatasourceToIndex
 * @group search_api
 * @group Recipe
 */
#[RunTestsInSeparateProcesses]
class AddDatasourceToIndexTest extends KernelTestBase {

  /**
   * The search server used for testing.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected ServerInterface $server;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_test',
    'user',
    'system',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig('search_api');

    // Create a test server.
    $this->server = Server::create([
      'id' => 'test',
      'name' => 'Test server',
      'status' => 1,
      'backend' => 'search_api_test',
    ]);
    $this->server->save();
    $index = Index::create([
      'id' => 'test',
      'name' => 'Test index',
      'status' => 1,
      'datasource_settings' => [
        'entity:search_api_task' => [],
      ],
      'tracker_settings' => [
        'default' => [],
      ],
      'server' => $this->server->id(),
      'options' => ['index_directly' => TRUE],
    ]);
    $index->save();
  }

  /**
   * Tests the "add datasource"
   *
   * @param string $entity_name
   * @param string $message
   *
   * @dataProvider addDatasourceToIndexTestDataProvider
   */
  public function testAddDatasourceToIndex(string $entity_name, string $message): void {
    if (version_compare(\Drupal::VERSION, '10.3', '<')) {
      $this->markTestSkipped('Config actions and recipes are not available before Drupal 10.3.');
    }

    $recipe = $this->createRecipe([
      'name' => 'Search API test',
      'config' => [
        'actions' => [
          'search_api.index.test' => [
            'addDatasourceToIndex' => [
              'name' => $entity_name,
              'options' => [],
            ],
          ],
        ],
      ],
    ]);
    if ($message) {
      $this->expectException(ConfigActionException::class);
      $this->expectExceptionMessage($message);
    }
    RecipeRunner::processRecipe($recipe);
    if (!$message) {
      $datasource = Index::load('test')->getDatasource($entity_name);
      $this->assertEquals($entity_name, $datasource->getPluginId());
    }
  }

  /**
   * Provides test data for testAddDatasourceToIndex().
   *
   * @return array[]
   *   An array of argument arrays for testAddDatasourceToIndex().
   *
   * @see \Drupal\Tests\search_api\Kernel\ConfigAction\AddDatasourceToIndexTest::testAddDatasourceToIndex()
   */
  public static function addDatasourceToIndexTestDataProvider(): array {
    return [
      ['entity:node', ''],
      ['entity:user', ''],
      ['entity:search_api_task', 'Datasource "entity:search_api_task" already exists on index "Test index".'],
      ['entity:bogus', 'Error while adding datasource "entity:bogus" to index "Test index": The "entity:bogus" plugin does not exist. Valid plugin IDs for Drupal\search_api\Datasource\DatasourcePluginManager are: entity:search_api_task, entity:user, entity:node, search_api_test.'],
    ];
  }

  /**
   * Creates a recipe in a temporary directory.
   *
   * @param array $data
   *   The contents of recipe.yml.
   *
   * @return \Drupal\Core\Recipe\Recipe
   *   The recipe object.
   *
   * @todo Use RecipeTestTrait::createRecipe() instead once we depend on
   *   Drupal 10.3.
   */
  private function createRecipe(array $data): Recipe {
    $data = Yaml::encode($data);
    $recipes_dir = $this->siteDirectory . '/recipes';
    $dir = uniqid($recipes_dir . '/');
    mkdir($dir, recursive: TRUE);
    file_put_contents($dir . '/recipe.yml', $data);

    return Recipe::createFromDirectory($dir);
  }

}
