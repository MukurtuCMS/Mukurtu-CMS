<?php

namespace Drupal\mukurtu_protocol\Controller;

use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\mukurtu_core\Service\EntityTranslationResolver;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\og\MembershipManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for protocol comment management.
 */
class ProtocolCommentSettingsController extends ControllerBase {

  /**
   * The OG membership manager.
   *
   * @var \Drupal\og\MembershipManagerInterface
   */
  protected MembershipManagerInterface $membershipManager;

  /**
   * The entity translation resolver.
   *
   * @var \Drupal\mukurtu_core\Service\EntityTranslationResolver
   */
  protected EntityTranslationResolver $entityTranslationResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->membershipManager = $container->get('og.membership_manager');
    $instance->entityTranslationResolver = $container->get('mukurtu_core.entity_translation_resolver');
    return $instance;
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

    return $this->buildUnapprovedCommentsTable($node_ids, $this->t('No comments awaiting approval for this protocol.'));
  }

  /**
   * Page listing unapproved comments across every protocol the current user
   * has the 'administer comments' permission in.
   */
  public function myUnapprovedComments() {
    $empty_message = $this->t('No comments awaiting approval for your protocols.');
    $protocol_ids = $this->getStewardedProtocolIds($this->currentUser());

    if (empty($protocol_ids)) {
      return ['#markup' => $empty_message, '#cache' => ['contexts' => ['user']]];
    }

    $node_storage = $this->entityTypeManager()->getStorage('node');
    $or = $node_storage->getQuery()->accessCheck(FALSE)->orConditionGroup();
    foreach ($protocol_ids as $protocol_id) {
      $or->condition('field_cultural_protocols.protocols', "|{$protocol_id}|", 'CONTAINS');
    }
    $node_ids = $node_storage->getQuery()
      ->condition($or)
      ->accessCheck(FALSE)
      ->execute();

    return $this->buildUnapprovedCommentsTable($node_ids, $empty_message);
  }

  /**
   * Builds the unapproved comments table for a set of node IDs.
   */
  protected function buildUnapprovedCommentsTable(array $node_ids, TranslatableMarkup $empty_message) {
    if (empty($node_ids)) {
      return ['#markup' => $empty_message];
    }

    $comment_ids = $this->entityTypeManager()->getStorage('comment')->getQuery()
      ->condition('entity_id', $node_ids, 'IN')
      ->condition('entity_type', 'node')
      ->condition('status', CommentInterface::NOT_PUBLISHED)
      ->sort('created', 'DESC')
      ->range(0, 200)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($comment_ids)) {
      return ['#markup' => $empty_message];
    }

    $comments = $this->entityTypeManager()->getStorage('comment')->loadMultiple($comment_ids);
    $date_formatter = \Drupal::service('date.formatter');

    $rows = [];
    foreach ($comments as $comment) {
      $commented_entity = $comment->getCommentedEntity();
      $entity_link = $commented_entity
        ? Link::fromTextAndUrl($this->entityTranslationResolver->translate($commented_entity)->label(), $commented_entity->toUrl())->toString()
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
      '#empty' => $empty_message,
      '#cache' => [
        'contexts' => ['user', 'languages:language_content'],
        'tags' => ['comment_list'],
      ],
    ];
  }

  /**
   * Gets the IDs of every protocol the given account has the
   * 'administer comments' permission in.
   *
   * @return int[]
   */
  protected function getStewardedProtocolIds(AccountInterface $account): array {
    $protocol_ids = [];
    foreach ($this->membershipManager->getMemberships($account->id()) as $membership) {
      if ($membership->getGroupEntityType() !== 'protocol') {
        continue;
      }
      if ($membership->hasPermission('administer comments')) {
        $protocol_ids[] = (int) $membership->getGroupId();
      }
    }
    return $protocol_ids;
  }

  /**
   * Access check for the per-protocol unapproved comments page.
   */
  public function access(AccountInterface $account, ProtocolInterface $group) {
    $membership = $group->getMembership($account);
    if ($membership && $membership->hasPermission('administer comments')) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Access check for the aggregated "my protocols" unapproved comments page.
   */
  public function myAccess(AccountInterface $account): AccessResult {
    if (!empty($this->getStewardedProtocolIds($account))) {
      return AccessResult::allowed()->addCacheContexts(['user']);
    }

    return AccessResult::forbidden()->addCacheContexts(['user']);
  }

}
