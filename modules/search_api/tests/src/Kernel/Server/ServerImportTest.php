<?php

namespace Drupal\Tests\search_api\Kernel\Server;

use Drupal\Component\Uuid\Php;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Server;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that importing a server works correctly.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class ServerImportTest extends KernelTestBase {

  /**
   * The test server.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api',
    'search_api_test',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installConfig('search_api');

    // Create a test server.
    $this->server = Server::create([
      'name' => 'Test Server',
      'id' => 'test_server',
      'status' => 1,
      'backend' => 'search_api_test',
      'backend_config' => [
        'test' => 'foo',
      ],
    ]);
    $this->server->save();

    $config_storage = $this->container->get('config.storage');
    $config_sync = $this->container->get('config.storage.sync');
    // Ensure the "system.site" config exists.
    $config_storage->write('system.site', ['uuid' => (new Php())->generate()]);
    $this->copyConfig($config_storage, $config_sync);
  }

  /**
   * Tests that importing new server config works correctly.
   */
  public function testServerImport() {
    // Stolen from
    // \Drupal\Tests\system\Kernel\Entity\ConfigEntityImportTest::assertConfigUpdateImport().
    $name = $this->server->getConfigDependencyName();
    $original_data = $this->server->toArray();
    $custom_data = $original_data;
    $custom_data['name'] = 'Old test server';
    $custom_data['backend_config']['test'] = 'bar';

    $this->container->get('config.storage.sync')->write($name, $custom_data);

    // Verify the active configuration still returns the default values.
    $config = $this->config($name);
    $this->assertSame($config->get(), $original_data);

    // Import.
    $this->configImporter()->import();

    // Verify the values were updated.
    $this->container->get('config.factory')->reset($name);
    $config = $this->config($name);
    $this->assertSame($config->get(), $custom_data);
  }

}
