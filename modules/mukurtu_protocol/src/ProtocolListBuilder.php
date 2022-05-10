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
  protected function getDefaultOperations(EntityInterface $entity)
  {
    $operations = parent::getDefaultOperations($entity);
    $operations['members'] = [
      'title' => $this->t('Manage Members'),
      'weight' => 100,
      'url' => Url::fromRoute('mukurtu_protocol.protocol_members_list', ['group' => $entity->id()]),
    ];
    $operations['add-member'] = [
      'title' => $this->t('Add Member'),
      'weight' => 100,
      'url' => Url::fromRoute('mukurtu_protocol.protocol_add_membership', ['group' => $entity->id()]),
    ];
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\mukurtu_protocol\Entity\Protocol $entity */
    //$row['id'] = $entity->id();

    // Name.
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'mukurtu_protocol.manage_protocol',
      ['group' => $entity->id()]
    );

    // Description.
    $row['description'] = $entity->getDescription();

    // Communities.
    $communities = [];
    foreach ($entity->getCommunities() as $community) {
      $communities[] = Link::createFromRoute(
        $community->label(),
        'mukurtu_protocol.manage_community',
        ['group' => $community->id()]
      )->toString()->getGeneratedLink();
    }

    $row['communities']['data']['#markup'] = implode(', ', $communities);
    return $row + parent::buildRow($entity);
  }

}
