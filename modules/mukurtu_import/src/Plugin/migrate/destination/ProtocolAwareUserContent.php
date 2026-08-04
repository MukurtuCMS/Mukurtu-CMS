<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\destination;

use Drupal\migrate\MigrateException;
use Drupal\migrate\Row;
use Drupal\og\OgMembershipInterface;
use Drupal\user\UserInterface;

/**
 * Provides a migrate destination for bulk-creating/updating user accounts.
 *
 * Extends ProtocolAwareEntityContent's current-user-based access checking
 * with account-specific safety rules: the site superuser (uid 1) can never
 * be an import target, no destination property can ever set the account
 * password, and newly created accounts optionally receive Drupal's standard
 * account-setup email.
 *
 * @see mukurtu_import_migrate_destination_info_alter().
 */
class ProtocolAwareUserContent extends ProtocolAwareEntityContent {

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    // Defense in depth: the entity-type radio option is hidden in the wizard
    // UI without this permission, but that alone is not a security boundary
    // (the radios element sets '#validated' => TRUE, which disables Drupal's
    // automatic "value must be in #options" check, so a crafted request
    // could still submit entity_type_id=user). Enforce the permission here,
    // at the point where user accounts are actually created/updated,
    // regardless of how the migration definition was assembled.
    if (!$this->currentUser->hasPermission('import mukurtu users')) {
      throw new MigrateException('The current user does not have permission to import user accounts.');
    }

    // Plaintext passwords are never imported. New accounts get a standard
    // account-setup email instead (see sendAccountSetupEmail()).
    $row->removeDestinationProperty('pass');

    // Community/protocol membership isn't a real field on the user entity,
    // so it must be extracted before parent::import() builds/saves the
    // entity (core's EntityContentBase would otherwise try to set it as a
    // literal property and throw), then applied after save using the
    // resulting entity ID (see applyGroupMembershipUpdates()).
    $group_membership_updates = $this->extractAndClearGroupMembershipUpdates($row);

    // Validate access to every named group BEFORE the user entity is
    // created/updated below. A failure here must abort the whole row
    // without side effects, same as an unresolvable community/protocol
    // name or role already does inside GroupMembershipLookup (which runs
    // earlier still, during the process pipeline) -- checking only after
    // parent::import() has already saved the account would mark the row
    // failed but leave the account it just created behind.
    $this->validateGroupMembershipAccess($group_membership_updates);

    $existing_id = $this->getEntityId($row);
    if ($existing_id && (int) $existing_id === 1) {
      throw new MigrateException('The site superuser account (uid 1) cannot be created or updated via import.');
    }

    $is_new = !$existing_id;
    $ids = parent::import($row, $old_destination_id_values);

    if (!empty($ids[0])) {
      $this->applyGroupMembershipUpdates((int) $ids[0], $group_membership_updates);
    }

    if ($is_new && !empty($ids[0])) {
      $this->sendAccountSetupEmail((int) $ids[0]);
    }

