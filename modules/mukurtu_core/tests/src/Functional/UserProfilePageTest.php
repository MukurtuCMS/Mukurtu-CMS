<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests /user routing and profile view access for issue #128.
 *
 * @group mukurtu_core
 */
class UserProfilePageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'mukurtu';

  /**
   * Anonymous visitors are redirected from /user to the login form.
   */
  public function testAnonymousUserPageRedirectsToLogin(): void {
    $this->drupalGet('/user');
    $this->assertSession()->addressEquals('/user/login');
  }

  /**
   * Logged-in users visiting /user are redirected to their own profile.
   */
  public function testLoggedInUserPageRedirectsToOwnProfile(): void {
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);
    $this->drupalGet('/user');
    $this->assertSession()->addressEquals('/user/' . $account->id());
  }

  /**
   * A plain authenticated user can view another user's profile.
   */
  public function testAuthenticatedUserCanViewOtherProfile(): void {
    $viewer = $this->drupalCreateUser();
    $other = $this->drupalCreateUser();
    $this->drupalLogin($viewer);
    $this->drupalGet('/user/' . $other->id());
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Anonymous visitors cannot view any user's profile.
   */
  public function testAnonymousCannotViewProfile(): void {
    $account = $this->drupalCreateUser();
    $this->drupalGet('/user/' . $account->id());
    $this->assertSession()->statusCodeEquals(403);
  }

}
