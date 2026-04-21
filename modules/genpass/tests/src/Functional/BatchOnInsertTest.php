<?php

declare(strict_types=1);

namespace Drupal\Tests\genpass\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the ajax interaction and helpers in the admin form.
 *
 * @group genpass
 */
class BatchOnInsertTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'genpass',
    'genpass_batch',
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
   * Test a batch_set call during user create.
   */
  public function testUserHookBatch(): void {
    $this->drupalGet('admin/people/create');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Add user');

    // Create a user using the admin form. This will trigger hook_user_insert()
    // in the genpass_batch test module.
    $edit = [
      'mail' => 'authenticated.test@drupal.org',
      'name' => 'test_authenticated',
    ];
    $this->submitForm($edit, 'Create new account');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error. Try again later.');
    $this->assertSession()->pageTextContains('Created a new user account for test_authenticated. No email has been sent.');

    $this->drupalGet('admin/people');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('test_authenticated');
  }

}
