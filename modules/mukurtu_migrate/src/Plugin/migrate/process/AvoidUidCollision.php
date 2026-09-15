<?php

declare(strict_types=1);

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Keeps a migrated uid from landing on an account the migration didn't create.
 *
 * The user migrations force the destination uid to the source site's uid so
 * that users keep their ids. If an account already exists at that uid on the
 * destination site (the blocked "Submission Forms" service account that
 * mukurtu_submissions creates at install time, or any account a site builder
 * made before migrating), core's entity migrate destination updates that
 * account in place instead of creating a new user, merging an unrelated
 * legacy account and its group memberships onto it.
 *
 * Leaving the uid unset isn't enough either: Drupal then assigns the next
 * free uid, which is inside the range of source uids still to be migrated,
 * so a later source user is force-mapped onto the account just created and
 * overwrites it. This plugin instead allocates an explicit uid above both
 * the highest existing destination uid and the highest source uid, which
 * no later row can claim.
 *
 * A source user this migration has already imported keeps the uid it was
 * given, so re-running with --update still updates the right account.
 */
#[MigrateProcess(
  id: 'avoid_uid_collision',
  handle_multiples: FALSE,
)]
class AvoidUidCollision extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs an AvoidUidCollision process plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected MigrationInterface $migration,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $uid = (int) $value;
    if ($uid <= 0) {
      return $value;
    }

    // A source user this migration already imported keeps whatever uid it
    // was given the first time, colliding or not.
    $mapped = $this->migration->getIdMap()->lookupDestinationIds($row->getSourceIdValues());
    if (!empty($mapped[0])) {
      return (int) reset($mapped[0]);
    }

    if (!$this->entityTypeManager->getStorage('user')->load($uid)) {
      return $uid;
    }

    return $this->allocateUid();
  }

  /**
   * Returns a uid above every existing destination uid and every source uid.
   */
  protected function allocateUid(): int {
    $result = $this->entityTypeManager->getStorage('user')->getAggregateQuery()
      ->accessCheck(FALSE)
      ->aggregate('uid', 'MAX')
      ->execute();
    $destination_max = (int) ($result[0]['uid_max'] ?? 0);

    $source_max = 0;
    $source = $this->migration->getSourcePlugin();
    if ($source instanceof SqlBase) {
      $source_max = (int) $source->getDatabase()
        ->query('SELECT MAX(uid) FROM {users}')
        ->fetchField();
    }

    return max($destination_max, $source_max) + 1;
  }

}
