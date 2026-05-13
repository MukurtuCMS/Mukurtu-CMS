<?php

namespace Drupal\Tests\mukurtu_protocol\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests MukurtuUserSetRandomPasswordAction overrides genpass behavior.
 *
 * @group mukurtu_protocol
 */
class UserSetRandomPasswordActionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'mukurtu';

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * Bypass pre-existing flat_taxonomy schema validation in the mukurtu profile.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin = $this->drupalCreateUser(['administer users']);
    $this->drupalLogin($admin);

    // Enable test mail collection.
    $this->config('system.site')->set('mail', 'admin@example.com')->save();
    \Drupal::state()->set('system.test_mail_collector', []);
  }

  /**
   * Action still appears on admin/people.
   */
  public function testActionIsAvailable(): void {
    $this->drupalGet('admin/people');
    $this->assertSession()->pageTextContains('Set new random password');
  }

  /**
   * Applying the action sends a password reset email.
   */
  public function testActionSendsEmail(): void {
    $this->drupalCreateUser();

    $edit = [
      'user_bulk_form[0]' => TRUE,
      'action' => 'genpass_set_random_password',
    ];
    $this->drupalGet('admin/people');
    $this->submitForm($edit, 'Apply to selected items');

    $mails = \Drupal::state()->get('system.test_mail_collector', []);
    $this->assertNotEmpty($mails, 'A password reset email was sent.');
    $this->assertEquals('password_reset', end($mails)['key']);
  }

  /**
   * Status message is shown after the action runs.
   */
  public function testActionShowsStatusMessage(): void {
    $this->drupalCreateUser();

    $edit = [
      'user_bulk_form[0]' => TRUE,
      'action' => 'genpass_set_random_password',
    ];
    $this->drupalGet('admin/people');
    $this->submitForm($edit, 'Apply to selected items');

    $this->assertSession()->pageTextMatchesCount(1, '/password (reset email has been sent|for .+ has been reset)/i');
  }

  /**
   * Plaintext password is never exposed in the UI.
   */
  public function testPlaintextPasswordNotExposed(): void {
    $this->drupalCreateUser();

    $edit = [
      'user_bulk_form[0]' => TRUE,
      'action' => 'genpass_set_random_password',
    ];
    $this->drupalGet('admin/people');
    $this->submitForm($edit, 'Apply to selected items');

    // genpass original shows "Password for X is: <plaintext>"; our override
    // must never display the raw generated password.
    $this->assertSession()->pageTextNotMatches('/Password for .+ is:/');
  }

}