    return $ids;
  }

  /**
   * Extracts and clears the virtual communities/protocols destination
   * properties, returning a group-entity-type => parsed-entries map for
   * post-save processing.
   */
  protected function extractAndClearGroupMembershipUpdates(Row $row): array {
    $updates = [];
    foreach (['communities' => 'community', 'protocols' => 'protocol'] as $dest_key => $group_entity_type) {
      $value = $row->getDestinationProperty($dest_key);
      if ($value) {
        $updates[$group_entity_type] = $value;
        $row->setDestinationProperty($dest_key, NULL);
      }
    }
    return $updates;
  }

  /**
   * Validates access to every group named in parsed membership updates.
   *
   * Must run before the user entity is created/updated, so an access
   * failure aborts the whole row with no side effects -- matching how an
   * unresolvable community/protocol name or role already hard-fails the
   * row inside GroupMembershipLookup, earlier in the process pipeline.
   *
   * @param array $updates
   *   A 'community'/'protocol' => list of ['target_id' => id, 'roles' =>
   *   [role,...]] entries map, as produced by GroupMembershipLookup.
   *
   * @throws \Drupal\migrate\MigrateException
   *   If the current user lacks 'manage members' access to a named group.
   */
  protected function validateGroupMembershipAccess(array $updates): void {
    $og_access = \Drupal::service('og.access');
    foreach ($updates as $group_entity_type => $entries) {
      $storage = $this->entityTypeManager->getStorage($group_entity_type);
      foreach ($entries as $entry) {
        // The lookup plugin returns NULL for a blank CSV cell.
        if (empty($entry) || empty($entry['target_id'])) {
          continue;
        }
        $group = $storage->load($entry['target_id']);
        if (!$group) {
          continue;
        }
        // Membership grants are gated by the importing user's own OG
        // access to the specific group being granted, not just the
        // broader 'import mukurtu users' permission -- otherwise any
        // import admin could grant themselves or others
        // community_manager/protocol_steward in ANY community/protocol
        // site-wide, bypassing that group's own stewards' control over
        // their membership. OgAccess::userAccess() never returns
        // "forbidden", only "allowed" (uid 1, 'administer organic
        // groups', or an existing role with 'manage members' on this
        // group) or "neutral" (everyone else) -- so this must check
        // isAllowed(), not merely "not forbidden".
        $access = $og_access->userAccess($group, 'manage members', $this->currentUser);
        if (!$access->isAllowed()) {
          throw new MigrateException(sprintf('The current user does not have permission to manage membership for the %s "%s".', $group_entity_type, $group->label()));
        }
      }
    }
  }

  /**
   * Applies parsed community/protocol memberships to the saved user account.
   *
   * Access to every named group has already been validated by
   * validateGroupMembershipAccess() before the user entity was saved.
   *
   * @param int $uid
   *   The saved user's ID.
   * @param array $updates
   *   A 'community'/'protocol' => list of ['target_id' => id, 'roles' =>
   *   [role,...]] entries map, as produced by GroupMembershipLookup.
   */
  protected function applyGroupMembershipUpdates(int $uid, array $updates): void {
    if (empty($updates)) {
      return;
    }
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface) {
      return;
    }

    foreach ($updates as $group_entity_type => $entries) {
      $storage = $this->entityTypeManager->getStorage($group_entity_type);
      foreach ($entries as $entry) {
        // The lookup plugin returns NULL for a blank CSV cell.
        if (empty($entry) || empty($entry['target_id'])) {
          continue;
        }
        $group = $storage->load($entry['target_id']);
        if (!$group) {
          continue;
        }
        // Community::addMember() no-ops WITHOUT updating roles if a
        // membership already exists in any state, while Protocol::addMember()
        // self-corrects to setRoles() in that case. Checking first and
        // branching explicitly makes both group types behave the same way,
        // so re-importing a user with changed roles always updates them.
        if ($group->getMembership($account, OgMembershipInterface::ALL_STATES)) {
          $group->setRoles($account, $entry['roles']);
        }
        else {
          $group->addMember($account, $entry['roles']);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * The 'import mukurtu users' permission, enforced above, is the intended
   * full authorization gate for this destination. Without this override,
   * the parent's generic entity access check would additionally require
   * Drupal core's broad 'administer users' permission (the user entity
   * type's admin permission) for any non-uid-1 importer, defeating the
   * point of a dedicated, more narrowly scoped import permission.
   */
  protected function currentUserBypassesAccessChecks(): bool {
    return parent::currentUserBypassesAccessChecks() || $this->currentUser->hasPermission('import mukurtu users');
  }

  /**
   * Sends the standard Drupal account-setup email for a newly created user.
   *
   * Only sent when the importing admin opted in for this batch (see
   * ExecuteImportForm) and the account is active with an email address.
   */
  protected function sendAccountSetupEmail(int $uid): void {
    if (empty($this->migration->pluginDefinition['mukurtu_import_send_setup_emails'])) {
      return;
    }
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface || !$account->isActive() || !$account->getEmail()) {
      return;
    }
    _user_mail_notify('register_admin_created', $account);
  }

}
