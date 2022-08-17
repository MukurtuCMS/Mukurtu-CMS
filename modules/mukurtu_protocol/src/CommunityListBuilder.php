<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Community entities.
 *
 * @ingroup mukurtu_protocol
 */
class CommunityListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    //$header['id'] = $this->t('Community ID');
    $header['name'] = $this->t('Community');
    $header['description'] = $this->t('Description');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritDoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $access_manager = \Drupal::service('access_manager');

    if ($access_manager->checkNamedRoute('entity.community.canonical', ['community' => $entity->id()])) {
      $operations['members'] = [
        'title' => $this->t('View'),
        'weight' => 100,
        'url' => Url::fromRoute('entity.community.canonical', ['community' => $entity->id()]),
      ];
    }

    if ($access_manager->checkNamedRoute('mukurtu_protocol.community_members_list', ['group' => $entity->id()])) {
      $operations['members'] = [
        'title' => $this->t('Manage Members'),
        'weight' => 100,
        'url' => Url::fromRoute('mukurtu_protocol.community_members_list', ['group' => $entity->id()]),
      ];
    }
    if ($access_manager->checkNamedRoute('mukurtu_protocol.community_add_membership', ['group' => $entity->id()])) {
      $operations['add-member'] = [
        'title' => $this->t('Add Member'),
        'weight' => 100,
        'url' => Url::fromRoute('mukurtu_protocol.community_add_membership', ['group' => $entity->id()]),
      ];
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $access_manager = \Drupal::service('access_manager');
    /** @var \Drupal\mukurtu_protocol\Entity\Community $entity */

    // Name.
    if ($access_manager->checkNamedRoute('mukurtu_protocol.manage_community', ['group' => $entity->id()])) {
      $row['name'] = Link::createFromRoute(
        $entity->label(),
        'mukurtu_protocol.manage_community',
        ['group' => $entity->id()]
      );
    } else {
      $row['name'] = $entity->label();
    }

    // Description.
    $row['description'] = $entity->getDescription();

    // Parent Community.
    /* $parent = $entity->getParentCommunity();
    $row['parent'] = "";
    if ($parent) {
      $row['parent'] = Link::createFromRoute(
        $parent->label(),
        'entity.community.canonical',
        ['community' => $parent->id()]
      );
    } */
    return $row + parent::buildRow($entity);
  }

}
