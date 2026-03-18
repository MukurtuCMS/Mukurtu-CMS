<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\migrate\Exception\EntityValidationException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a protocol-aware entity content migrate destination plugin.
 *
 * EntityContentBase does account switching to ensure the user can modify every
 * item in the migration, while ProtocolAwareEntityContent specifically does not
 * do that because we want the import to run as the current user, even if that
 * causes a failure. Generally speaking, import should respect entity access in
 * the same way as the rest of Drupal/Mukurtu CMS.
 *
 * This class is used in place of EntityContentBase and the relevant child
 * classes.
 *
 * @see mukurtu_import_migrate_destination_info_alter().
 */
class ProtocolAwareEntityContent extends EntityContentBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs a ProtocolAwareEntityContent.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration entity.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The storage for this entity type.
   * @param array $bundles
   *   The list of bundles this entity type has.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Session\AccountSwitcherInterface|null $account_switcher
   *   The account switcher service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null $entity_type_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, EntityStorageInterface $storage, array $bundles, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, AccountProxyInterface $current_user, ?AccountSwitcherInterface $account_switcher = NULL, ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $storage, $bundles, $entity_field_manager, $field_type_manager, $account_switcher, $entity_type_bundle_info);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): static {
    $entity_type = static::getEntityTypeId($plugin_id);
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager')->getStorage($entity_type),
      array_keys($container->get('entity_type.bundle.info')->getBundleInfo($entity_type)),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('current_user'),
      $container->get('account_switcher'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $this->rollbackAction = MigrateIdMapInterface::ROLLBACK_DELETE;
    $entity = $this->getEntity($row, $old_destination_id_values);
    if (!$entity) {
      throw new MigrateException('Unable to get entity');
    }
    assert($entity instanceof ContentEntityInterface);

    // For media entities, call prepareSave() before validation to allow
    // auto-population of the name field from the filename (for file-based
    // media) or remote title/URL (for remote media).
    if (method_exists($entity, 'prepareSave')) {
      $entity->prepareSave();
    }

    if ($this->isEntityValidationRequired($entity)) {
      $this->validateEntity($entity);
    }
    $ids = $this->save($entity, $old_destination_id_values);
    if ($this->isTranslationDestination()) {
      $ids[] = $entity->language()->getId();
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityId(Row $row): ?string {
    // ID get priority.
    if ($id = $row->getDestinationProperty($this->getKey('id'))) {
      return $id;
    }

    // UUID is next.
    if ($uuid = $row->getDestinationProperty($this->getKey('uuid'))) {
      // Need to lookup the ID from the UUID.
      return $this->getEntityIDFromUUID($uuid);
    }

    return NULL;
  }

  /**
   * Gets the entity ID from its UUID.
   *
   * @param string $uuid
   *   The UUID of the entity.
   *
   * @return string|int|null
   *   The entity ID or NULL if not found.
   */
  protected function getEntityIDFromUUID(string $uuid): mixed {
    $entities = $this->storage->loadByProperties(['uuid' => $uuid]);
    $entity = reset($entities);
    if (!$entity instanceof EntityInterface) {
      return NULL;
    }
    return $entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function validateEntity(FieldableEntityInterface $entity): void {
    // EntityContentBase uses the accountSwitcher to switch to the owner
    // account. We don't want to do that. For the Mukurtu importer the user
    // doing the import is the content creator and all checks should be run
    // using their account.

    // Add alt text validation constraint for image media during import.
    $this->addImageAltConstraint($entity);

    $violations = $entity->validate();

    if (count($violations) > 0) {
      throw new EntityValidationException($violations);
    }
  }

  /**
   * Adds alt text validation constraint to image media entities.
   *
   * This ensures that image media entities imported without alt text
   * will fail validation, maintaining accessibility standards.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being validated.
   */
  protected function addImageAltConstraint(FieldableEntityInterface $entity): void {
    // Only apply to image media entities.
    if ($entity->getEntityTypeId() !== 'media' || $entity->bundle() !== 'image') {
      return;
    }

    // Check if the field exists.
    if (!$entity->hasField('field_media_image')) {
      return;
    }

    // Get the field definition and add the constraint.
    $field_definition = $entity->getFieldDefinition('field_media_image');
    if (method_exists($field_definition, 'addPropertyConstraints')) {
      $field_definition->addPropertyConstraints('alt', ['ImageAltRequired' => []]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function save(ContentEntityInterface $entity, array $old_destination_id_values = []): array {
    if ($entity instanceof RevisionLogInterface) {
      $message = $this->migration->pluginDefinition["mukurtu_import_message"] ?? '';
      $entity->setRevisionUserId($this->currentUser->id());
      $entity->setNewRevision();
      $entity->setRevisionLogMessage($message);
    }
    return parent::save($entity, $old_destination_id_values);
  }

}
