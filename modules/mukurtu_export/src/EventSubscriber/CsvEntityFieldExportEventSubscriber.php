<?php

namespace Drupal\mukurtu_export\EventSubscriber;

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

    if ($fieldType == 'image') {
      return $this->exportImage($event, $field, $config);
    }

    // Default handling.
    $values = $entity->get($field_name)->getValue();
    $exportValue = [];
    foreach ($values as $value) {
      $exportValue[] = is_array($value) ? reset($value) : $value;
    }
    $event->setValue($exportValue);
  }

  protected function exportImage(EntityFieldExportEvent $event, $field, CsvExporter $config) {
    $idSetting = $config->getImageFieldSetting();
    $export = [];

    foreach ($field->getValue() as $value) {
      if ($fid = ($value['target_id'] ?? NULL)) {
        if ($idSetting == 'id') {
          $export[] = $fid;
          continue;
        }

        // Export path and package binary file.
        $export[] = $this->packageFile($event, $fid);
      }
    }
    $event->setValue($export);
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
