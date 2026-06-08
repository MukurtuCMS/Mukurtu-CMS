<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Field;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * RepresentativeMediaItemList class to generate a computed field.
 */
class RepresentativeMediaItemList extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $entity = $this->getEntity();

    $media_fields = ['field_thumbnail', 'field_media_assets', 'field_collection_image', 'field_word_list_image'];

    // Check available media fields. Find the first media item that is
    // accessible to the user and return that. This seems very heavy
    // at first glance but 99% of the time in practice this will load
    // exactly one media item because the protocol between the content
    // and the media are nearly always the same.
    $has_media_references = FALSE;
    foreach ($media_fields as $media_field) {
      if ($entity->hasField($media_field)) {
        foreach ($entity->{$media_field} as $media_ref) {
          if ($media_ref) {
            $target = $media_ref->getValue()['target_id'] ?? NULL;
            if ($target) {
              $has_media_references = TRUE;
              $media = \Drupal::entityTypeManager()->getStorage('media')->load($media_ref->target_id);
              if ($media && $media->access('view')) {
                $this->list[0] = $this->createItem(0, $media->id());
                return;
              }
            }
          }
        }
      }
    }

    // No accessible media found. If a dedicated placeholder is configured for
    // this case, leave the list empty so hook_entity_view_alter can inject it.
    $media_settings = \Drupal::config('mukurtu_media.settings');
    $has_restricted_placeholder = !empty($media_settings->get('restricted_media_placeholder'));
    $has_no_media_placeholder   = !empty($media_settings->get('no_media_placeholder'));

    if ($has_media_references && $has_restricted_placeholder) {
      return;
    }
    if (!$has_media_references && $has_no_media_placeholder) {
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): FieldableEntityInterface {
    $entity = parent::getEntity();
    if (!$entity->hasField('field_mukurtu_original_record') || $entity->get('field_mukurtu_original_record')->isEmpty()) {
      return $entity;
    }
    // Support Community Records by using the CR as the source of truth when
    // set.
    $original_record_field = $entity->get('field_mukurtu_original_record');
    if (!$original_record_field instanceof EntityReferenceFieldItemList) {
      return $entity;
    }
    $original_record = $original_record_field->referencedEntities()[0];
    if (!$original_record instanceof FieldableEntityInterface) {
      return $entity;
    }
    return $original_record;
  }

}
