<?php

declare(strict_types=1);

namespace Drupal\Tests\genpass\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\genpass\GenpassInterface;

/**
 * Tests functionality of the Generate Password module.
 *
 * @group genpass
 */
class GenpassTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = TRUE;

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'genpass',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with "administer account settings".
   *
   * And "administer users" permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp():void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer account settings',
      'administer users',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test Generate Password configs and create users by admin.
   */
  public function testGenpassConfigs(): void {
    foreach (static::configTestsDataProvider() as $case) {
      $this->doTestGenpassConfigs($case[0], $case[1]);
    }
  }

  /**
   * Test Generate Password configs and create users by admin.
   *
   * @param array $config
   *   Configuration values to set in form prior to submission.
   * @param array $expects
   *   Array of expected and not expected message text to check for.
   */
  protected function doTestGenpassConfigs(array $config, array $expects): void {
    // Load the accounts page and confirm it works.
    $this->drupalGet('admin/config/people/accounts');
    $this->assertSession()->statusCodeEquals(200);

    // Set the configuration values into an edit form submission.
    $this->submitForm($config, 'Save configuration');

    // Check for text that both exists and not.
    foreach (($expects['text'] ?? []) as $text) {
      $this->assertSession()->pageTextContains($text);
    }
    foreach (($expects['nottext'] ?? []) as $text) {
      $this->assertSession()->pageTextNotContains($text);
    }
  }

  /**
   * Data provider for testGenpassConfigs.
   *
   * NB: Each case must overwrite all options of the previous one as these now
   * use the same instance for each test case.
   *
   * @return \Generator
   *   An array of configuration options to set, and expected page text output.
   */
  public static function configTestsDataProvider(): \Generator {

    // Test to confirm that genpass_mode 0 will error when email_verify is on.
    yield [
      [
        'user_email_verification' => '1',
        'genpass_mode' => GenpassInterface::PASSWORD_REQUIRED,
      ],
      [
        'text' => ['User password entry option Users must enter a password on registration is not available when email verification is enabled.'],
        'nottext' => ['The configuration options have been saved.'],
      ],
    ];

    // Test to confirm that genpass_mode 1 will error when email_verify is on.
    yield [
      [
        'user_email_verification' => '1',
        'genpass_mode' => GenpassInterface::PASSWORD_OPTIONAL,
      ],
      [
        'text' => ['User password entry option Users may enter a password on registration is not available when email verification is enabled.'],
        'nottext' => ['The configuration options have been saved.'],
      ],
    ];

    // Test to confirm that email_verify turned off will allow genpass_mode 0.
    yield [
      [
        'user_email_verification' => '0',
        'genpass_mode' => GenpassInterface::PASSWORD_REQUIRED,
      ],
      [
        'text' => ['The configuration options have been saved.'],
      ],
    ];

    // Test the default options work.
    yield [
      [
        'user_email_verification' => '1',
        'genpass_mode' => GenpassInterface::PASSWORD_RESTRICTED,
      ],
      [
        'text' => ['The configuration options have been saved.'],
      ],
    ];
  }

  /**
   * Create a user via admin form and check output says password created.
   */
  public function testCreateUserByAdmin(): void {

    // Create the test_authenticated user.
    $this->drupalGet('admin/people/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Add user');
    $edit = [
      'mail' => 'authenticated.test@drupal.org',
      'name' => 'test_authenticated',
    ];
    $this->submitForm($edit, 'Create new account');

    $this->assertSession()->pageTextContains('Since you did not provide a password, it was generated automatically for this account.');
    $this->assertSession()->pageTextContains('Created a new user account for test_authenticated. No email has been sent.');
  }

  /**
   * Test Generate Password hide password field functionality.
   */
  public function testGenpassHidePasswordField(): void {

    // Allow admins to set passwords.
    $this->drupalGet('admin/config/people/accounts');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Admin password entry');

    // Allow admins to set password.
    $this->getSession()->getPage()->selectFieldOption(
      'genpass_admin_mode',
      (string) GenpassInterface::PASSWORD_ADMIN_SHOW
    );
    $this->getSession()->getPage()->pressButton('Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Create the test_authenticated user.
    $this->drupalGet('admin/people/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Provide a password for the new account in both fields.');
    $this->getSession()->getPage()->fillField('mail', 'authenticated.test@drupal.org');
    $this->getSession()->getPage()->fillField('Username', 'test_authenticated');
    $this->getSession()->getPage()->pressButton('Create new account');
    $this->assertSession()->pageTextContains('Since you did not provide a password, it was generated automatically for this account.');
    $this->assertSession()->pageTextContains('Created a new user account for test_authenticated. No email has been sent.');

    // Disallow admins to set passwords and not display passwords (default).
    $this->drupalGet('admin/config/people/accounts');
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->selectFieldOption(
      'genpass_admin_mode',
      (string) GenpassInterface::PASSWORD_ADMIN_HIDE
    );
    $this->getSession()->getPage()->selectFieldOption(
      'genpass_display',
      (string) GenpassInterface::PASSWORD_DISPLAY_NONE
    );
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Create the test_authenticated_two user. As ADMIN_HIDE and DISPLAY_NONE
    // are set, it will not contain the text "did not provide a password".
    $this->drupalGet('admin/people/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Provide a password for the new account in both fields.');
    $this->getSession()->getPage()->fillField('mail', 'authenticated.test2@drupal.org');
    $this->getSession()->getPage()->fillField('Username', 'test_authenticated_two');
    $this->getSession()->getPage()->pressButton('Create new account');
    $this->assertSession()->pageTextNotContains('Since you did not provide a password, it was generated automatically for this account.');
    $this->assertSession()->pageTextNotContains('The password is:');
    $this->assertSession()->pageTextContains('Created a new user account for test_authenticated_two. No email has been sent.');

    // Show passwords to admin.
    $this->drupalGet('admin/config/people/accounts');
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->selectFieldOption(
      'genpass_display',
      (string) GenpassInterface::PASSWORD_DISPLAY_ADMIN
    );
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Repeat, but display the password.
    $this->drupalGet('admin/people/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->fillField('mail', 'authenticated.test3@drupal.org');
    $this->getSession()->getPage()->fillField('Username', 'test_authenticated_three');
    $this->getSession()->getPage()->pressButton('Create new account');
    $this->assertSession()->pageTextNotContains('Since you did not provide a password, it was generated automatically for this account.');
    $this->assertSession()->pageTextContains('The password is:');
    $this->assertSession()->pageTextContains('Created a new user account for test_authenticated_three. No email has been sent.');
  }

}
