<?php

namespace Drupal\mukurtu_export\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;
use Drupal\mukurtu_export\Entity\CsvExporter;

class CsvEntityFieldExportEventSubscriber implements EventSubscriberInterface
{

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
    $config = \Drupal::entityTypeManager()->getStorage('csv_exporter')->load($event->context['results']['config_id']);

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

  protected function exportEntityReference(EntityFieldExportEvent $event, $field, CsvExporter $config) {
    $export = [];
    $target_type = $field->getFieldDefinition()->getSettings()['target_type'] ?? NULL;
    $option = $config->getEntityReferenceSetting($target_type);

    foreach ($field->getValue() as $value) {
      if ($id = ($value['target_id'] ?? NULL)) {
        if ($option && $target_type) {
          if ($option == 'id') {
            $export[] = $id;
            continue;
          }

          if ($option == 'entity') {
            $this->exportEntityById($event, $target_type, $id);
            $export[] = $id;
            continue;
          }

          if ($target_type == 'user' && $option == 'username') {
            if ($user = \Drupal::entityTypeManager()->getStorage($target_type)->load($id)) {
              /** @var \Drupal\user\UserInterface $user */
              $export[] = $user->getAccountName();
            }
          }

          if ($target_type == 'taxonomy_term' && $option == 'name') {
            if ($term = \Drupal::entityTypeManager()->getStorage($target_type)->load($id)) {
              /** @var \Drupal\taxonomy\TermInterface $term */
              $export[] = $term->getName();
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

    foreach ($field->getValue() as $value) {
      $protocols = str_replace('|', '', $value['protocols']);
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
        $export[] = $fid;
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
        $export[] = $fid;
      }
    }
    $event->setValue($export);
  }

  protected function exportEntityById(EntityFieldExportEvent $event, $entity_type_id, $id): EntityInterface|null {
    if ($entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($id)) {
      $event->exportAdditionalEntity($entity);
      return $entity;
    }
    return NULL;
  }

  protected function packageFile(EntityFieldExportEvent $event, $fid): string|null {
    if ($file = \Drupal::entityTypeManager()->getStorage('file')->load($fid)) {
      $packagedFilePath = sprintf("%s/%s/%s", "files", $fid, $file->getFilename());
      $event->packageFile($file->getFileUri(), $packagedFilePath);
      return $packagedFilePath;
    }
    return NULL;
  }

}
