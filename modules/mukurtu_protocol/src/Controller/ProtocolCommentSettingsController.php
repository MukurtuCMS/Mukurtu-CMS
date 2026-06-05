<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Controller for protocol comment management.
 */
class ProtocolCommentSettingsController extends ControllerBase {

  /**
   * Page for managing protocol's comment settings.
   */
  public function content(ProtocolInterface $group) {
    $form_state = new FormState();
    $form_state->set('protocol', $group);
    $form = $this->formBuilder()->buildForm('Drupal\mukurtu_protocol\Form\ProtocolCommentSettingsForm', $form_state);
    return $form;
  }

  /**
   * Title callback for comment settings page.
   */
  public function getTitle(ProtocolInterface $group) {
    return $this->t("%protocol Comment Settings", ['%protocol' => $group->getName()]);
  }

  /**
   * Title callback for the unapproved comments page.
   */
  public function getUnapprovedTitle(ProtocolInterface $group) {
    return $this->t('%protocol - Comments Awaiting Approval', ['%protocol' => $group->getName()]);
  }

  /**
   * Page listing unapproved comments for content in a given protocol.
   */
  public function unapprovedComments(ProtocolInterface $group) {
    $node_ids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->condition('field_cultural_protocols.protocols', "|{$group->id()}|", 'CONTAINS')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($node_ids)) {
      return ['#markup' => $this->t('No comments awaiting approval for this protocol.')];
    }

    $comment_ids = $this->entityTypeManager()->getStorage('comment')->getQuery()
      ->condition('entity_id', $node_ids, 'IN')
      ->condition('entity_type', 'node')
      ->condition('status', CommentInterface::NOT_PUBLISHED)
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($comment_ids)) {
      return ['#markup' => $this->t('No comments awaiting approval for this protocol.')];
    }

    $comments = $this->entityTypeManager()->getStorage('comment')->loadMultiple($comment_ids);
    $date_formatter = \Drupal::service('date.formatter');

    $rows = [];
    foreach ($comments as $comment) {
      $commented_entity = $comment->getCommentedEntity();
      $entity_link = $commented_entity
        ? Link::fromTextAndUrl($commented_entity->label(), $commented_entity->toUrl())->toString()
        : $this->t('(deleted)');

      $operations = [];
      if ($comment->access('approve')) {
        $operations['approve'] = [
          'title' => $this->t('Approve'),
          'url' => Url::fromRoute('comment.approve', ['comment' => $comment->id()]),
        ];
      }
      if ($comment->access('delete')) {
        $operations['delete'] = [
          'title' => $this->t('Delete'),
          'url' => $comment->toUrl('delete-form'),
        ];
      }

      $rows[] = [
        $comment->getSubject() ?: $this->t('(no subject)'),
        ['data' => ['#markup' => $entity_link]],
        $comment->getAuthorName(),
        $date_formatter->format($comment->getCreatedTime(), 'short'),
        [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Subject'),
        $this->t('Content'),
        $this->t('Author'),
        $this->t('Date'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No comments awaiting approval for this protocol.'),
    ];
  }

  /**
   * Access check for redirect to management page.
   */
  public function access(AccountInterface $account, ProtocolInterface $group) {
    $membership = $group->getMembership($account);
    if ($membership && $membership->hasPermission('administer comments')) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
