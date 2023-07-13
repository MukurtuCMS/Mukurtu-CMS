<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldType;

Use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\mukurtu_local_contexts\Event\LocalContextsProjectReferenceUpdatedEvent;

/**
 * Defines the 'local_contexts_project' field type.
 *
 * @FieldType(
 *   id = "local_contexts_project",
 *   label = @Translation("Local Contexts Project"),
 *   default_widget = "local_contexts_project",
 *   default_formatter = "string"
 * )
 */
class LocalContextsProjectItem extends StringItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings()
  {
    return [
      'max_length' => 128,
      'is_ascii' => TRUE,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritDoc}
   */
  public function postSave($update) {
    $event_dispatcher = \Drupal::service('event_dispatcher');
    if ($id = $this->getValue()['value'] ?? NULL) {
      $event = new LocalContextsProjectReferenceUpdatedEvent($id);
      $event_dispatcher->dispatch($event, LocalContextsProjectReferenceUpdatedEvent::EVENT_NAME);
    }
  }

}
