<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate\process;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Plugin\migrate\process\Snippet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the snippet plugin.
 */
#[CoversClass(Snippet::class)]
#[Group('migrate_plus')]
#[RunTestsInSeparateProcesses]
final class SnippetTest extends KernelTestBase implements MigrateMessageInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'migrate_plus',
    'snippet_process_test',
  ];

  /**
   * The process plugin manager.
   */
  protected ?MigratePluginManagerInterface $pluginManager = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->pluginManager = $this->container->get('plugin.manager.migrate.process');
  }

  /**
   * Tests using a valid snippet.
   *
   * @param string $path
   *   The path to the snippet, relative to migrations/process.
   * @param mixed $expected
   *   The expected result of the snippet.
   *
   * @dataProvider providerValidSnippet
   */
  #[DataProvider('providerValidSnippet')]
  public function testValidSnippet(string $path, $expected): void {
    /** @var \Drupal\migrate\MigrateExecutableInterface $executable */
    $executable = $this->prophesize(MigrateExecutableInterface::class)->reveal();
    $row = new Row(['some_field' => 'source value'], []);
    $configuration = [
      'module' => 'snippet_process_test',
      'path' => $path,
    ];
    /** @var \Drupal\migrate_plus\Plugin\migrate\process\Snippet $snippet */
    $snippet = $this->pluginManager->createInstance('snippet', $configuration);

    // Replace 'foo' with ' ' and apply trim().
    $value = 'foo foobar bar';
    $result = $snippet->transform($value, $executable, $row, 'destination_property');
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testValidSnippet.
   */
  public static function providerValidSnippet(): array {
    return [
      'Replace "foo" with " " and apply trim()' => [
        'path' => 'strip_foo',
        'expected' => 'bar bar',
      ],
      'Get value from the Row object' => [
        'path' => 'use_source/with_get',
        'expected' => 'source value',
      ],
      'Implicit get does not work' => [
        'path' => 'use_source/without_get',
        'expected' => 'foo foobar bar',
      ],
      'Stop the pipeline' => [
        'path' => 'check_numeric',
        'expected' => NULL,
      ],
    ];
  }

  /**
   * Tests configuration validation.
   *
   * @param string[] $configuration
   *   The configuration for the snippet plugin. The expected keys are 'module'
   *   and 'path'.
   * @param string $message
   *   The expected exception message.
   *
   * @dataProvider providerConfig
   */
  #[DataProvider('providerConfig')]
  public function testInvalidConfig(array $configuration, string $message): void {
    $module_path = \Drupal::service('module_handler')
      ->getModule('snippet_process_test')->getPath();
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage(sprintf($message, $module_path));
    $this->pluginManager->createInstance('snippet', $configuration);
  }

  /**
   * Data provider for testInvalidConfig.
   */
  public static function providerConfig(): array {
    return [
      'missing module parameter' => [
        'configuration' => ['path' => 'strip_foo'],
        'message' => "The 'module' parameter is required.",
      ],
      'missing path parameter' => [
        'configuration' => ['module' => 'snippet_process_test'],
        'message' => "The 'path' parameter is required.",
      ],
      'invalid module name' => [
        'configuration' => [
          'module' => 'not_installed',
          'path' => 'strip_foo',
        ],
        'message' => "The 'not_installed' module is not installed.",
      ],
      'missing YAML' => [
        'configuration' => [
          'module' => 'snippet_process_test',
          'path' => 'missing_yaml',
        ],
        'message' => 'File "%s/migrations/process/missing_yaml.yml" does not exist.',
      ],
      'invalid YAML' => [
        'configuration' => [
          'module' => 'snippet_process_test',
          'path' => 'invalid_yaml',
        ],
        'message' => 'Unable to parse in "%s/migrations/process/invalid_yaml.yml" at line 1',
      ],
    ];
  }

  /**
   * Tests handling of skip_on_empty in a snippet.
   */
  public function testSkipHandling(): void {
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = \Drupal::service('plugin.manager.migration')->createStubMigration([
      'source' => [
        'plugin' => 'embedded_data',
        'data_rows' => [
          [
            'id' => 1,
            'value' => '',
          ],
        ],
        'ids' => [
          'id' => ['type' => 'integer'],
        ],
      ],
      'process' => [
        'value' => [
          [
            'plugin' => 'snippet',
            'module' => 'snippet_process_test',
            'path' => 'skip_empty',
            'source' => 'value',
          ],
          [
            'plugin' => 'default_value',
            'default_value' => 'default value',
          ],
        ],
      ],
      'destination' => [
        'plugin' => 'config',
        'config_name' => 'snippet_test.settings',
      ],
    ]);

    $migration_executable = (new MigrateExecutable($migration, $this));
    $migration_executable->import();
    $config = \Drupal::config('snippet_test.settings');
    $this->assertEquals('', $config->get('value'));
  }

  /**
   * {@inheritdoc}
   */
  public function display($message, $type = 'status'): void {
    $this->assertTrue($type == 'status', $message);
  }

}
