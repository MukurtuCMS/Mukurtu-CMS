<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\comment\CommentFieldItemList;
use Drupal\mukurtu_protocol\Entity\ProtocolControl;

/**
 * Defines an item list class for comment fields.
 */
class MukurtuCommentFieldItemList extends CommentFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    $entity = $this->getEntity();

    if ($operation === 'edit') {
      // Only users with administer comments permission can edit the comment
      // status field.
      $result = AccessResult::allowedIf(ProtocolControl::hasSiteOrProtocolPermission($entity, 'administer comments', $account ?: \Drupal::currentUser(), TRUE));
      return $return_as_object ? $result : $result->isAllowed();
    }
    if ($operation === 'view') {
      // Precedence ordering from highest to lowest is site, protocol,
      // content type definition, individual item.
      // A 'Deny' setting cannot be overridden by a lower precedence setting.
      $siteCommentingConfig = \Drupal::config('mukurtu_protocol.comment_settings');
      $siteCommentingEnabled = $siteCommentingConfig->get('site_comments_enabled') ?? TRUE;
      if (!$siteCommentingEnabled) {
        return $return_as_object ? AccessResult::forbidden() : FALSE;
      }

      // Check each of the entity's protocols for their protocol comment status.
      $protocolCommenting = TRUE;
      $pce = ProtocolControl::getProtocolControlEntity($entity);
      if ($pce) {
        $protocols = $pce->getProtocolEntities();
        foreach ($protocols as $protocol) {
          $protocolCommenting = $protocolCommenting && $protocol->getCommentStatus();
        }
      }

      if (!$protocolCommenting) {
        return $return_as_object ? AccessResult::forbidden() : FALSE;
      }

      // Only users with "post comments" or "access comments" permission can
      // view the field value. The formatter,
      // Drupal\comment\Plugin\Field\FieldFormatter\CommentDefaultFormatter,
      // takes care of showing the thread and form based on individual
      // permissions, so if a user only has ‘post comments’ access, only the
      // form will be shown and not the comments.
      $result = AccessResult::allowedIfHasPermission($account ?: \Drupal::currentUser(), 'access comments')
        ->orIf(AccessResult::allowedIfHasPermission($account ?: \Drupal::currentUser(), 'post comments'));

      return $return_as_object ? $result : $result->isAllowed();
    }
    return parent::access($operation, $account, $return_as_object);
  }
}
