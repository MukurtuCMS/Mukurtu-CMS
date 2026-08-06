<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\ImportBatchExecutable;
use Drupal\user\Entity\Role;

/**
 * Tests importing/updating user accounts via the Mukurtu Import module.
 */
class ImportUserAccountTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Role::create(['id' => 'administrator', 'label' => 'Administrator'])->save();
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();

    // Grant the test's importing user the dedicated permission this feature
    // requires (mirrors gating this tool to admins in production).
    $import_users_role = Role::create(['id' => 'import_users', 'label' => 'Import Users']);
    $import_users_role->grantPermission('import mukurtu users');
    $import_users_role->save();
    $this->currentUser->addRole('import_users');
    $this->currentUser->save();

    // Kernel tests don't automatically swap in the test mail collector the
    // way functional tests do; enable it explicitly so sent mail can be
    // asserted against via the 'system.test_mail_collector' state key.
    $this->config('system.mail')->set('interface.default', 'test_mail_collector')->save();

    // The account-setup email relies on 'user.mail' (subject/body templates)
    // and 'user.settings' (notify.register_admin_created), neither of which
    // this test base installs by default.
    $this->installConfig(['user']);
    $this->config('user.settings')->set('notify.register_admin_created', TRUE)->save();

    // The account-setup email template tokenizes the site name/mail; this
    // test base never populates them, leaving them NULL.
    $this->config('system.site')->set('name', 'Test Site')->set('mail', 'admin@example.com')->save();
  }

  /**
   * Runs a user-targeted import, optionally opting in to setup emails.
   *
   * @return int
   *   The MigrateExecutable result code.
   */
  protected function importUserCsv(array $data, array $mapping, bool $sendSetupEmails = FALSE): int {
    $file = $this->createCsvFile($data);
    $import_config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $import_config->setTargetEntityTypeId('user');
    $import_config->setTargetBundle('user');
    $import_config->setMapping($mapping);
    $definition = $import_config->toDefinition($file) + [
      'mukurtu_import_send_setup_emails' => $sendSetupEmails,
    ];

    $message = new MigrateMessage();
    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
    $migration = $migration_plugin_manager->createStubMigration($definition);
    $this->lastMigration = $migration;
    $executable = new ImportBatchExecutable(
      $migration,
      $message,
      $this->keyValue,
      \Drupal::service('datetime.time'),
      \Drupal::service('string_translation'),
      $migration_plugin_manager,
      [],
    );
    return $executable->import();
  }

  /**
   * Test creating a new user account with core fields and a non-admin role.
   */
  public function testCreateUserWithFieldsAndRole() {
    $data = [
      ['Username', 'Email', 'Account Status', 'Roles'],
      ['newperson', 'newperson@example.com', 'Active', 'editor'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'account_status', 'source' => 'Account Status'],
      ['target' => 'roles', 'source' => 'Roles'],
    ];

    $result = $this->importUserCsv($data, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'newperson']);
    $user = reset($users);
    $this->assertNotFalse($user);
    $this->assertEquals('newperson@example.com', $user->getEmail());
    $this->assertTrue($user->isActive());
    $this->assertTrue($user->hasRole('editor'));
  }

  /**
   * Test that a non-uid-1 user with only the import permission can import.
   *
   * Regression test: the dedicated 'import mukurtu users' permission,
   * enforced by ProtocolAwareUserContent, was previously undermined by the
   * parent destination's generic entity access check, which for the user
   * entity type requires Drupal core's broad 'administer users' permission
   * for any account other than uid 1. That silently rejected every row for
   * an importer who held only the module's scoped permission. All the other
   * tests in this class run as this test base's uid-1 user, which bypasses
   * both checks and would not have caught this.
   */
  public function testNonSuperuserWithPermissionCanImport() {
    $importer = $this->createUser();
    $importer->addRole('import_users');
    $importer->save();
    $this->setCurrentUser($importer);

    $data = [
      ['Username', 'Email', 'Roles'],
      ['importedbynonadmin', 'importedbynonadmin@example.com', 'editor'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'roles', 'source' => 'Roles'],
    ];

    $result = $this->importUserCsv($data, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'importedbynonadmin']);
    $user = reset($users);
    $this->assertNotFalse($user, 'A user with only the import mukurtu users permission (not uid 1, not administer users) must be able to create accounts via import.');
    $this->assertTrue($user->hasRole('editor'));
  }

  /**
   * Test that importing users requires the dedicated permission.
   *
   * This is enforced at the destination plugin level (not just by hiding the
   * 'user' option in the wizard UI), since the entity-type radios element
   * sets '#validated' => TRUE, which disables Drupal's automatic "value must
   * be in #options" check -- so a crafted request could otherwise still
   * submit entity_type_id=user regardless of what the UI shows.
   */
  public function testImportRequiresPermission() {
    $unprivileged = $this->createUser();
    $unprivileged->save();
    $this->setCurrentUser($unprivileged);

    $data = [
      ['Username', 'Email'],
      ['sneaky', 'sneaky@example.com'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
    ];

    $this->importUserCsv($data, $mapping);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'sneaky']);
    $this->assertEmpty($users, 'A user lacking the import mukurtu users permission must not be able to create accounts via import.');

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('does not have permission to import user accounts', reset($messages)->message);
  }

  /**
   * Test that the Administrator role can never be assigned via import.
   */
  public function testAdministratorRoleRejected() {
    $data = [
      ['Username', 'Email', 'Roles'],
      ['wannabe_admin', 'wannabe@example.com', 'administrator'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'roles', 'source' => 'Roles'],
    ];

    $this->importUserCsv($data, $mapping);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'wannabe_admin']);
    $this->assertEmpty($users, 'A row attempting to grant the Administrator role must not create the account.');

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('Administrator role cannot be assigned', reset($messages)->message);
  }

  /**
   * Test that a custom role granting a genuinely dangerous permission is
   * rejected too, not just the literal Administrator role.
   *
   * Regression coverage for a gap noted while fixing the "import mukurtu
   * users" permission to actually work as scoped: RoleLookup previously only
   * blocked the literal 'administrator' machine name, so a differently-named
   * custom role carrying an equivalent admin-only permission (e.g.
   * 'administer permissions') could sidestep that protection entirely.
   */
  public function testDangerousPermissionRoleRejected() {
    Role::create(['id' => 'shadow_admin', 'label' => 'Shadow Admin'])
      ->grantPermission('administer permissions')
      ->save();

    $data = [
      ['Username', 'Email', 'Roles'],
      ['wannabe_shadow_admin', 'wannabe_shadow_admin@example.com', 'Shadow Admin'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'roles', 'source' => 'Roles'],
    ];

    $this->importUserCsv($data, $mapping);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'wannabe_shadow_admin']);
    $this->assertEmpty($users, 'A row attempting to grant a role with a dangerous, site-wide-control permission must not create the account.');

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('grants the "administer permissions" permission', reset($messages)->message);
  }

  /**
   * Test that a role carrying only a scoped, 'restrict access'-flagged
   * feature-admin permission (not a site-wide-control one) CAN be assigned
   * via import.
   *
   * Regression coverage: RoleLookup previously rejected any role holding
   * ANY permission Drupal marks 'restrict access', a flag core and Mukurtu's
   * own modules both apply broadly to mean "trusted roles only" -- not a
   * reliable signal of site-wide escalation. That wrongly blocked roles like
   * Mukurtu Manager, which only holds scoped feature-admin permissions such
   * as 'administer mukurtu access denied page'.
   */
  public function testRestrictAccessPermissionAllowedWhenNotDangerous() {
    Role::create(['id' => 'mukurtu_manager_like', 'label' => 'Mukurtu-Manager-like'])
      ->grantPermission('administer mukurtu access denied page')
      ->save();

    $data = [
      ['Username', 'Email', 'Roles'],
      ['scopedadmin', 'scopedadmin@example.com', 'Mukurtu-Manager-like'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'roles', 'source' => 'Roles'],
    ];

    $result = $this->importUserCsv($data, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'scopedadmin']);
    $user = reset($users);
    $this->assertNotFalse($user, 'A role with only a scoped restrict-access permission (not a site-wide-control one) must be importable.');
    $this->assertTrue($user->hasRole('mukurtu_manager_like'));
  }

  /**
   * Test that the real Mukurtu Roundtrip Manager role can be assigned via
   * import.
   *
   * Regression test for the reviewed round-trip testing issue: this role's
   * only sensitive permission, 'administer mukurtu_import_strategy', is
   * 'restrict access'-flagged but not a site-wide-control permission.
   */
  public function testRoundtripManagerRoleImportable() {
    Role::create(['id' => 'mukurtu_roundtrip_manager', 'label' => 'Mukurtu Roundtrip Manager'])
      ->grantPermission('administer mukurtu_import_strategy')
      ->save();

    $data = [
      ['Username', 'Email', 'Roles'],
      ['roundtripmgr', 'roundtripmgr@example.com', 'Mukurtu Roundtrip Manager'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'roles', 'source' => 'Roles'],
    ];

    $result = $this->importUserCsv($data, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'roundtripmgr']);
    $user = reset($users);
    $this->assertNotFalse($user, 'The Mukurtu Roundtrip Manager role must be importable.');
    $this->assertTrue($user->hasRole('mukurtu_roundtrip_manager'));
  }

  /**
   * Test that a role Drupal itself marks as a full super-admin role
   * (RoleInterface::isAdmin()) is rejected regardless of its machine name.
   *
   * Regression coverage for a gap found alongside the dangerous-permission
   * allowlist fix: the Administrator check previously only compared against
   * the literal string 'administrator', so a cloned/renamed super-admin role
   * (is_admin: true, which grants every permission implicitly and typically
   * carries no explicit permissions of its own to match against an
   * allowlist) would have sailed through both checks.
   */
  public function testRenamedSuperAdminRoleRejected() {
    Role::create(['id' => 'site_owner', 'label' => 'Site Owner', 'is_admin' => TRUE])->save();

    $data = [
      ['Username', 'Email', 'Roles'],
      ['wannabe_site_owner', 'wannabe_site_owner@example.com', 'Site Owner'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'roles', 'source' => 'Roles'],
    ];

    $this->importUserCsv($data, $mapping);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'wannabe_site_owner']);
    $this->assertEmpty($users, 'A row attempting to grant a renamed super-admin (is_admin) role must not create the account.');

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('Administrator role cannot be assigned', reset($messages)->message);
  }

  /**
   * Test that the site superuser (uid 1) can never be updated via import.
   */
  public function testSuperuserCannotBeUpdated() {
    $data = [
      ['ID', 'Email'],
      ['1', 'hijacked@example.com'],
    ];
    $mapping = [
      ['target' => 'uid', 'source' => 'ID'],
      ['target' => 'mail', 'source' => 'Email'],
    ];

    $this->importUserCsv($data, $mapping);

    $superuser = $this->entityTypeManager->getStorage('user')->load(1);
    if ($superuser) {
      $this->assertNotEquals('hijacked@example.com', $superuser->getEmail());
    }

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('superuser account (uid 1)', reset($messages)->message);
  }

  /**
   * Test that setup emails are only sent for new accounts when opted in.
   */
  public function testSetupEmailOptIn() {
    $data = [
      ['Username', 'Email', 'Account Status'],
      ['optedin', 'optedin@example.com', 'Active'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'account_status', 'source' => 'Account Status'],
    ];

    $this->importUserCsv($data, $mapping, TRUE);

    $captured = \Drupal::state()->get('system.test_mail_collector', []);
    $sent = array_filter($captured, fn($mail) => ($mail['to'] ?? NULL) === 'optedin@example.com');
    $this->assertNotEmpty($sent, 'A setup email should be sent when opted in for a newly created account.');
  }

  /**
   * Test that setup emails are not sent when not opted in.
   */
  public function testSetupEmailNotSentByDefault() {
    $data = [
      ['Username', 'Email', 'Account Status'],
      ['notopted', 'notopted@example.com', 'Active'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'account_status', 'source' => 'Account Status'],
    ];

    $this->importUserCsv($data, $mapping, FALSE);

    $captured = \Drupal::state()->get('system.test_mail_collector', []);
    $sent = array_filter($captured, fn($mail) => ($mail['to'] ?? NULL) === 'notopted@example.com');
    $this->assertEmpty($sent, 'No setup email should be sent unless the importing admin opts in.');
  }

  /**
   * Installs the mukurtu_core field_pending field on the user entity,
   * mirroring its shipped field storage/field config (boolean, defaulting to
   * 1 for new accounts).
   */
  protected function installFieldPending(): void {
    if (!FieldStorageConfig::loadByName('user', 'field_pending')) {
      FieldStorageConfig::create([
        'field_name' => 'field_pending',
        'entity_type' => 'user',
        'type' => 'boolean',
      ])->save();
    }
    if (!FieldConfig::loadByName('user', 'user', 'field_pending')) {
      FieldConfig::create([
        'field_name' => 'field_pending',
        'entity_type' => 'user',
        'bundle' => 'user',
        'default_value' => [['value' => 1]],
      ])->save();
    }
  }

  /**
   * Test that importing a new user with Account Status=Blocked results in
   * field_pending being cleared rather than left at its field-storage
   * default of 1.
   *
   * Regression coverage: field_pending's storage default is 1. The
   * interactive user-edit form explicitly clears it to 0 when "Blocked" is
   * selected (see FormHooks::userStatusPreSaveSubmit()); the unified
   * 'account_status' import target (AccountStatusLookup +
   * ProtocolAwareUserContent::applyAccountStatus()) mirrors that same
   * three-state model.
   */
  public function testBlockedAccountStatusClearsFieldPending(): void {
    $this->installFieldPending();

    $data = [
      ['Username', 'Email', 'Account Status'],
      ['blockeduser', 'blockeduser@example.com', 'Blocked'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'account_status', 'source' => 'Account Status'],
    ];

    $result = $this->importUserCsv($data, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'blockeduser']);
    $user = reset($users);
    $this->assertNotFalse($user);
    $this->assertFalse($user->isActive());
    $this->assertEquals(0, $user->get('field_pending')->value, 'A user imported as Blocked must not default to appearing Pending.');
  }

  /**
   * Test that Account Status=Pending sets both status=0 and field_pending=1.
   */
  public function testPendingAccountStatusSetsFieldPending(): void {
    $this->installFieldPending();

    $data = [
      ['Username', 'Email', 'Account Status'],
      ['explicitlypending', 'explicitlypending@example.com', 'Pending'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'account_status', 'source' => 'Account Status'],
    ];

    $result = $this->importUserCsv($data, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'explicitlypending']);
    $user = reset($users);
    $this->assertNotFalse($user);
    $this->assertFalse($user->isActive());
    $this->assertEquals(1, $user->get('field_pending')->value);
  }

  /**
   * Test that a new account defaults to Active when Account Status isn't
   * mapped at all.
   */
  public function testUnmappedAccountStatusDefaultsToActiveForNewAccount(): void {
    $this->installFieldPending();

    $data = [
      ['Username', 'Email'],
      ['defaultactive', 'defaultactive@example.com'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
    ];

    $result = $this->importUserCsv($data, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'defaultactive']);
    $user = reset($users);
    $this->assertNotFalse($user);
    $this->assertTrue($user->isActive(), 'A new account with no Account Status mapped must default to Active.');
    $this->assertEquals(0, $user->get('field_pending')->value);
  }

  /**
   * Test that re-importing an existing account with Account Status blank
   * leaves its current status untouched, rather than resetting it to
   * Active.
   */
  public function testBlankAccountStatusLeavesExistingAccountUnchanged(): void {
    $this->installFieldPending();

    $blocked = $this->createUser([], NULL, FALSE, ['status' => 0]);

    $data = [
      ['ID', 'Account Status'],
      [$blocked->id(), ''],
    ];
    $mapping = [
      ['target' => 'uid', 'source' => 'ID'],
      ['target' => 'account_status', 'source' => 'Account Status'],
    ];

    $result = $this->importUserCsv($data, $mapping);
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $updated = $this->entityTypeManager->getStorage('user')->load($blocked->id());
    $this->assertFalse($updated->isActive(), 'A blank Account Status on an update row must not reactivate an existing account.');
  }

  /**
   * Test that an unrecognized Account Status value fails the row.
   */
  public function testInvalidAccountStatusFailsRow(): void {
    $data = [
      ['Username', 'Email', 'Account Status'],
      ['badstatus', 'badstatus@example.com', 'Frobnicated'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'account_status', 'source' => 'Account Status'],
    ];

    $this->importUserCsv($data, $mapping);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'badstatus']);
    $this->assertEmpty($users, 'A row with an unrecognized Account Status value must not create the account.');

    $messages = iterator_to_array($this->lastMigration->getIdMap()->getMessages());
    $this->assertNotEmpty($messages);
    $this->assertStringContainsString('is not a valid Account Status', reset($messages)->message);
  }

}
