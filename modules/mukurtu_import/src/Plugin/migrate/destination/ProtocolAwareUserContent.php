<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\destination;

use Drupal\migrate\MigrateException;
use Drupal\migrate\Row;
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

    $existing_id = $this->getEntityId($row);
    if ($existing_id && (int) $existing_id === 1) {
      throw new MigrateException('The site superuser account (uid 1) cannot be created or updated via import.');
    }

    $is_new = !$existing_id;
    $ids = parent::import($row, $old_destination_id_values);

    if ($is_new && !empty($ids[0])) {
      $this->sendAccountSetupEmail((int) $ids[0]);
    }

    return $ids;
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
