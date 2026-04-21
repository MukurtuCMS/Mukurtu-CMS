<?php

declare(strict_types=1);

namespace Drupal\migrate_tools\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of migration entities in a given group.
 *
 * @package Drupal\migrate_tools\Controller
 *
 * @ingroup migrate_tools
 *
 * @phpstan-consistent-constructor
 */
class MigrationListBuilder extends ConfigEntityListBuilder {

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected readonly CurrentRouteMatch $currentRouteMatch,
    protected readonly MigrationPluginManagerInterface $migrationPluginManager,
    protected readonly LoggerInterface $logger,
    protected readonly KeyValueFactoryInterface $keyValue,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('current_route_match'),
      $container->get('plugin.manager.migration'),
      $container->get('logger.channel.migrate_tools'),
      $container->get('keyvalue'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Retrieve the migrations belonging to the appropriate group.
   *
   * @return array
   *   An array of entity IDs.
   */
  protected function getEntityIds(): array {
    $migration_group = $this->currentRouteMatch->getParameter('migration_group');

    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort($this->entityType->getKey('id'));

    $migration_groups = MigrationGroup::loadMultiple();

    if (array_key_exists($migration_group, $migration_groups)) {
      $query->condition('migration_group', $migration_group);
    }
    else {
      $query->notExists('migration_group');
    }
    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * Builds the header row for the entity listing.
   *
   * @return array
   *   A render array structure of header strings.
   *
   * @see \Drupal\Core\Entity\EntityListController::render()
   */
  public function buildHeader(): array {
    $header = [];
    $header['label'] = $this->t('Migration');
    $header['machine_name'] = $this->t('Machine Name');
    $header['status'] = $this->t('Status');
    $header['total'] = $this->t('Total');
    $header['imported'] = $this->t('Imported');
    $header['unprocessed'] = $this->t('Unprocessed');
    $header['messages'] = $this->t('Messages');
    $header['last_imported'] = $this->t('Last Imported');
    $header['operations'] = $this->t('Operations');
    return $header;
  }

  /**
   * Builds a row for a migration plugin.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The migration plugin for which to build the row.
   *
   * @return array
   *   A render array of the table row for displaying the plugin information.
   *
   * @see \Drupal\Core\Entity\EntityListController::render()
   */
  public function buildRow(EntityInterface $entity): array {
    $row = [];
    try {
      assert($entity instanceof Migration);
      $migration = $this->migrationPluginManager->createInstance($entity->id());
      if (!$migration) {
        return $row;
      }
      $migration_group = $entity->get('migration_group');
      if (!$migration_group) {
        $migration_group = 'default';
      }
      $route_parameters = [
        'migration_group' => $migration_group,
        'migration' => $migration->id(),
      ];
      $row['label'] = [
        'data' => [
          '#type' => 'link',
          '#title' => $migration->label(),
          '#url' => Url::fromRoute("entity.migration.overview", $route_parameters),
        ],
      ];
      $row['machine_name'] = $migration->id();
      $row['status'] = $migration->getStatusLabel();
    }
    catch (\Exception $e) {
      $this->logger->warning('Migration entity id %id is malformed: %orig', [
        '%id' => $entity->id(),
        '%orig' => $e->getMessage(),
      ]);
      return $row;
    }

    try {
      // Derive the stats.
      $source_plugin = $migration->getSourcePlugin();
      $row['total'] = $source_plugin->count();
      $map = $migration->getIdMap();
      $row['imported'] = $map->importedCount();
      // -1 indicates uncountable sources.
      if ($row['total'] == -1) {
        $row['total'] = $this->t('N/A');
        $row['unprocessed'] = $this->t('N/A');
      }
      else {
        $row['unprocessed'] = $row['total'] - $map->processedCount();
      }
      $row['messages'] = [
        'data' => [
          '#type' => 'link',
          '#title' => $map->messageCount(),
          '#url' => Url::fromRoute("migrate_tools.messages", $route_parameters),
        ],
      ];
      $migrate_last_imported_store = $this->keyValue->get('migrate_last_imported');
      $last_imported = $migrate_last_imported_store->get($migration->id(), FALSE);
      if ($last_imported) {
        $row['last_imported'] = $this->dateFormatter->format((int) ($last_imported / 1000),
          'custom', 'Y-m-d H:i:s');
      }
      else {
        $row['last_imported'] = '';
      }

      $row['operations']['data'] = $this->buildOperations($entity);
    }
    catch (\Throwable) {
      $this->handleThrowable($row);
    }

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $migration_group = $entity->get('migration_group');
    if (!$migration_group) {
      $migration_group = 'default';
    }
    $operations['execute'] = [
      'title' => $this->t('Execute'),
      'url' => Url::fromRoute('migrate_tools.execute', [
        'migration_group' => $migration_group,
        'migration' => $entity->id(),
      ]),
    ];
    return $operations;
  }

  /**
   * Derive the row data.
   *
   * @param array $row
   *   The table row.
   */
  protected function handleThrowable(array &$row): void {
    $row['status'] = $this->t('No data found');
    $row['total'] = $this->t('N/A');
    $row['imported'] = $this->t('N/A');
    $row['unprocessed'] = $this->t('N/A');
    $row['messages'] = $this->t('N/A');
    $row['last_imported'] = $this->t('N/A');
    $row['operations'] = $this->t('N/A');
  }

  /**
   * Add group route parameter.
   *
   * @param \Drupal\Core\Url $url
   *   The URL associated with an operation.
   * @param string $migration_group
   *   The migration's parent group.
   */
  protected function addGroupParameter(Url $url, $migration_group): void {
    if (!$migration_group) {
      $migration_group = 'default';
    }
    $route_parameters = $url->getRouteParameters() + ['migration_group' => $migration_group];
    $url->setRouteParameters($route_parameters);
  }

}
