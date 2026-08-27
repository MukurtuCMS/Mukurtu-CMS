<?php

namespace Drupal\mukurtu_export\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\mukurtu_core\Service\ParagraphEmptinessChecker;
use Drupal\mukurtu_export\Entity\CsvExporter;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\og\Og;
use InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CsvEntityFieldExportEventSubscriber implements EventSubscriberInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The paragraph emptiness checker.
   *
   * @var \Drupal\mukurtu_core\Service\ParagraphEmptinessChecker
   */
  protected $paragraphEmptinessChecker;

  /**
   * {@inheritDoc}
   */
  public function __construct(MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager, ParagraphEmptinessChecker $paragraph_emptiness_checker) {
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
    $this->paragraphEmptinessChecker = $paragraph_emptiness_checker;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      EntityFieldExportEvent::EVENT_NAME => ['exportField', 100],
    ];
  }

  /**
   * Handler for the EntityFieldExportEvent.
   *
   * @param \Drupal\mukurtu_export\Event\EntityFieldExportEvent $event
   *   The event.
   *
   * @return mixed
   */
  public function exportField(EntityFieldExportEvent $event) {
    if ($event->exporter_id != 'csv') {
      return;
    }
    $entity = $event->entity;
    $field_name = $event->field_name;
    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $config */
    $config = $this->entityTypeManager->getStorage('csv_exporter')->load($event->context['results']['config_id']);

    // Virtual field: reverse lookup of nodes that reference this media item.
    if ($entity->getEntityTypeId() === 'media' && $field_name === 'field_found_in') {
      return $this->exportFoundIn($event, $entity, $config);
    }

    // Virtual fields: community/protocol membership isn't a real field on
    // the user entity, so it's read directly from the OG membership API.
    if ($entity->getEntityTypeId() === 'user' && in_array($field_name, ['communities', 'protocols'], TRUE)) {
      return $this->exportGroupMembership($event, $entity, $field_name);
    }

    // Virtual field: account status is split across two real fields
    // (status, field_pending), exported as a single Active/Blocked/Pending
    // value to match the import side's unified 'account_status' target.
    if ($entity->getEntityTypeId() === 'user' && $field_name === 'account_status') {
      return $this->exportAccountStatus($event, $entity);
    }

    try {
      $field = $entity->get($field_name);
    } catch (InvalidArgumentException $e) {
      return NULL;
    }
    $fieldType = $field->getFieldDefinition()->getType() ?? NULL;

    if ($fieldType == 'file') {
      return $this->exportFile($event, $field, $config);
    }

    if ($fieldType == 'image') {
      return $this->exportImage($event, $field, $config);
    }

    if ($fieldType == 'cultural_protocol') {
      return $this->exportCulturalProtocol($event, $field, $config);
    }

    if ($fieldType == 'entity_reference') {
      return $this->exportEntityReference($event, $field, $config);
    }

    if ($fieldType == 'entity_reference_revisions') {
      return $this->exportEntityReferenceRevision($event, $field, $config);
    }

    if ($fieldType == 'link') {
      return $this->exportLink($event, $field, $config);
    }

    // Default handling.
    $values = $entity->get($field_name)->getValue();
    $exportValue = [];
    foreach ($values as $value) {
      $exportValue[] = is_array($value) ? reset($value) : $value;
    }
    $event->setValue($exportValue);
  }

  /**
   * Retrieves the UUID for a given entity or a concatenated type:id string if not found.
   *
   * This method attempts to load an entity of the specified type and ID and return
   * its UUID. If the entity cannot be loaded, it returns a string combining the
   * entity type and ID.
   *
   * @param string $entity_type_id
   *   The entity type ID for which to retrieve the UUID.
   * @param mixed $id
   *   The ID of the entity for which to retrieve the UUID.
   *
   * @return string
   *   The UUID of the entity if found, or a string in the format "{entity_type_id}:{id}"
   *   if the entity is not found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   If the storage handler class for the entity type does not exist.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   If the entity type does not exist.
   *
   * @protected
   */
  protected function getUUID($entity_type_id, $id) {
    if ($entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id)) {
      return $entity->uuid();
    }
    return "{$entity_type_id}:{$id}";
  }

  /**
   * Exports the entity reference field values for a given CSV export configuration.
   *
   * This method processes an entity reference field and exports the field values
   * based on the configuration provided. It supports exporting entity IDs, UUIDs,
   * usernames for user entities, and term names for taxonomy terms, depending on
   * the exporter settings. The resulting export values are then set in the event object.
   *
   * @param EntityFieldExportEvent $event
   *   The entity field export event containing context and environment for the export.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field items list being exported, which contains the entity reference data.
   * @param CsvExporter $config
   *   The configuration object that provides settings for the export, such as the
   *   desired format of the entity reference export (e.g., ID, UUID, username).
   *
   * @protected
   */
  protected function exportEntityReference(EntityFieldExportEvent $event, $field, CsvExporter $config) {
    $export = [];
    $target_type = $field->getFieldDefinition()->getSettings()['target_type'] ?? NULL;

    // Export alt text from the image field of the referenced media entity.
    if ($event->sub_field_name === 'alt' && $target_type === 'media') {
      foreach ($field->getValue() as $value) {
        if ($mid = ($value['target_id'] ?? NULL)) {
          $media = $this->entityTypeManager->getStorage('media')->load($mid);
          $alt = '';
          if ($media) {
            foreach ($media->getFields() as $media_field) {
              if ($media_field->getFieldDefinition()->getType() === 'image') {
                $image_values = $media_field->getValue();
                $alt = $image_values[0]['alt'] ?? '';
                break;
              }
            }
          }
          $export[] = $alt;
        }
      }
      $event->setValue($export);
      return;
    }

    $option = $config->getEntityReferenceSetting($target_type);
    $id_format = $config->getIdFieldSetting();

    // If the entity being exported was itself pulled in as a shallow reference,
    // do not follow its references further -- treat them all as identifier-only.
    $currentEntity = $event->entity;
    $isShallow = $event->context['results']['shallow_entity_ids'][$currentEntity->getEntityTypeId()][$currentEntity->id()] ?? FALSE;

    // Entities are loaded one-by-one per reference value. This is acceptable
    // because multi-value reference fields rarely carry hundreds of items, and
    // entity exports are not high-frequency operations.
    foreach ($field->getValue() as $value) {
      if ($id = ($value['target_id'] ?? NULL)) {
        if ($option && $target_type) {
          if ($option == 'id' || $isShallow) {
            $export[] = $id_format === 'uuid' ? $this->getUUID($target_type, $id) : $id;
            continue;
          }

          if ($option == 'entity_shallow') {
            $this->exportEntityByIdShallow($event, $target_type, $id);
            $export[] = $id_format === 'uuid' ? $this->getUUID($target_type, $id) : $id;
            continue;
          }

          if ($option == 'entity') {
            $this->exportEntityById($event, $target_type, $id);
            $export[] = $id_format === 'uuid' ? $this->getUUID($target_type, $id) : $id;
            continue;
          }

          if ($target_type == 'user' && $option == 'username') {
            if ($user = $this->entityTypeManager->getStorage($target_type)->load($id)) {
              /** @var \Drupal\user\UserInterface $user */
              $export[] = $user->getAccountName();
              continue;
            }
          }

          if ($target_type == 'taxonomy_term' && $option == 'name') {
            if ($term = $this->entityTypeManager->getStorage($target_type)->load($id)) {
              /** @var \Drupal\taxonomy\TermInterface $term */
              $export[] = $term->getName();
              continue;
            }
          }
        }

        $export[] = $id;
      }
    }
    $event->setValue($export);
  }

  /**
   * Exports the entity reference revision field values.
   *
   * This method processes an entity reference revision field and exports the field values
   * based on the configuration provided.
   *
   * @param EntityFieldExportEvent $event
   *   The entity field export event containing context and environment for the export.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field items list being exported, which contains the entity reference revision data.
   * @param CsvExporter $config
   *   The configuration object that provides settings for the export, such as the
   *   desired format of the entity reference export (e.g., ID, UUID, username).
   *
   * @protected
   */
  protected function exportEntityReferenceRevision(EntityFieldExportEvent $event, $field, CsvExporter $config) {
    $export = [];
    $target_type = $field->getFieldDefinition()->getSettings()['target_type'] ?? NULL;
    $option = $config->getEntityReferenceSetting($target_type);
    $id_format = $config->getIdFieldSetting();

    $currentEntity = $event->entity;
    $isShallow = $event->context['results']['shallow_entity_ids'][$currentEntity->getEntityTypeId()][$currentEntity->id()] ?? FALSE;

    foreach ($field->getValue() as $value) {
      if ($id = ($value['target_id'] ?? NULL)) {
        if ($target_type === 'paragraph' && $this->isParagraphEmpty($id)) {
          // Skip paragraphs auto-created by the Paragraphs widget but never
          // filled in, so they don't show up as blank rows/sheets on export.
          continue;
        }

        if ($option && $target_type) {
          if ($option == 'id' || $isShallow) {
            $export[] = $id_format === 'uuid' ? $this->getUUID($target_type, $id) : $id;
            continue;
          }

          if ($option == 'entity_shallow') {
            $this->exportEntityByIdShallow($event, $target_type, $id);
            $export[] = $id_format === 'uuid' ? $this->getUUID($target_type, $id) : $id;
            continue;
          }

          if ($option == 'entity') {
            $this->exportEntityById($event, $target_type, $id);
            $export[] = $id_format === 'uuid' ? $this->getUUID($target_type, $id) : $id;
            continue;
          }
        }
      }
    }
    $event->setValue($export);
  }

  /**
   * Checks whether a paragraph has no content of its own.
   *
   * The Paragraphs widget auto-attaches a paragraph of the sole allowed
   * bundle on new-entity forms so editors can see what fields it holds.
   * If left untouched it's normally pruned before save (see
   * PrunesEmptyParagraphsTrait), but paragraphs created before that fix
   * shipped may already exist, so this is skipped on export too.
   *
   * @param int|string $id
   *   The paragraph entity ID.
   *
   * @return bool
   *   TRUE if the paragraph exists and all of its content fields are empty.
   */
  protected function isParagraphEmpty($id): bool {
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->load($id);
    if (!$paragraph) {
      return FALSE;
    }

    return $this->paragraphEmptinessChecker->isEmpty($paragraph);
  }

  /**
   * Exports the cultural protocol field values according to the provided configuration.
   *
   * Processes the cultural protocol field from the event's context and formats it based on
   * the CSV exporter settings. It allows for the protocol identifiers to be exported as UUIDs
   * or as their original IDs, formatted and concatenated into a string representation.
   *
   * @param EntityFieldExportEvent $event
   *   The event object containing the context and settings for the current export operation.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field items list being exported, which contains the cultural protocol data.
   * @param CsvExporter $config
   *   The configuration object that provides settings for the export, such as the
   *   desired format for the ID field.
   *
   * @throws \Exception
   *   If any errors occur during the retrieval of UUIDs for the protocols.
   *
   * @protected
   */
  protected function exportCulturalProtocol(EntityFieldExportEvent $event, $field, CsvExporter $config) {
    $export = [];
    $id_format = $config->getIdFieldSetting();

    foreach ($field->getValue() as $value) {
      if ($event->sub_field_name == "sharing_setting") {
        $export[] = $value['sharing_setting'];
        continue;
      }

      $protocol_ids = explode(',', str_replace('|', '', $value['protocols']));
      if ($id_format === 'uuid') {
        $protocol_ids = array_map(fn($p) => $this->getUUID('protocol', $p), $protocol_ids);
      }

      // The combined "sharing_setting(protocols)" string is parsed back by
      // CulturalProtocolItem::setValue(), which expects a comma regardless
      // of the exporter's configured multi-value delimiter.
      if (!$event->sub_field_name) {
        $export[] = "{$value['sharing_setting']}(" . implode(',', $protocol_ids) . ")";
      }

      if ($event->sub_field_name == "protocols") {
        $export[] = implode($config->getMultivalueDelimiter(), $protocol_ids);
      }
    }
    $event->setValue($export);
  }

  /**
   * Exports the file field values based on the specified export configuration.
   *
   * This method handles the export of file fields by either packaging the file and exporting the path,
   * exporting the file entity, or by providing the file identifier (ID or UUID). The chosen method
   * depends on the configuration specified in the CsvExporter.
   *
   * @param EntityFieldExportEvent $event
   *   The export event object which provides the context and environment for the current export operation.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field items list containing the file data to be exported.
   * @param CsvExporter $config
   *   The configuration object that dictates how file fields should be exported.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if there is an issue loading or packaging the file entity.
   *
   * @protected
   */
  protected function exportFile(EntityFieldExportEvent $event, $field, CsvExporter $config) {
    $setting = $config->getFileFieldSetting();
    $export = [];

    foreach ($field->getValue() as $value) {
      if ($fid = ($value['target_id'] ?? NULL)) {
        // Export path and package binary file.
        if ($setting == 'path_with_binary') {
          $export[] = $this->packageFile($event, $fid);
          continue;
        }

        // Export whole file entity.
        if ($setting == 'file_entity') {
          if ($this->exportEntityById($event, 'file', $fid)) {
            $export[] = $fid;
            $this->packageFile($event, $fid);
            continue;
          }
        }

        // Default.
        $export[] = $config->getIdFieldSetting() === 'uuid' ? $this->getUUID('file', $fid) : $fid;
      }
    }
    $event->setValue($export);
  }

  /**
   * Exports the image field values based on the specified export configuration.
   *
   * This method exports image field data in various formats based on the exporter settings.
   * It supports packaging the image file with its path, exporting the whole file entity,
   * or exporting the file identifier (ID or UUID).
   *
   * @param EntityFieldExportEvent $event
   *   The export event object which provides the context and the necessary environment
   *   for the current export operation.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field items list containing image data to be exported.
   * @param CsvExporter $config
   *   The configuration object that dictates how image fields should be exported,
   *   including the format of the image identifier and whether to include binary data.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if there is an issue loading or packaging the image file entity.
   *
   * @protected
   */
  protected function exportImage(EntityFieldExportEvent $event, $field, CsvExporter $config) {
    // Split-column path: export only the requested sub-property so the output
    // matches the two-column format the import system expects.
    if ($event->sub_field_name === 'target_id') {
      $setting = $config->getImageFieldSetting();
      $export = [];
      foreach ($field->getValue() as $value) {
        if ($fid = ($value['target_id'] ?? NULL)) {
          if ($setting == 'path_with_binary') {
            $export[] = $this->packageFile($event, $fid);
            continue;
          }
          if ($setting == 'file_entity') {
            if ($this->exportEntityById($event, 'file', $fid)) {
              $export[] = $fid;
              $this->packageFile($event, $fid);
              continue;
            }
          }
          $export[] = $config->getIdFieldSetting() === 'uuid'
            ? $this->getUUID('file', $fid)
            : $fid;
        }
      }
      $event->setValue($export);
      return;
    }
    if ($event->sub_field_name === 'alt') {
      $export = [];
      foreach ($field->getValue() as $value) {
        $export[] = $value['alt'] ?? '';
      }
      $event->setValue($export);
      return;
    }

    // Legacy single-column path (no sub_field_name): keeps the existing
    // markdown format for backward compatibility with saved exporters that
    // still reference the bare field name.
    $setting = $config->getImageFieldSetting();
    $export = [];

    foreach ($field->getValue() as $value) {
      if ($fid = ($value['target_id'] ?? NULL)) {
        // Export path and package binary file.
        if ($setting == 'path_with_binary') {
          $export[] = $this->packageFile($event, $fid);
          continue;
        }

        // Export whole file entity.
        if ($setting == 'file_entity') {
          if($this->exportEntityById($event, 'file', $fid)) {
            $export[] = $fid;
            $this->packageFile($event, $fid);
            continue;
          }
        }

        // Default: build the image export value in markdown format:
        // ![alt text](<image fid or uuid> "title")
        $exportId = $config->getIdFieldSetting() === 'uuid' ? $this->getUUID('file', $fid) : $fid;
        $alt = $value['alt'] ?? NULL;
        $title = $value['title'] ?? NULL;

        $export[] = '![' . $alt . '](' . strval($exportId) . ' "' . $title . '")';
      }
    }
    $event->setValue($export);
  }

  /**
   * Exports link field values in markdown format.
   *
   * @param EntityFieldExportEvent $event
   *   The export event object which provides the context and the necessary environment
   *   for the current export operation.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field items list containing link data to be exported.
   *
   * @protected
   */
  protected function exportLink(EntityFieldExportEvent $event, $field) {
    // Link field values are wrapped in another array like this:
    // values => [
    //    value => [
    //      'uri' => 'https://google.com',
    //      'title' => 'Google',
    //      'options => [...]
    //    ]
    // ]
    // Link options attribute is an internal Drupal field we don't need, so we
    // don't include it in export.
    $links = $field->getValue() ?? NULL;
    $exportValue = [];
    if ($links) {
      foreach ($links as $link) {
        $title = $link['title'];
        $uri = $link['uri'];
        $exportValue[] = "[$title]($uri)";
      }
    }
    $event->setValue($exportValue);
  }

  /**
   * Loads an entity by its ID and triggers its export if found.
   *
   * This method attempts to load an entity of the specified type and ID. If the entity
   * is successfully loaded, it is passed to the export event to handle additional export
   * logic and is then returned. If no entity is found, NULL is returned.
   *
   * @param EntityFieldExportEvent $event
   *   The export event object which provides context for the export operation.
   * @param string $entity_type_id
   *   The type of entity to load, such as 'node' or 'user'.
   * @param mixed $id
   *   The unique identifier for the entity to load.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity object if found, or NULL otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler class for the entity type does not exist.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type does not exist.
   *
   * @protected
   */
  protected function exportEntityById(EntityFieldExportEvent $event, $entity_type_id, $id): EntityInterface|null {
    if ($entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id)) {
      $event->exportAdditionalEntity($entity);
      return $entity;
    }
    return NULL;
  }

  /**
   * Queues an entity for export and marks it as shallow.
   *
   * When an entity is marked shallow, its own entity reference fields will be
   * exported as identifiers only -- their references will not be followed further.
   */
  protected function exportEntityByIdShallow(EntityFieldExportEvent $event, $entity_type_id, $id): EntityInterface|null {
    if ($entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id)) {
      $event->exportAdditionalEntity($entity);
      $event->context['results']['shallow_entity_ids'][$entity_type_id][$id] = $id;
      return $entity;
    }
    return NULL;
  }

  /**
   * Prepares a file for export and returns the packaged file path.
   *
   * This method loads a file entity based on the provided file ID (fid) and then
   * prepares it for export by packaging it into a predefined file structure.
   * If the file is successfully loaded and packaged, the path to the packaged file
   * is returned. If no file is found, NULL is returned.
   *
   * @param EntityFieldExportEvent $event
   *   The export event object which provides context for the export operation.
   * @param int|string $fid
   *   The unique identifier for the file to be packaged.
   *
   * @return string|null
   *   The path to the packaged file if the file entity is found, or NULL otherwise.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if there is an issue loading the file entity.
   *
   * @protected
   */
  protected function packageFile(EntityFieldExportEvent $event, $fid): string|null {
    if ($file = $this->entityTypeManager->getStorage('file')->load($fid)) {
      $folder = str_contains($event->field_name, 'thumbnail') ? 'thumbnails' : 'files';
      $event->packageFile($file->getFileUri(), sprintf("%s/%s", $folder, $file->getFilename()));
      return $file->getFilename();
    }
    return NULL;
  }

  /**
   * Exports the virtual "found in" field for media entities.
   *
   * Performs a reverse entity query to find all nodes that reference this media
   * item via field_media_assets and returns their IDs or UUIDs.
   *
   * @param EntityFieldExportEvent $event
   *   The export event object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The media entity being exported.
   * @param CsvExporter $config
   *   The export configuration.
   *
   * @protected
   */
  protected function exportFoundIn(EntityFieldExportEvent $event, EntityInterface $entity, CsvExporter $config) {
    $nids = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('field_media_assets', $entity->id())
      ->accessCheck(TRUE)
      ->execute();

    $export = [];
    $id_format = $config->getIdFieldSetting();

    if ($id_format === 'uuid') {
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
      foreach ($nodes as $node) {
        $export[] = $node->uuid();
      }
    }
    else {
      $export = array_values($nids);
    }

    $event->setValue($export);
  }

  /**
   * Exports a user's community or protocol memberships and roles.
   *
   * Serializes to the same "Name>role1|role2" format the import side's
   * GroupMembershipLookup process plugin expects (the CSV::export() plugin
   * joins the returned array with the exporter's configured
   * multivalue_delimiter, matching the delimiter import's explode step
   * reads by default), so an export/re-import round-trip reproduces the
   * same membership state.
   *
   * @protected
   */
  protected function exportGroupMembership(EntityFieldExportEvent $event, EntityInterface $entity, string $field_name): void {
    $bundle = $field_name === 'communities' ? 'community' : 'protocol';
    $memberships = array_filter(Og::getMemberships($entity), fn($membership) => $membership->getGroupBundle() === $bundle);

    $export = [];
    foreach ($memberships as $membership) {
      $group = $membership->getGroup();
      if (!$group) {
        continue;
      }
      $roles = array_values(array_filter(
        array_map(fn($role) => $role->getName(), $membership->getRoles()),
        fn($role_name) => !in_array($role_name, ['member', 'non-member'], TRUE),
      ));
      // Strip delimiter characters defensively rather than invent an
      // escaping scheme, matching exportCulturalProtocol()'s precedent of
      // stripping conflicting delimiter characters from exported values.
      // ':' is deliberately not stripped -- '>' is the compound-value
      // delimiter now, and colons are common in real group names.
      $label = str_replace(['>', '|', ';'], '', $group->label());
      $export[] = $roles ? "{$label}>" . implode('|', $roles) : $label;
    }

    $event->setValue($export);
  }

  /**
   * Exports a user's account status as a single Active/Blocked/Pending
   * value.
   *
   * Mirrors the same three-state model the interactive account form
   * presents (see FormHooks::userStatusPreSaveSubmit()) and
   * MukurtuUserListBuilder::buildRow()'s admin listing, and matches the
   * format the import side's AccountStatusLookup process plugin expects,
   * so an export/re-import round trip reproduces the same account status.
   *
   * @protected
   */
  protected function exportAccountStatus(EntityFieldExportEvent $event, EntityInterface $entity): void {
    if ($entity->isActive()) {
      $status = 'Active';
    }
    elseif ($entity->hasField('field_pending') && $entity->get('field_pending')->value) {
      $status = 'Pending';
    }
    else {
      $status = 'Blocked';
    }

    $event->setValue([$status]);
  }

}
