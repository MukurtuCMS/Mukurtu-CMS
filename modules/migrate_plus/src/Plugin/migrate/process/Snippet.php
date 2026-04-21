<?php

declare(strict_types=1);

namespace Drupal\migrate_plus\Plugin\migrate\process;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Applies process pipelines described in YAML snippets.
 *
 * Available configuration keys:
 * - module: the module providing the snippet
 * - path: path of the YAML file, relative to module/migrations/process and
 *   without the .yml extension.
 *
 * Examples:
 *
 * Suppose the file my_module/migrations/process/clean_whitespace.yml contains
 * the following:
 *
 * @code
 * -
 *   plugin: callback
 *   callable: htmlentities
 * -
 *   plugin: str_replace
 *   search:
 *     - '&#160;'
 *     - '&nbsp;'
 *   replace: ' '
 * -
 *   plugin: str_replace
 *   regex: true
 *   search: '@\s+@'
 *   replace: ' '
 * -
 *   plugin: callback
 *   callable: trim
 * @endcode
 *
 * Then that process pipeline can be used as follows:
 *
 * @code
 * process:
 *   field_formatted_text/value:
 *     plugin: snippet
 *     module: my_module
 *     path: clean_whitespace
 *     source: html_string
 *   field_formatted_text/format:
 *     plugin: default_value
 *     default_value: full_html
 * @endcode
 *
 * Normally, any process plugin can specify the source key, which implicitly
 * adds the get process plugin to the pipeline. This does NOT WORK inside a
 * snippet:
 *
 * @code
 *   # The source will be ignored. The source of the snippet plugin is always
 *   # used.
 * - plugin: default_value
 *   default_value: default value
 *   source: some_field
 * @endcode
 *
 * Instead, explicitly add the get plugin to the pipeline:
 *
 * @code
 * - plugin: get
 *   source: some_field
 * - plugin: default_value
 *   default_value: default value
 * @endcode
 */
#[MigrateProcess(id: 'snippet')]
class Snippet extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The process pipeline.
   *
   * @var \Drupal\migrate\Plugin\MigrateProcessInterface[]
   */
  protected $pipeline = [];

  /**
   * Constructs a snippet process plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface|null $migration
   *   The migration entity.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\migrate\Plugin\MigratePluginManagerInterface $plugin_manager
   *   The process plugin manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    $migration,
    protected ModuleHandlerInterface $moduleHandler,
    MigratePluginManagerInterface $plugin_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $module = $this->configuration['module'] ?? NULL;
    if (empty($module)) {
      throw new \InvalidArgumentException("The 'module' parameter is required.");
    }
    if (!$this->moduleHandler->moduleExists($module)) {
      throw new \InvalidArgumentException("The '$module' module is not installed.");
    }
    if (empty($this->configuration['path'] ?? NULL)) {
      throw new \InvalidArgumentException("The 'path' parameter is required.");
    }

    $snippet_file = implode('/', [
      $this->moduleHandler->getModule($module)->getPath(),
      'migrations/process',
      $this->configuration['path'],
    ]) . '.yml';
    try {
      $steps = Yaml::parseFile($snippet_file);
    }
    catch (ParseException $e) {
      throw new \InvalidArgumentException($e->getMessage());
    }

    foreach ($steps as $step_configuration) {
      $step_id = $step_configuration['plugin'];
      unset($step_configuration['plugin']);
      $this->pipeline[] = $plugin_manager->createInstance($step_id, $step_configuration, $migration);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('module_handler'),
      $container->get('plugin.manager.migrate.process'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    /** @var \Drupal\migrate\Plugin\MigrateProcessInterface $step */
    foreach ($this->pipeline as $step) {
      $step->reset();
      $value = $step->transform($value, $migrate_executable, $row, $destination_property);
      if ($step->isPipelineStopped()) {
        $this->stopPipeline();
        return $value;
      }
    }
    return $value;
  }

}
