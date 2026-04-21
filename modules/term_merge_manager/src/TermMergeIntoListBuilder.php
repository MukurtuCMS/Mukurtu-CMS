<?php

namespace Drupal\term_merge_manager;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Term merge into entities.
 *
 * @ingroup term_merge_manager
 */
class TermMergeIntoListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function load() {

    $entity_query = \Drupal::entityQuery('term_merge_into');
    $header = $this->buildHeader();

    $entity_query->pager(50);
    $entity_query->tableSort($header);
    $entity_query->accessCheck(TRUE);

    $uids = $entity_query->execute();

    return $this->storage->loadMultiple($uids);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {

    $header['id'] = [
      'data' => $this->t('Term merge into ID'),
      'field' => 'id',
      'specifier' => 'id',
    ];

    $header['vid'] = [
      'data' => $this->t('Vocabulary'),
      'field' => 'vid',
      'specifier' => 'vid',
    ];

    $header['term'] = [
      'data' => $this->t('Term'),
      'field' => 'tid',
      'specifier' => 'tid',
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\term_merge_manager\Entity\TermMergeInto $entity */
    $row['id'] = $entity->id();
    $row['vid'] = $entity->getVid();
    $row['tid'] = Link::createFromRoute(
      $entity->getTid() . ' (' . $entity->getName() . ')',
      'entity.taxonomy_term.edit_form',
      ['taxonomy_term' => $entity->getTid()]
    );
    return $row + parent::buildRow($entity);
  }

}
