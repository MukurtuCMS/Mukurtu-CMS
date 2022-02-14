<?php

namespace Drupal\mukurtu_community;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Community entities.
 *
 * @ingroup mukurtu_community
 */
class CommunityListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    //$header['id'] = $this->t('Community ID');
    $header['name'] = $this->t('Community');
    $header['parent'] = $this->t('Parent Community');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\mukurtu_community\Entity\Community $entity */
    //$row['id'] = $entity->id();
    // Name.
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.community.canonical',
      ['community' => $entity->id()]
    );

    // Parent Community.
    $parent = $entity->getParentCommunity();
    $row['parent'] = "";
    if ($parent) {
      $row['parent'] = Link::createFromRoute(
        $parent->label(),
        'entity.community.canonical',
        ['community' => $parent->id()]
      );
    }
    return $row + parent::buildRow($entity);
  }

}
