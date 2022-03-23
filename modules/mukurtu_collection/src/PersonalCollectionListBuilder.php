<?php

namespace Drupal\mukurtu_collection;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Personal collection entities.
 *
 * @ingroup mukurtu_collection
 */
class PersonalCollectionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Personal collection ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\mukurtu_collection\Entity\PersonalCollection $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.personal_collection.edit_form',
      ['personal_collection' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
