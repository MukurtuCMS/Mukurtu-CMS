<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Controller;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Entity\MigrationGroupInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for migrate_tools migration view routes.
 *
 * @phpstan-consistent-constructor
 */
class MigrationController extends ControllerBase implements ContainerInjectionInterface {

  public function __construct(
    protected MigrationPluginManagerInterface $migrationPluginManager,
    protected CurrentRouteMatch $currentRouteMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('current_route_match')
    );
  }

  /**
   * Route title callback for migration pages.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface|null $migration
   *   Migration from url, or NULL if this is just a group page.
   * @param \Drupal\migrate_plus\Entity\MigrationGroupInterface|null $group
   *   Migration group from url.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string|null
   *   Title.
   */
  public function title(?MigrationInterface $migration = NULL, ?MigrationGroupInterface $group = NULL): MarkupInterface|string|null {
    $route_name = $this->currentRouteMatch->getRouteName();
    $group_label = $group?->label();
    $migration_label = $migration?->label();

    // If neither entity is available, fall back to null.
    if ($group_label === NULL && $migration_label === NULL) {
      return NULL;
    }

    return match ($route_name) {
      'entity.migration_group.edit_form' => $this->t('Edit migration group @group', ['@group' => $group_label ?? '']),
      'entity.migration_group.delete_form' => $this->t('Delete migration group @group', ['@group' => $group_label ?? '']),
      'entity.migration.list' => $this->t('Migrations of @group', ['@group' => $group_label ?? '']),
      'entity.migration.overview' => $this->t('Migration overview of @migration', ['@migration' => $migration_label ?? '']),
      'entity.migration.source' => $this->t('Source of @migration', ['@migration' => $migration_label ?? '']),
      'entity.migration.process' => $this->t('Process of @migration', ['@migration' => $migration_label ?? '']),
      'entity.migration.destination' => $this->t('Destination of @migration', ['@migration' => $migration_label ?? '']),
      'entity.migration.edit_form' => $this->t('Edit migration @migration', ['@migration' => $migration_label ?? '']),
      'entity.migration.delete_form' => $this->t('Delete migration @migration', ['@migration' => $migration_label ?? '']),
      'migrate_tools.execute' => $this->t('Execute migration @migration', ['@migration' => $migration_label ?? '']),
      default => $migration_label ?? $group_label,
    };
  }

  /**
   * Displays an overview of a migration entity.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationGroupInterface $migration_group
   *   The migration group.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The $migration.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function overview(MigrationGroupInterface $migration_group, MigrationInterface $migration): array {
    $build = [];
    $build['overview'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Overview'),
    ];

    $build['overview']['group'] = [
      '#title' => $this->t('Group:'),
      '#markup' => Xss::filterAdmin($migration_group->label()),
      '#type' => 'item',
    ];

    $build['overview']['description'] = [
      '#title' => $this->t('Description:'),
      '#markup' => Xss::filterAdmin($migration->label()),
      '#type' => 'item',
    ];
    $migration_dependencies = $this->getMigrationPlugin($migration)->getMigrationDependencies();
    if (!empty($migration_dependencies['required'])) {
      $build['overview']['dependencies'] = [
        '#title' => $this->t('Migration Dependencies') ,
        '#markup' => Xss::filterAdmin(implode(', ', $migration_dependencies['required'])),
        '#type' => 'item',
      ];
    }
    if (!empty($migration_dependencies['optional'])) {
      $build['overview']['soft_dependencies'] = [
        '#title' => $this->t('Soft Migration Dependencies'),
        '#markup' => Xss::filterAdmin(implode(', ', $migration_dependencies['optional'])),
        '#type' => 'item',
      ];
    }

    return $build;
  }

  /**
   * Display source information of a migration entity.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationGroupInterface $migration_group
   *   The migration group.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The $migration.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function source(MigrationGroupInterface $migration_group, MigrationInterface $migration): array {
    $build = [];
    // Source field information.
    $build['source'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Source'),
      '#group' => 'detail',
      '#description' => $this->t('<p>These are the fields available from the source of this migration task. The machine names listed here may be used as sources in the process pipeline.</p>'),
      '#description_display' => 'after',
      '#attributes' => [
        'id' => 'migration-detail-source',
      ],
    ];
    $migration_plugin = $this->getMigrationPlugin($migration);
    $source = $migration_plugin->getSourcePlugin();
    $build['source']['query'] = [
      '#type' => 'item',
      '#title' => $this->t('Query'),
      '#markup' => '<pre>' . Xss::filterAdmin($source) . '</pre>',
    ];
    $header = [$this->t('Machine name'), $this->t('Description')];
    $rows = [];
    foreach ($source->fields($migration_plugin) as $machine_name => $description) {
      $rows[] = [
        ['data' => Html::escape($machine_name)],
        ['data' => Xss::filterAdmin($description)],
      ];
    }

    $build['source']['fields'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No fields'),
    ];

    return $build;
  }

  /**
   * Display process information of a migration entity.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationGroupInterface $migration_group
   *   The migration group.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The $migration.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function process(MigrationGroupInterface $migration_group, MigrationInterface $migration): array {
    $build = [];
    $migration_plugin = $this->getMigrationPlugin($migration);

    // Process information.
    $build['process'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Process'),
    ];

    $header = [
      $this->t('Destination'),
      $this->t('Source'),
      $this->t('Process plugin'),
      $this->t('Default'),
    ];
    $rows = [];
    foreach ($migration_plugin->getProcess() as $destination_id => $process_line) {
      $row = [];
      $row[] = ['data' => Html::escape($destination_id)];
      if (isset($process_line[0]['source'])) {
        if (is_array($process_line[0]['source'])) {
          $process_line[0]['source'] = implode(', ', $process_line[0]['source']);
        }
        $row[] = ['data' => Xss::filterAdmin($process_line[0]['source'])];
      }
      else {
        $row[] = '';
      }
      if (isset($process_line[0]['plugin'])) {
        $process_line_plugins = [];
        foreach ($process_line as $process_line_row) {
          $process_line_plugins[] = Xss::filterAdmin($process_line_row['plugin']);
        }
        $row[] = ['data' => implode(', ', $process_line_plugins)];
      }
      else {
        $row[] = '';
      }
      if (isset($process_line[0]['default_value'])) {
        $row[] = ['data' => Xss::filterAdmin($process_line[0]['default_value'])];
      }
      else {
        $row[] = '';
      }
      $rows[] = $row;
    }

    $build['process']['fields'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No process defined.'),
    ];

    return $build;
  }

  /**
   * Displays destination information of a migration entity.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationGroupInterface $migration_group
   *   The migration group.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The $migration.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function destination(MigrationGroupInterface $migration_group, MigrationInterface $migration): array {
    $build = [];
    $destination = $this->getMigrationPlugin($migration)->getDestinationPlugin();

    // Destination field information.
    $build['destination'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Destination'),
      '#group' => 'detail',
      '#description' => $this->t('<p>These are the fields available in the destination plugin of this migration task. The machine names are those available to be used as the keys in the process pipeline.</p>'),
      '#description_display' => 'after',
      '#attributes' => [
        'id' => 'migration-detail-destination',
      ],
    ];
    $build['destination']['type'] = [
      '#type' => 'item',
      '#title' => $this->t('Type'),
      '#markup' => Xss::filterAdmin($destination->getPluginId()),
    ];
    $header = [$this->t('Machine name'), $this->t('Description')];
    $rows = [];
    $destination_fields = $destination->fields() ?: [];
    foreach ($destination_fields as $machine_name => $description) {
      $rows[] = [
        ['data' => Html::escape($machine_name)],
        ['data' => Xss::filterAdmin($description)],
      ];
    }

    $build['destination']['fields'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No fields'),
    ];

    return $build;
  }

  /**
   * Return an instance of a migration plugin.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The $migration.
   *
   * @return object
   *   A fully configured plugin instance.
   */
  protected function getMigrationPlugin(MigrationInterface $migration) {
    return $this->migrationPluginManager->createInstance($migration->id());
  }

}
