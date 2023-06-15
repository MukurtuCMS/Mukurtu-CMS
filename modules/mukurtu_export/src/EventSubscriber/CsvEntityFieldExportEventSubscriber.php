<?php

namespace Drupal\mukurtu_export\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\mukurtu_export\Event\EntityFieldExportEvent;

class CsvEntityFieldExportEventSubscriber implements EventSubscriberInterface
{

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents()
  {
    return [
      EntityFieldExportEvent::EVENT_NAME => ['exportField', -100],
    ];
  }

  public function exportField(EntityFieldExportEvent $event)
  {
    if ($event->exporter_id != 'csv') {
      return;
    }
    $entity = $event->entity;
    $field_name = $event->field_name;

    $field = $entity->get($field_name);
    $fieldType = $field->getFieldDefinition()->getType() ?? NULL;

    $values = $entity->get($field_name)->getValue();
    $exportValue = [];
    foreach ($values as $value) {
      $exportValue[] = is_array($value) ? reset($value) : $value;
    }

    $event->setValue($exportValue);
  }

}
