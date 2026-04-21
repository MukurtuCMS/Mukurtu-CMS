<?php

declare(strict_types=1);

namespace Drupal\Tests\genpass\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests functionality of the Generate Password custom forms integration.
 *
 * @group genpass
 */
class GenpassFormsTest extends BrowserTestBase {

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
    'genpass_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with "administer users" permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer users']);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test Generate Password alter user forms.
   */
  public function testGenpassUserForms(): void {

    // Get the normal user creation page and confirm alterations are present.
    $this->drupalGet('admin/people/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('If left blank, a password will be generated for you.');
    $this->assertSession()->pageTextContains('This is recommended when auto-generating the password; otherwise, neither you nor the new user will know the password.');

    // Alter the default forms to turn off notify altering.
    genpass_test_userform_set_alter_mode('remove_default_notify');
    $this->drupalGet('admin/people/create');
    $this->assertSession()->pageTextContains('If left blank, a password will be generated for you.');
    $this->assertSession()->pageTextNotContains('This is recommended when auto-generating the password; otherwise, neither you nor the new user will know the password.');

    // Alter the default forms to turn off password altering.
    genpass_test_userform_set_alter_mode('remove_default_locations');
    $this->drupalGet('admin/people/create');
    $this->assertSession()->pageTextNotContains('If left blank, a password will be generated for you.');
    $this->assertSession()->pageTextNotContains('This is recommended when auto-generating the password; otherwise, neither you nor the new user will know the password.');

    // Alter the default form to remove it from all processing.
    genpass_test_userform_set_alter_mode('remove_default_form');
    $this->drupalGet('admin/people/create');
    $this->assertSession()->pageTextNotContains('If left blank, a password will be generated for you.');
    $this->assertSession()->pageTextNotContains('This is recommended when auto-generating the password; otherwise, neither you nor the new user will know the password.');

    // Also test with custom form pages to make sure they're altered too.
    genpass_test_userform_set_alter_mode('default');
    $this->drupalGet('genpass_test/user_hook_forms');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('If left blank, a password will be generated for you.');

    $this->drupalGet('genpass_test/user_hook_forms_alter');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('If left blank, a password will be generated for you.');

    genpass_test_userform_set_alter_mode('add_custom_form');
    $this->drupalGet('genpass_test/user_hook_forms_alter');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('If left blank, a password will be generated for you.');
  }

}
