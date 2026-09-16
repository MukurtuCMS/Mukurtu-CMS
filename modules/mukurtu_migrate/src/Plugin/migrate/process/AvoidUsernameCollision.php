<?php

declare(strict_types=1);

namespace Drupal\mukurtu_migrate\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renames a migrated user whose name is already taken on this site.
 *
 * Drupal keeps a unique index on the username, so importing a Mukurtu 3 user
 * whose name matches an account that already exists here fails the row with
 * "Integrity constraint violation: Duplicate entry ... for key user__name"
 * and the account is never created. That is easy to hit: a Mukurtu 3 site
 * often has its own "admin" account that isn't user 1, and this site's
 * administrator may well be named after someone who also has a Mukurtu 3
 * account.
 *
 * The account that already exists here keeps its name. The incoming user
 * gets a numeric suffix, so their content still has an owner to be
 * attributed to. A row this migration has already imported keeps the name it
 * was given, so re-running with --update doesn't rename anyone twice.
 */
#[MigrateProcess(
  id: 'avoid_username_collision',
  handle_multiples: FALSE,
)]
class AvoidUsernameCollision extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs an AvoidUsernameCollision process plugin.
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
    $name = trim((string) $value);
    if ($name === '') {
      return $value;
    }

    $own_uid = $this->destinationUid($row);
    if (!$this->isTaken($name, $own_uid)) {
      return $name;
    }

    $renamed = $this->allocateName($name, $own_uid);
    $migrate_executable->saveMessage(sprintf('The username "%s" already belongs to an account on this site, so the migrated user was renamed to "%s".', $name, $renamed), MigrationInterface::MESSAGE_INFORMATIONAL);

    return $renamed;
  }

  /**
   * Returns the uid this row has already been migrated to, if any.
   */
  protected function destinationUid(Row $row): ?int {
    $mapped = $this->migration->getIdMap()->lookupDestinationIds($row->getSourceIdValues());
    if (empty($mapped[0])) {
      return NULL;
    }
    return (int) reset($mapped[0]);
  }

  /**
   * Whether a name belongs to an account other than this row's own.
   */
  protected function isTaken(string $name, ?int $own_uid): bool {
    $accounts = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['name' => $name]);
    foreach ($accounts as $account) {
      assert($account instanceof UserInterface);
      if ($own_uid === NULL || (int) $account->id() !== $own_uid) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the first free "<name>_<n>", truncated to fit Drupal's limit.
   */
  protected function allocateName(string $name, ?int $own_uid): string {
    for ($suffix = 1;; $suffix++) {
      $tail = '_' . $suffix;
      $base = mb_substr($name, 0, UserInterface::USERNAME_MAX_LENGTH - mb_strlen($tail));
      $candidate = $base . $tail;
      if (!$this->isTaken($candidate, $own_uid)) {
        return $candidate;
      }
    }
  }

}
