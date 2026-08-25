<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\destination;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Per-row created/updated details accumulated since the last drain.
   *
   * Drained by getAndClearRowResults() so ImportBatchExecutable can log
   * true entity-level created/updated status (see import()) instead of
   * relying on migrate's own ID-map bookkeeping, which tracks whether this
   * migration has seen the source row before, not whether the destination
   * entity itself pre-existed.
   *
   * @var array
   */
  protected array $rowResults = [];

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountSwitcherInterface|null $account_switcher
   *   The account switcher service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null $entity_type_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, EntityStorageInterface $storage, array $bundles, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, AccountProxyInterface $current_user, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time, ?AccountSwitcherInterface $account_switcher = NULL, ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $storage, $bundles, $entity_field_manager, $field_type_manager, $account_switcher, $entity_type_bundle_info);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): static {
    $entity_type = static::getEntityTypeId($plugin_id);
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $entity_type_manager->getStorage($entity_type),
      array_keys($container->get('entity_type.bundle.info')->getBundleInfo($entity_type)),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('current_user'),
      $entity_type_manager,
      $container->get('datetime.time'),
      $container->get('account_switcher'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    // Extract and clear */alt destination properties for single-value
    // entity_reference-to-media fields before entity building. Entity
    // reference fields have no 'alt' sub-property, so passing these to the
    // entity storage would throw. We apply them post-save instead.
    $media_alt_updates = $this->extractAndClearMediaAltUpdates($row);

    $this->rollbackAction = MigrateIdMapInterface::ROLLBACK_DELETE;
    $entity = $this->getEntity($row, $old_destination_id_values);
    if (!$entity) {
      throw new MigrateException('Unable to get entity');
    }
    assert($entity instanceof ContentEntityInterface);

    // A new entity with no mapped/available "created" value is left with an
    // empty created field: unlike "changed", core has no fallback default
    // for it, so it would otherwise fail to save with a NOT NULL constraint
    // violation on the created column.
    if ($entity->isNew() && $entity->hasField('created') && $entity->get('created')->isEmpty()) {
      $entity->set('created', $this->time->getRequestTime());
    }

    // For media entities, call prepareSave() before validation to allow
    // auto-population of the name field from the filename (for file-based
    // media) or remote title/URL (for remote media).
    if (method_exists($entity, 'prepareSave')) {
      $entity->prepareSave();
    }

    // Determine if this is a create or update operation. Captured
    // unconditionally (not just for the access check below) so it reflects
    // ground truth for row-result reporting regardless of the uid-1 bypass.
    $was_new = $entity->isNew();

    // Skip access checks for user 1. The initial v3-to-v4 site migration is
    // restricted to user 1 (see MukurtuMigrateAccessCheck) and runs before OG
    // memberships are migrated, so protocol/community access checks would
    // incorrectly deny entity creation.
    if ((int) $this->currentUser->id() !== 1) {
      $operation = $was_new ? 'create' : 'update';

      // Check entity access for the current user.
      $access = $entity->access($operation, $this->currentUser, TRUE);
      if (!$access->isAllowed()) {
        $reason = $access->getReason();
        throw new MigrateException(
          sprintf('The current user does not have %s access for this %s (ID: %s).%s',
            $operation,
            mb_strtolower((string)$entity->getEntityType()->getLabel()),
            $was_new ? 'new' : $entity->id(),
            $reason ? ' ' . $reason : '',
          )
        );
      }
    }

    if ($this->isEntityValidationRequired($entity)) {
      $this->validateEntity($entity);
    }
    $ids = $this->save($entity, $old_destination_id_values);

    if (!empty($media_alt_updates)) {
      $this->applyMediaEntityAltText($entity, $media_alt_updates);
    }

    if ($this->isTranslationDestination()) {
      $ids[] = $entity->language()->getId();
    }

    // Recorded last, after every step that could still throw for this row,
    // so a row that fails during post-save processing (e.g. applying media
    // alt text) isn't also recorded here as a success.
    $this->rowResults[] = [
      'source_id' => implode(':', $row->getSourceIdValues()),
      'status' => $was_new ? 'created' : 'updated',
      'entity_type_id' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'label' => (string) $entity->label(),
      'url' => $entity->hasLinkTemplate('canonical') ? $entity->toUrl()->toString() : NULL,
    ];

    return $ids;
  }

  /**
   * Returns and clears the created/updated details recorded since the last
   * call.
   *
   * @return array
   *   A list of associative arrays, each with keys: source_id, status
   *   ('created' or 'updated'), entity_type_id, bundle, label, and url
   *   (nullable).
   */
  public function getAndClearRowResults(): array {
    $row_results = $this->rowResults;
    $this->rowResults = [];
    return $row_results;
  }

  /**
   * Extracts and clears /alt destination properties for entity_reference-to-media
   * fields, returning the field-name => alt-text map for post-save processing.
   */
  protected function extractAndClearMediaAltUpdates(Row $row): array {
    $updates = [];
    $entity_type_id = $this->storage->getEntityTypeId();
    $bundle_key = $this->getKey('bundle');
    $bundle = $bundle_key
      ? ($row->getDestinationProperty($bundle_key) ?? $entity_type_id)
      : $entity_type_id;

    $field_defs = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

    foreach ($row->getDestination() as $dest_key => $dest_value) {
      if (!str_ends_with($dest_key, '/alt') || empty($dest_value)) {
        continue;
      }
      $field_name = substr($dest_key, 0, strrpos($dest_key, '/'));
      if (!isset($field_defs[$field_name])) {
        continue;
      }
      $field_def = $field_defs[$field_name];
      if ($field_def->getType() === 'entity_reference'
        && $field_def->getSetting('target_type') === 'media'
        && $field_def->getFieldStorageDefinition()->getCardinality() === 1) {
        $updates[$field_name] = $dest_value;
        $row->setDestinationProperty($dest_key, NULL);
      }
    }

    return $updates;
  }

  /**
   * Updates the alt text on the image field of referenced media entities.
   */
  protected function applyMediaEntityAltText(ContentEntityInterface $entity, array $media_alt_updates): void {
    $media_storage = $this->entityTypeManager->getStorage('media');

    foreach ($media_alt_updates as $field_name => $alt_text) {
      if (!$entity->hasField($field_name)) {
        continue;
      }
      $target_id = $entity->get($field_name)->target_id;
      if (!$target_id) {
        continue;
      }
      $media = $media_storage->load($target_id);
      if (!$media) {
        continue;
      }
      foreach ($media->getFields() as $media_field_name => $media_field) {
        if ($media_field->getFieldDefinition()->getType() !== 'image') {
          continue;
        }
        $vals = $media_field->getValue();
        if (!empty($vals)) {
          $vals[0]['alt'] = $alt_text;
          $media->get($media_field_name)->setValue($vals);
          $media->save();
        }
        break;
      }
    }
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
      $lines = [];
      foreach ($violations as $violation) {
        $lines[] = $this->formatViolationMessage($violation, $entity);
      }
      throw new MigrateException(implode("\n", $lines));
    }
  }

  /**
   * Formats a single validation violation using the field's actual label.
   *
   * Replaces core's raw "field_name.delta.column=message" property path
   * with the human-readable field label, e.g. "Title: This value should
   * not be null." instead of "title.0.value=This value should not be
   * null.". For composite fields with more than one independently
   * validated sub-property (e.g. Cultural Protocols' "protocols" and
   * "sharing_setting" columns), the sub-property's own label is appended
   * so two different failures on the same field aren't reported
   * identically, e.g. "Cultural Protocols (Sharing Setting): ...".
   */
  protected function formatViolationMessage(ConstraintViolationInterface $violation, FieldableEntityInterface $entity): string {
    $property_path = $violation->getPropertyPath();
    if ($property_path === '') {
      // Entity-level constraints (not tied to a specific field) have no
      // property path to translate into a field label.
      return (string) $violation->getMessage();
    }
    $parts = explode('.', $property_path);
    $field_name = $parts[0];
    $field_definition = $field_name ? $entity->getFieldDefinition($field_name) : NULL;
    $label = $field_definition ? $field_definition->getLabel() : $property_path;

    if ($field_definition && $this->hasMultipleValidatedProperties($field_definition)) {
      // A violation on a specific sub-property names it directly in its
      // path (field_name.delta.property). A violation on the field/item as
      // a whole - e.g. a composite field's own required-empty check, which
      // for a field like Cultural Protocols fires whenever ANY of its
      // required sub-properties is empty, not just its main one - carries
      // no property segment, so the entity's current value has to be
      // inspected to find which required sub-property is actually empty.
      $property_name = $parts[2] ?? $this->findEmptyRequiredProperty($entity, $field_name, $field_definition);
      $property_definition = $property_name ? $field_definition->getItemDefinition()->getPropertyDefinition($property_name) : NULL;
      if ($property_definition) {
        $label = sprintf('%s (%s)', $label, $property_definition->getLabel());
      }
    }

    return sprintf('%s: %s', $label, $violation->getMessage());
  }

  /**
   * Finds which required sub-property of a field's first item is empty.
   *
   * Used when a violation is reported against a composite field as a whole
   * rather than one of its sub-properties by name. Falls back to the
   * field's main property if no item exists yet or none of its required
   * properties are empty.
   */
  protected function findEmptyRequiredProperty(FieldableEntityInterface $entity, string $field_name, FieldDefinitionInterface $field_definition): ?string {
    $item_definition = $field_definition->getItemDefinition();
    $items = $entity->get($field_name);
    $item = $items->count() > 0 ? $items->get(0) : NULL;
    if ($item) {
      foreach ($item_definition->getPropertyDefinitions() as $property_name => $property_definition) {
        if ($property_definition->isComputed() || !$property_definition->isRequired()) {
          continue;
        }
        $value = $item->get($property_name)->getValue();
        if ($value === NULL || $value === '') {
          return $property_name;
        }
      }
    }
    return $item_definition->getMainPropertyName();
  }

  /**
   * Determines whether a field's item type has more than one independently
   * validated sub-property.
   *
   * Most field types (string, entity_reference, etc.) have only one
   * property that is actually required or constrained - entity_reference's
   * "entity" property, for instance, is computed from "target_id" rather
   * than validated on its own. Fields like Cultural Protocols
   * (CulturalProtocolItem), whose "protocols" and "sharing_setting" columns
   * are each independently required/constrained, are the ones where a bare
   * field label doesn't say which sub-property actually failed.
   */
  protected function hasMultipleValidatedProperties(FieldDefinitionInterface $field_definition): bool {
    $validated_count = 0;
    foreach ($field_definition->getItemDefinition()->getPropertyDefinitions() as $property_definition) {
      if ($property_definition->isComputed()) {
        continue;
      }
      if ($property_definition->isRequired() || $property_definition->getConstraints()) {
        $validated_count++;
      }
    }
    return $validated_count > 1;
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
  protected function updateEntity(EntityInterface $entity, Row $row) {
    // Skip access checks for user 1. See the corresponding check in import()
    // for rationale.
    if ((int) $this->currentUser->id() !== 1) {
      return parent::updateEntity($entity, $row);
    }

    // Check update access against the original, unmodified entity before the
    // parent applies imported field values. This prevents a user from
    // bypassing access control by importing their own protocol onto content
    // they cannot otherwise edit. Without this check, the protocol change
    // would already be applied in memory by the time the post-edit access
    // check runs in import(), causing it to incorrectly pass.
    $access = $entity->access('update', $this->currentUser, TRUE);
    if (!$access->isAllowed()) {
      $reason = $access->getReason();
      throw new MigrateException(
        sprintf('The current user does not have access to update this %s (ID: %s).%s',
          mb_strtolower((string)$entity->getEntityType()->getLabel()),
          $entity->id(),
          $reason ? ' ' . $reason : '',
        )
      );
    }

    return parent::updateEntity($entity, $row);
  }

  /**
   * {@inheritdoc}
   */
  protected function save(ContentEntityInterface $entity, array $old_destination_id_values = []): array {
    $is_update = !$entity->isNew();
    if ($entity instanceof RevisionLogInterface) {
      $message = $this->migration->pluginDefinition["mukurtu_import_message"] ?? '';
      $entity->setRevisionUserId($this->currentUser->id());
      $entity->setNewRevision();
      $entity->setRevisionLogMessage($message);
    }
    // EntityContentBase::save() sets the entity as syncing before saving,
    // which suppresses ChangedItem::preSave()'s automatic bump of the
    // "changed" timestamp on update. Set it explicitly so imported edits are
    // treated the same as manual edits (e.g. for "recent content" sorting).
    if ($is_update && $entity instanceof EntityChangedInterface) {
      // Use the current wall-clock time rather than the request time so
      // each row in a multi-row import batch gets a distinct, monotonically
      // increasing "changed" value, matching the relative order they were
      // actually processed in.
      $entity->setChangedTime($this->time->getCurrentTime());
    }
    return parent::save($entity, $old_destination_id_values);
  }

}
