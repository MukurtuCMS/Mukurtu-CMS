<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Access\AccessResult;
use Drupal\comment\CommentAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolControl;

/**
 * Defines the access control handler for the comment entity type.
 *
 * @see \Drupal\comment\Entity\Comment
 */
class MukurtuCommentAccessControlHandler extends CommentAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\comment\CommentInterface|\Drupal\user\EntityOwnerInterface $entity */

    // Mukurtu is only concerned with altering the approval behavior.
    // All other operations can be handled by the stock handler.
    if ($operation != 'approve') {
      return parent::checkAccess($entity, $operation, $account);
    }

    // Site comment admins trump protocol level settings. If they have
    // site comment admin we can skip the protocol checks.
    $site_comment_admin = $account->hasPermission('administer comments');
    if ($site_comment_admin && !$entity->isPublished()) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    // Fall back to normal comment access checks if the entity doesn't have a
    // protocol field.
    if (!$entity->getCommentedEntity()->hasField('field_protocol_control')) {
      return parent::checkAccess($entity, $operation, $account);
    }

    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface $protocolControlEntity */
    // Try to load the protocol control entity on the commented entity.
    $protocolControlEntity = ProtocolControl::getProtocolControlEntity($entity->getCommentedEntity());

    // No valid protocol control entity (but DOES have a protocol control
    // field) or an empty protocol list. Fall back to parent check.
    if (!$protocolControlEntity || empty($protocolControlEntity->getProtocols())) {
      return parent::checkAccess($entity, $operation, $account);
    }

    // Now we can finally see if they have protocol level comment admin.
    $protocols = $protocolControlEntity->getProtocolEntities();
    $protocol_comment_admin = TRUE;
    foreach ($protocols as $protocol) {
      $membership = $protocol->getMembership($account);
      $protocol_comment_admin = $protocol_comment_admin && $membership->hasPermission('administer comments');
    }

    return AccessResult::allowedIf($protocol_comment_admin && !$entity->isPublished())
      ->cachePerPermissions()
      ->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'post comments');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, FieldItemListInterface $items = NULL) {
    $entity = $items->getEntity();
    if ($operation == 'edit') {
      // Only users with the "administer comments" permission can edit
      // administrative fields.
      $administrative_fields = [
        'uid',
        'status',
        'created',
        'date',
      ];
      if (in_array($field_definition->getName(), $administrative_fields, TRUE)) {
        return AccessResult::allowedIf(ProtocolControl::hasSiteOrProtocolPermission($entity, 'administer comments', $account, TRUE));
      }

      // No user can change read-only fields.
      $read_only_fields = [
        'hostname',
        'changed',
        'cid',
        'thread',
      ];
      // These fields can be edited during comment creation.
      $create_only_fields = [
        'comment_type',
        'uuid',
        'entity_id',
        'entity_type',
        'field_name',
        'pid',
      ];
      if ($items && ($entity = $items->getEntity()) && $entity->isNew() && in_array($field_definition->getName(), $create_only_fields, TRUE)) {
        // We are creating a new comment, user can edit create only fields.
        return AccessResult::allowedIfHasPermission($account, 'post comments')->addCacheableDependency($entity);
      }
      // We are editing an existing comment - create only fields are now read
      // only.
      $read_only_fields = array_merge($read_only_fields, $create_only_fields);
      if (in_array($field_definition->getName(), $read_only_fields, TRUE)) {
        return AccessResult::forbidden();
      }

      // If the field is configured to accept anonymous contact details - admins
      // can edit name, homepage and mail. Anonymous users can also fill in the
      // fields on comment creation.
      if (in_array($field_definition->getName(), ['name', 'mail', 'homepage'], TRUE)) {
        if (!$items) {
          // We cannot make a decision about access to edit these fields if we
          // don't have any items and therefore cannot determine the Comment
          // entity. In this case we err on the side of caution and prevent edit
          // access.
          return AccessResult::forbidden();
        }
        $is_name = $field_definition->getName() === 'name';
        /** @var \Drupal\comment\CommentInterface $entity */
        $entity = $items->getEntity();
        $commented_entity = $entity->getCommentedEntity();
        $anonymous_contact = $commented_entity->get($entity->getFieldName())->getFieldDefinition()->getSetting('anonymous');
        $admin_access = AccessResult::allowedIf(ProtocolControl::hasSiteOrProtocolPermission($entity, 'administer comments', $account, TRUE));
        $anonymous_access = AccessResult::allowedIf($entity->isNew() && $account->isAnonymous() && ($anonymous_contact != CommentInterface::ANONYMOUS_MAYNOT_CONTACT || $is_name) && $account->hasPermission('post comments'))
          ->cachePerPermissions()
          ->addCacheableDependency($entity)
          ->addCacheableDependency($field_definition->getConfig($commented_entity->bundle()))
          ->addCacheableDependency($commented_entity);
        return $admin_access->orIf($anonymous_access);
      }
    }

    if ($operation == 'view') {
      // Nobody has access to the hostname.
      if ($field_definition->getName() == 'hostname') {
        return AccessResult::forbidden();
      }
      // The mail field is hidden from non-admins.
      if ($field_definition->getName() == 'mail') {
        return AccessResult::allowedIfHasPermission($account, 'administer comments');
      }
    }
    return parent::checkFieldAccess($operation, $field_definition, $account, $items);
  }

}
