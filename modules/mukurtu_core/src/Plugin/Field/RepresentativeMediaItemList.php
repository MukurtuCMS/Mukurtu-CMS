<?php

namespace Drupal\mukurtu_core\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * TermStatusItemList class to generate a computed field.
 */
class RepresentativeMediaItemList extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());

    $media_fields = ['field_media_assets'];

    // Check available media fields. Find the first media item that is
    // accessible to the user and return that. This seems very heavy
    // at first glance but 99% of the time in practice this will load
    // exactly one media item because the protocol between the content
    // and the media are nearly always the same.
    foreach ($media_fields as $media_field) {
      if ($entity->hasField($media_field)) {
        foreach ($entity->{$media_field} as $media_ref) {
          $media = \Drupal::entityTypeManager()->getStorage('media')->load($media_ref->target_id);
          if ($media->access('view', $user)) {
            $this->list[0] = $this->createItem(0, $media->id());
            return;
          }
        }
      }
    }

    // If the item has no media, use the default image.
    $default_image = \Drupal::config('mukurtu.settings')->get('mukurtu_default_image');
    if ($default_image) {
      $this->list[0] = $this->createItem(0, $default_image);
    }
  }

}
