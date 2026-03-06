<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Looks up a Local Contexts label or notice by name, or passes through a
 * valid compound stored value.
 */
#[MigrateProcess('local_contexts_label_lookup')]
class LocalContextsLabelLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a LocalContextsLabelLookup object.
   *
   * @param array $configuration
   *    A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *    The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *    The plugin implementation definition.
   * @param \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager $manager
   *    The Local Contexts supported project manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected LocalContextsSupportedProjectManager $manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('mukurtu_local_contexts.supported_project_manager'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
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

    // Split on the first non-escaped colon to detect compound format.
    $parts = preg_split('/(?<!\\\\):/', $value, 2);

    if (count($parts) === 2) {
      return $this->transformCompound($parts[0], $parts[1], $labels, $notices, $value);
    }

    return $this->transformByName(trim($value), $labels, $notices, $value);
  }

  /**
   * Resolves a compound "Project Title: Label Name" value.
   *
   * @param string $project_part
   *   The raw project title segment (may contain escaped colons).
   * @param string $label_part
   *   The raw label/notice name segment (may contain escaped colons).
   * @param array $labels
   *   All labels from the manager.
   * @param array $notices
   *   All notices from the manager.
   * @param string $original_value
   *   The original input value, used in error messages.
   *
   * @return string|null
   *   The stored compound value, or NULL if not found.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  protected function transformCompound(string $project_part, string $label_part, array $labels, array $notices, string $original_value): ?string {
    $project_title = trim(str_replace('\:', ':', $project_part));
    $label_name = trim(str_replace('\:', ':', $label_part));

    // Build a lowercased project title → [project_id, ...] map.
    $project_title_map = [];
    foreach ($this->manager->getAllProjects() as $project_id => $project) {
      $key = mb_strtolower($project['title']);
      $project_title_map[$key][] = $project_id;
    }

    $project_key = mb_strtolower($project_title);
    $project_matches = $project_title_map[$project_key] ?? [];

    if (count($project_matches) === 0) {
      throw new MigrateException(sprintf('Project "%s" not found in "%s".', $project_title, $original_value));
    }

    if (count($project_matches) > 1) {
      throw new MigrateException(sprintf('"%s" is ambiguous in "%s", multiple projects share this title. Use the stored compound value instead.', $project_title, $original_value));
    }

    $project_id = reset($project_matches);

    // Build a lowercased label/notice name → stored value map filtered to this project.
    $name_map = [];
    foreach ($labels as $label_id => $label) {
      if ($label['project_id'] !== $project_id) {
        continue;
      }
      $stored = $label['project_id'] . ':' . $label_id . ':' . $label['display'];
      $key = mb_strtolower($label['name']);
      $name_map[$key][] = $stored;
    }
    foreach ($notices as $notice) {
      if ($notice['project_id'] !== $project_id) {
        continue;
      }
      $stored = $notice['project_id'] . ':' . $notice['type'] . ':' . $notice['display'];
      $key = mb_strtolower($notice['name']);
      $name_map[$key][] = $stored;
    }

    $needle = mb_strtolower($label_name);
    $matches = $name_map[$needle] ?? [];

    if (count($matches) > 1) {
      throw new MigrateException(sprintf('"%s" is ambiguous in "%s", multiple labels/notices in this project share this name. Use the stored compound value instead.', $label_name, $original_value));
    }

    if (count($matches) === 1) {
      return reset($matches);
    }

    return NULL;
  }

  /**
   * Resolves a bare label/notice name across all projects.
   *
   * @param string $needle
   *   The label or notice name to look up (already trimmed).
   * @param array $labels
   *   All labels from the manager.
   * @param array $notices
   *   All notices from the manager.
   * @param string $original_value
   *   The original input value, used in error messages.
   *
   * @return string|null
   *   The stored compound value, or NULL if not found.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  protected function transformByName(string $needle, array $labels, array $notices, string $original_value): ?string {
    // Build a lowercase name → stored compound value map across all projects.
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

    $matches = $name_map[mb_strtolower($needle)] ?? [];

    if (count($matches) > 1) {
      throw new MigrateException(sprintf('"%s" is ambiguous, multiple labels/notices share this name. Use the stored compound value instead.', $original_value));
    }

    if (count($matches) === 1) {
      return reset($matches);
    }

    return NULL;
  }

}
