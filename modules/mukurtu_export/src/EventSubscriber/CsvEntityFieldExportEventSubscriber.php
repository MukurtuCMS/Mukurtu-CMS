<?php

namespace Drupal\mukurtu_export\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\mukurtu_export\Entity\CsvExporter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;

class CsvEntityFieldExportEventSubscriber implements EventSubscriberInterface
{

  protected $messenger;
  protected $entityTypeManager;

  public function __construct(MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager) {
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents()
  {
    return [
      EntityFieldExportEvent::EVENT_NAME => ['exportField', 100],
    ];
  }

  public function exportField(EntityFieldExportEvent $event)
  {
    if ($event->exporter_id != 'csv') {
      return;
    }
    $entity = $event->entity;
    $field_name = $event->field_name;
    /** @var \Drupal\mukurtu_export\Entity\CsvExporter $config */
    $config = $this->entityTypeManager->getStorage('csv_exporter')->load($event->context['results']['config_id']);

    $field = $entity->get($field_name);
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

    // Default handling.
    $values = $entity->get($field_name)->getValue();
    $exportValue = [];
    foreach ($values as $value) {
      $exportValue[] = is_array($value) ? reset($value) : $value;
    }
    $event->setValue($exportValue);
  }

  protected function getUUID($entity_type_id, $id) {
    if($entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id)) {
      return $entity->uuid();
    }
    return "{$entity_type_id}:{$id}";
  }
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

  protected function exportCulturalProtocol(EntityFieldExportEvent $event, $field, CsvExporter $config) {
    $export = [];
    $id_format = $config->getIdFieldSetting();

    foreach ($field->getValue() as $value) {
      $protocols = str_replace('|', '', $value['protocols']);
      if ($id_format === 'uuid') {
        $ids = explode(',', $protocols);
        $uuids = array_map(fn($p) => $this->getUUID('protocol', $p), $ids);
        $protocols = implode(',', $uuids);
      }
      $export[] = "{$value['sharing_setting']}({$protocols})";
    }
    $event->setValue($export);
  }


  protected function exportFile(EntityFieldExportEvent $event, $field, CsvExporter $config)
  {
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

  protected function exportEntityById(EntityFieldExportEvent $event, $entity_type_id, $id): EntityInterface|null {
    if ($entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id)) {
      $event->exportAdditionalEntity($entity);
      return $entity;
    }
    return NULL;
  }

  protected function packageFile(EntityFieldExportEvent $event, $fid): string|null {
    if ($file = $this->entityTypeManager->getStorage('file')->load($fid)) {
      $packagedFilePath = sprintf("%s/%s/%s", "files", $fid, $file->getFilename());
      $event->packageFile($file->getFileUri(), $packagedFilePath);
      return $packagedFilePath;
    }
    return NULL;
  }

}
