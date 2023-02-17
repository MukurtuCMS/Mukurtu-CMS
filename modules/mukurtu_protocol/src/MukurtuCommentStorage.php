<?php

namespace Drupal\mukurtu_protocol;

use Drupal\comment\CommentInterface;
use Drupal\comment\CommentManagerInterface;
use Drupal\comment\CommentStorage;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Entity\EntityInterface;
use Drupal\mukurtu_protocol\CulturalProtocols;

/**
 * Defines the storage handler class for comments.
 *
 * This extends the Drupal\comment\CommentStorage class,
 * adding required special handling for Mukurtu protocols.
 */
class MukurtuCommentStorage extends CommentStorage {

  /**
   * {@inheritdoc}
   */
  public function getDisplayOrdinal(CommentInterface $comment, $comment_mode, $divisor = 1) {
    // Count how many comments (c1) are before $comment (c2) in display order.
    // This is the 0-based display ordinal.
    $data_table = $this->getDataTable();
    $query = $this->database->select($data_table, 'c1');
    $query->innerJoin($data_table, 'c2', '[c2].[entity_id] = [c1].[entity_id] AND [c2].[entity_type] = [c1].[entity_type] AND [c2].[field_name] = [c1].[field_name]');
    $query->addExpression('COUNT(*)', 'count');
    $query->condition('c2.cid', $comment->id());
    if (!$this->currentUser->hasPermission('administer comments')) {
      $query->condition('c1.status', CommentInterface::PUBLISHED);
    }

    if ($comment_mode == CommentManagerInterface::COMMENT_MODE_FLAT) {
      // For rendering flat comments, cid is used for ordering comments due to
      // unpredictable behavior with timestamp, so we make the same assumption
      // here.
      $query->condition('c1.cid', $comment->id(), '<');
    }
    else {
      // For threaded comments, the c.thread column is used for ordering. We can
      // use the sorting code for comparison, but must remove the trailing
      // slash.
      $query->where('SUBSTRING([c1].[thread], 1, (LENGTH([c1].[thread]) - 1)) < SUBSTRING([c2].[thread], 1, (LENGTH([c2].[thread]) - 1))');
    }

    $query->condition('c1.default_langcode', 1);
    $query->condition('c2.default_langcode', 1);

    $ordinal = $query->execute()->fetchField();

    return ($divisor > 1) ? floor($ordinal / $divisor) : $ordinal;
  }

  /**
   * {@inheritdoc}
   */
  public function loadThread(EntityInterface $entity, $field_name, $mode, $comments_per_page = 0, $pager_id = 0) {
    $data_table = $this->getDataTable();
    $query = $this->database->select($data_table, 'c');
    $query->addField('c', 'cid');
    $query
      ->condition('c.entity_id', $entity->id())
      ->condition('c.entity_type', $entity->getEntityTypeId())
      ->condition('c.field_name', $field_name)
      ->condition('c.default_langcode', 1)
      ->addTag('entity_access')
      ->addTag('comment_filter')
      ->addMetaData('base_table', 'comment')
      ->addMetaData('entity', $entity)
      ->addMetaData('field_name', $field_name);

    if ($comments_per_page) {
      $query = $query->extend(PagerSelectExtender::class)
        ->limit($comments_per_page);
      if ($pager_id) {
        $query->element($pager_id);
      }

      $count_query = $this->database->select($data_table, 'c');
      $count_query->addExpression('COUNT(*)');
      $count_query
        ->condition('c.entity_id', $entity->id())
        ->condition('c.entity_type', $entity->getEntityTypeId())
        ->condition('c.field_name', $field_name)
        ->condition('c.default_langcode', 1)
        ->addTag('entity_access')
        ->addTag('comment_filter')
        ->addMetaData('base_table', 'comment')
        ->addMetaData('entity', $entity)
        ->addMetaData('field_name', $field_name);
      $query->setCountQuery($count_query);
    }

    if (!CulturalProtocols::hasSiteOrProtocolPermission($entity, 'administer comments', $this->currentUser, TRUE)) {
      $query->condition('c.status', CommentInterface::PUBLISHED);
      if ($comments_per_page) {
        $count_query->condition('c.status', CommentInterface::PUBLISHED);
      }
    }
    if ($mode == CommentManagerInterface::COMMENT_MODE_FLAT) {
      $query->orderBy('c.cid', 'ASC');
    }
    else {
      // See comment above. Analysis reveals that this doesn't cost too much. It
      // scales much better than having the whole comment structure.
      $query->addExpression('SUBSTRING([c].[thread], 1, (LENGTH([c].[thread]) - 1))', 'torder');
      $query->orderBy('torder', 'ASC');
    }

    $cids = $query->execute()->fetchCol();

    $comments = [];
    if ($cids) {
      $comments = $this->loadMultiple($cids);
    }

    return $comments;
  }

}
