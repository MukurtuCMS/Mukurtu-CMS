<?php

namespace Drupal\mukurtu_export\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\mukurtu_export\Entity\CsvExporter;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
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
   * {@inheritDoc}
   */
  public function __construct(MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager) {
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
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
    $option = $config->getEntityReferenceSetting($target_type);
    $id_format = $config->getIdFieldSetting();

    foreach ($field->getValue() as $value) {
      if ($id = ($value['target_id'] ?? NULL)) {
        if ($option && $target_type) {
          if ($option == 'id') {
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

    foreach ($field->getValue() as $value) {
      if ($id = ($value['target_id'] ?? NULL)) {
        if ($option && $target_type) {
          if ($option == 'id') {
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

      $protocols = str_replace('|', '', $value['protocols']);
      if ($id_format === 'uuid') {
        $ids = explode(',', $protocols);
        $uuids = array_map(fn($p) => $this->getUUID('protocol', $p), $ids);
        $protocols = implode(',', $uuids);
      }

      if (!$event->sub_field_name) {
        $export[] = "{$value['sharing_setting']}({$protocols})";
      }

      if ($event->sub_field_name == "protocols") {
        $export[] = $protocols;
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

        // Default.
        $export[] = $config->getIdFieldSetting() === 'uuid' ? $this->getUUID('file', $fid) : $fid;
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
      $packagedFilePath = sprintf("%s/%s/%s", "files", $fid, $file->getFilename());
      $event->packageFile($file->getFileUri(), $packagedFilePath);
      return $packagedFilePath;
    }
    return NULL;
  }

}
