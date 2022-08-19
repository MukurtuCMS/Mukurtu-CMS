<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;
/**
 * Defines a class to build a listing of Protocol entities.
 *
 * @ingroup mukurtu_protocol
 */
class ProtocolListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    //$header['id'] = $this->t('Protocol ID');
    $header['name'] = $this->t('Name');
    $header['description'] = $this->t('Description');
    $header['communities'] = $this->t('Communities');
    return $header + parent::buildHeader();
  }


  /**
   * {@inheritDoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $access_manager = \Drupal::service('access_manager');

    if ($access_manager->checkNamedRoute('entity.protocol.canonical', ['protocol' => $entity->id()])) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => 100,
        'url' => Url::fromRoute('entity.protocol.canonical', ['protocol' => $entity->id()]),
      ];
    }
    if ($access_manager->checkNamedRoute('mukurtu_protocol.manage_protocol', ['group' => $entity->id()])) {
      $operations['manage'] = [
        'title' => $this->t('Manage Protocol'),
        'weight' => 100,
        'url' => Url::fromRoute('mukurtu_protocol.manage_protocol', ['group' => $entity->id()]),
      ];
    }
    if ($access_manager->checkNamedRoute('mukurtu_protocol.protocol_members_list', ['group' => $entity->id()])) {
      $operations['members'] = [
        'title' => $this->t('Manage Members'),
        'weight' => 100,
        'url' => Url::fromRoute('mukurtu_protocol.protocol_members_list', ['group' => $entity->id()]),
      ];
    }
    if ($access_manager->checkNamedRoute('mukurtu_protocol.protocol_add_membership', ['group' => $entity->id()])) {
      $operations['add-member'] = [
        'title' => $this->t('Add Member'),
        'weight' => 100,
        'url' => Url::fromRoute('mukurtu_protocol.protocol_add_membership', ['group' => $entity->id()]),
      ];
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\mukurtu_protocol\Entity\Protocol $entity */
    //$row['id'] = $entity->id();
    $access_manager = \Drupal::service('access_manager');

    // Name.
    if ($access_manager->checkNamedRoute('mukurtu_protocol.manage_protocol', ['group' => $entity->id()])) {
      $row['name'] = Link::createFromRoute(
        $entity->label(),
        'mukurtu_protocol.manage_protocol',
        ['group' => $entity->id()]
      );
    } else {
      $row['name'] = $entity->label();
    }

    // Description.
    $row['description'] = $entity->getDescription();

    // Communities.
    $communities = [];
    foreach ($entity->getCommunities() as $community) {
      if ($access_manager->checkNamedRoute('mukurtu_protocol.manage_community', ['group' => $entity->id()])) {
        $communities[] = Link::createFromRoute(
          $community->label(),
          'mukurtu_protocol.manage_community',
          ['group' => $community->id()]
        )->toString()->getGeneratedLink();
      } else {
        $communities[] = $community->label();
      }
    }

    $row['communities']['data']['#markup'] = implode(', ', $communities);
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['#cache']['contexts'][] = 'user.roles';
    $build['#cache']['contexts'][] = 'og_group_context';
    $build['#cache']['contexts'][] = 'og_membership_state';
    $build['#cache']['contexts'][] = 'og_role';
    return $build;
  }

}
