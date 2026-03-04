<?php

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Plugin\ContainerFactoryPluginInterface;

/**
 * Looks up a Local Contexts project by title or passes through a valid UUID.
 *
 * @MigrateProcessPlugin(
 *   id = "local_contexts_project_lookup"
 * )
 */
class LocalContextsProjectLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager
   */
  protected $manager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, LocalContextsSupportedProjectManager $manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->manager = $manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mukurtu_local_contexts.supported_project_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $projects = $this->manager->getAllProjects();

    // Pass-through: value is already a valid project UUID.
    if (isset($projects[$value])) {
      return $value;
    }

    // Build a lowercase title → project UUID map.
    $title_map = [];
    foreach ($projects as $project_id => $project) {
      $key = mb_strtolower($project['title']);
      $title_map[$key][] = $project_id;
    }

    $needle = mb_strtolower(trim($value));
    $matches = $title_map[$needle] ?? [];

    if (count($matches) > 1) {
      throw new MigrateException(sprintf('"%s" is ambiguous, multiple projects share this title. Use the project UUID instead.', $value));
    }

    if (count($matches) === 1) {
      return reset($matches);
    }

    return NULL;
  }

}
