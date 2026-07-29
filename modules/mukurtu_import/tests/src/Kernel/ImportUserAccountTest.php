<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

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
      ['Username', 'Email', 'Status', 'Roles'],
      ['newperson', 'newperson@example.com', '1', 'editor'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'status', 'source' => 'Status'],
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
      ['Username', 'Email', 'Status'],
      ['optedin', 'optedin@example.com', '1'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'status', 'source' => 'Status'],
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
      ['Username', 'Email', 'Status'],
      ['notopted', 'notopted@example.com', '1'],
    ];
    $mapping = [
      ['target' => 'name', 'source' => 'Username'],
      ['target' => 'mail', 'source' => 'Email'],
      ['target' => 'status', 'source' => 'Status'],
    ];

    $this->importUserCsv($data, $mapping, FALSE);

    $captured = \Drupal::state()->get('system.test_mail_collector', []);
    $sent = array_filter($captured, fn($mail) => ($mail['to'] ?? NULL) === 'notopted@example.com');
    $this->assertEmpty($sent, 'No setup email should be sent unless the importing admin opts in.');
  }

}
