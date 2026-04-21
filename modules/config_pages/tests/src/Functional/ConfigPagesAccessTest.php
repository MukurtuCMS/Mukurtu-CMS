<?php

namespace Drupal\Tests\config_pages\Functional;

use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ConfigPages access control functionality.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesAccessTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['config_pages'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The config page type for testing.
   *
   * @var \Drupal\config_pages\Entity\ConfigPagesType
   */
  protected $configPageType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a config page type with a custom menu path.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_access_type',
      'label' => 'Test Access Type',
      'context' => [
        'show_warning' => '',
        'group' => [],
      ],
      'menu' => [
        'path' => '/test-custom-config-page',
        'weight' => 0,
        'description' => 'Test page for access control.',
      ],
      'token' => FALSE,
    ]);
    $this->configPageType->save();

    // Rebuild router to include the new custom route.
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests that canonical route respects entity access.
   */
  public function testCanonicalRouteAccessControl() {
    // Create a user with permission to edit config pages.
    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_access_type config page entity',
    ]);
    $this->drupalLogin($user);

    // Without the test module hook, access should be allowed.
    $this->drupalGet('admin/structure/config_pages/test_access_type/edit');
    $this->assertSession()->statusCodeEquals(200);

    // Enable the test module that implements hook_config_pages_access().
    \Drupal::service('module_installer')->install(['config_pages_test']);
    $this->rebuildContainer();
    $this->container->get('router.builder')->rebuild();

    // With the test module hook denying access, canonical route should be
    // forbidden.
    $this->drupalGet('admin/structure/config_pages/test_access_type/edit');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that custom routes respect entity access.
   */
  public function testCustomRouteAccessControl() {
    // Create a user with permission to edit config pages.
    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_access_type config page entity',
    ]);
    $this->drupalLogin($user);

    // Without the test module hook, access should be allowed.
    $this->drupalGet('test-custom-config-page');
    $this->assertSession()->statusCodeEquals(200);

    // Enable the test module that implements hook_config_pages_access().
    \Drupal::service('module_installer')->install(['config_pages_test']);
    $this->rebuildContainer();
    $this->container->get('router.builder')->rebuild();

    // With the test module hook denying access, custom route should also be
    // forbidden.
    $this->drupalGet('test-custom-config-page');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that both routes behave consistently with entity access.
   */
  public function testConsistentAccessBehavior() {
    // Create a user with permission to edit config pages.
    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_access_type config page entity',
    ]);
    $this->drupalLogin($user);

    // Enable the test module that implements hook_config_pages_access().
    \Drupal::service('module_installer')->install(['config_pages_test']);
    $this->rebuildContainer();
    $this->container->get('router.builder')->rebuild();

    // Both routes should consistently deny access.
    $this->drupalGet('admin/structure/config_pages/test_access_type/edit');
    $canonicalStatus = $this->getSession()->getStatusCode();

    $this->drupalGet('test-custom-config-page');
    $customStatus = $this->getSession()->getStatusCode();

    // Both should return 403 (forbidden).
    $this->assertEquals(403, $canonicalStatus, 'Canonical route should deny access');
    $this->assertEquals(403, $customStatus, 'Custom route should deny access');
    $this->assertEquals($canonicalStatus, $customStatus, 'Both routes should have consistent access behavior');
  }

  /**
   * Tests access with insufficient permissions.
   */
  public function testInsufficientPermissions() {
    // Create a user without config pages permissions.
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    // Both routes should deny access due to insufficient permissions.
    $this->drupalGet('admin/structure/config_pages/test_access_type/edit');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('test-custom-config-page');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests access for users with proper permissions but denied by entity access.
   */
  public function testEntityAccessDenialWithProperPermissions() {
    // Enable the test module first.
    \Drupal::service('module_installer')->install(['config_pages_test']);
    $this->rebuildContainer();
    $this->container->get('router.builder')->rebuild();

    // Create a user with all necessary permissions.
    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_access_type config page entity',
      'administer config_pages types',
    ]);
    $this->drupalLogin($user);

    // Even with proper permissions, entity access should deny access.
    $this->drupalGet('admin/structure/config_pages/test_access_type/edit');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('test-custom-config-page');
    $this->assertSession()->statusCodeEquals(403);
  }

}
