<?php

namespace Drupal\message;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Render controller for Messages.
 */
class MessageViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL) {
    $build = parent::view($entity, $view_mode, $langcode);

    /** @var \Drupal\message\Entity\Message $entity  */
    $partials = $entity->getText($langcode);

    // Get the partials the user selected for the current view mode.
    $extra_fields = EntityViewDisplay::load('message.' . $entity->bundle() . '.' . $build['#view_mode']);
    if (!$extra_fields instanceof EntityViewDisplayInterface) {
      $extra_fields = EntityViewDisplay::create([
        'targetEntityType' => 'message',
        'bundle' => $entity->bundle(),
        'mode' => $view_mode,
        'status' => TRUE,
      ]);
    }

    foreach ($extra_fields->getComponents() as $field_name => $settings) {
      // The partials are keyed with `partial_X`, check if that is set.
      if (strpos($field_name, 'partial_') === 0) {
        [, $delta] = explode('_', $field_name);
        if (isset($partials[$delta])) {
          $build[$field_name]['#markup'] = $partials[$delta];
        }
      }
      else {
        // This is another field.
        $display = $this->getSingleFieldDisplay($entity, $field_name, $settings);
        $build += $display->build($entity);
      }
    }

    return $build;
  }

}
