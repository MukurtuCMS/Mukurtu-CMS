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
 * Looks up a Local Contexts label or notice by name, or passes through a
 * valid compound stored value.
 *
 * @MigrateProcessPlugin(
 *   id = "local_contexts_label_lookup"
 * )
 */
class LocalContextsLabelLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
    $labels = $this->manager->getAllLabels();
    $notices = $this->manager->getAllNotices();

    // Build set of valid stored compound values.
    $valid_stored = [];
    foreach ($labels as $label_id => $label) {
      $stored = $label['project_id'] . ':' . $label_id . ':' . $label['display'];
      $valid_stored[$stored] = $stored;
    }
    foreach ($notices as $notice) {
      $stored = $notice['project_id'] . ':' . $notice['type'] . ':' . $notice['display'];
      $valid_stored[$stored] = $stored;
    }

    // Pass-through: value is already a valid stored compound value.
    if (isset($valid_stored[$value])) {
      return $value;
    }

    // Build a lowercase name → stored compound value map.
    $name_map = [];
    foreach ($labels as $label_id => $label) {
      $stored = $label['project_id'] . ':' . $label_id . ':' . $label['display'];
      $key = mb_strtolower($label['name']);
      $name_map[$key][] = $stored;
    }
    foreach ($notices as $notice) {
      $stored = $notice['project_id'] . ':' . $notice['type'] . ':' . $notice['display'];
      $key = mb_strtolower($notice['name']);
      $name_map[$key][] = $stored;
    }

    $needle = mb_strtolower(trim($value));
    $matches = $name_map[$needle] ?? [];

    if (count($matches) > 1) {
      throw new MigrateException(sprintf('"%s" is ambiguous, multiple labels/notices share this name. Use the stored compound value instead.', $value));
    }

    if (count($matches) === 1) {
      return reset($matches);
    }

    return NULL;
  }

}
