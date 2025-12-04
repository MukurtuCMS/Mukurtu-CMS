<?php

namespace Drupal\mukurtu_core\Entity;

use Drupal\Core\Field\EntityReferenceFieldItemList;

/**
 * @mixin \Drupal\Core\Entity\ContentEntityInterface
 * @mixin \Drupal\mukurtu_core\Entity\PeopleInterface
 */
trait PeopleTrait {

  /**
   * {@inheritdoc}
   */
  public function getPeopleTerms(): array {
    $people_field = $this->get('field_people');
    if (!$people_field instanceof EntityReferenceFieldItemList) {
      return [];
    }
    return $people_field->referencedEntities();
  }

}
