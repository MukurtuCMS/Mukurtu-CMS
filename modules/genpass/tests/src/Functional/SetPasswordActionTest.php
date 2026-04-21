<?php

declare(strict_types=1);

namespace Drupal\Tests\genpass\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests functionality of the UserSetRandomPassword action.
 *
 * @group genpass
 */
class SetPasswordActionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'views',
    'genpass',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An admin user with "administer users".
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp():void {
    parent::setUp();

    // Create administrator user.
    $this->adminUser = $this->drupalCreateUser(['administer users']);

    // Create normal user. This user not directly accessed, but does have its
    // password reset as part of the test.
    $this->drupalCreateUser();

    // Log in as the admin.
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests the Generate Password action UserSetRandomPassword.
   */
  public function testGenpassActionResetPassword() {

    // Action should be installed and available as an option in Action select.
    $this->drupalGet('admin/people');
    $this->assertSession()->pageTextContains('Set new random password for user(s)');

    $edit = [
      // User ordering on admin/people is entirely random so testing for a
      // response that includes the user display name causes random failures.
      'user_bulk_form[0]' => TRUE,
      'action' => 'genpass_set_random_password',
    ];
    $this->submitForm($edit, 'Apply to selected items');
    $this->assertSession()->pageTextContains('Set new random password for user(s) was applied to 1 item.');
    $this->assertSession()->pageTextMatchesCount(1, '/Password for /');

    // Reset password for multiple users.
    $edit = [
      'user_bulk_form[0]' => TRUE,
      'user_bulk_form[1]' => TRUE,
      'action' => 'genpass_set_random_password',
    ];
    $this->submitForm($edit, 'Apply to selected items');
    $this->assertSession()->pageTextContains('Set new random password for user(s) was applied to 2 items.');
    $this->assertSession()->pageTextMatchesCount(2, '/Password for /');
  }

}
