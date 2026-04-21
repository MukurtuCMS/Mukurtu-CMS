<?php

namespace Drupal\search_api\Form;

use Drupal\config_readonly\ReadOnlyFormEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Checks if the given form should be read-only or not.
 */
class ReadOnlyFormSubscriber implements EventSubscriberInterface {

  /**
   * Form IDs to mark as editable.
   */
  protected const ALLOWED_CONFIRM_FORM_IDS = [
    'search_api_index_break_lock_confirm',
    'search_api_index_clear_form',
    'search_api_index_rebuild_tracker_form',
    'search_api_index_reindex_form',
    'search_api_server_clear_form',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    if (class_exists(ReadOnlyFormEvent::class)) {
      $events[ReadOnlyFormEvent::NAME][] = ['onFormAlter', 200];
    }
    return $events;
  }

  /**
   * Reacts to the read-only form event.
   *
   * @param \Drupal\config_readonly\ReadOnlyFormEvent $event
   *   The event.
   */
  public function onFormAlter(ReadOnlyFormEvent $event): void {
    $form_object = $event->getFormState()->getFormObject();

    if (in_array($form_object->getFormId(), static::ALLOWED_CONFIRM_FORM_IDS)) {
      $event->markFormEditable();
    }
  }

}
