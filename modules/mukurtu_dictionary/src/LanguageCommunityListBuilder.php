<?php

namespace Drupal\mukurtu_dictionary;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Language community entities.
 *
 * @ingroup mukurtu_dictionary
 */
class LanguageCommunityListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Language community ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\mukurtu_dictionary\Entity\LanguageCommunity $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.language_community.edit_form',
      ['language_community' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
