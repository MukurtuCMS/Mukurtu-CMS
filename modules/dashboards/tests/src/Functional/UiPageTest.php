<?php

namespace Drupal\Tests\dashboards\Functional;

use Drupal\Tests\BrowserTestBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests that the Rules UI pages are reachable.
 *
 * @group dashboards
 */
class UiPageTest extends BrowserTestBase {
  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['dashboards'];

  /**
   * User with proper permissions for module configuration.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * User with content access.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $normalUser;

  /**
   * Testing dashboard name.
   *
   * @var string
   */
  public const DASHBOARD_NAME = "test_dashboard";

  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'olivero';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install(['claro']);
    $this->adminUser = $this->drupalCreateUser([
      'administer dashboards',
      'view the administration theme',
    ]);
    $this->normalUser = $this->drupalCreateUser(['access content']);
    $config = $this->config('system.theme');
    $config->set('admin', 'claro');
    $config->set('default', 'olivero');
    $config->save();
  }

  /**
   * Testing permission "administer dashboards".
   */
  public function testAdminRoute() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/dashboards');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->responseContains('core/themes/claro/css/');
    $this->assertSession()->pageTextContains('New dashboard');
  }

  /**
   * Testing permission "administer dashboards".
   */
  public function testNoAccessAdminRoute() {
    $this->drupalLogin($this->normalUser);
    $this->drupalGet('admin/structure/dashboards');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);
  }

  /**
   * Testing dashboard with frontend mode.
   */
  public function testFrontendMode() {
    $this->drupalLogin($this->adminUser);
    $this->setupCreateDashboard(1);
    $this->drupalGet('dashboard/' . static::DASHBOARD_NAME);
    $session = $this->assertSession();
    $session->statusCodeEquals(Response::HTTP_OK);
    $session->responseContains('core/themes/olivero/css/');
  }

  /**
   * Testing dashboard with backend mode.
   */
  public function testBackendMode() {
    $this->setupCreateDashboard(0);
    $user = $this->drupalCreateUser([
      'access content',
      'can view test_dashboard dashboard',
      'view the administration theme',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('dashboard/' . static::DASHBOARD_NAME);
    $session = $this->assertSession();
    $session->statusCodeEquals(Response::HTTP_OK);
    $session->responseContains('core/themes/claro/css/');

    $user = $this->drupalCreateUser([
      'access content',
      'can view test_dashboard dashboard',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('dashboard/' . static::DASHBOARD_NAME);
    $session = $this->assertSession();
    $session->statusCodeEquals(Response::HTTP_OK);
    $session->responseContains('core/themes/olivero/css/');
  }

  /**
   * Testing user without access rights for dashboard.
   */
  public function testAccessUser() {
    $this->setupCreateDashboard();
    $this->drupalLogin($this->normalUser);
    $this->drupalGet('dashboard/' . static::DASHBOARD_NAME);
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);
  }

  /**
   * Test "Personalize" permission.
   */
  public function testPersonalizeUser() {
    $this->setupCreateDashboard();
    $user = $this->drupalCreateUser([
      'access content',
      'can view test_dashboard dashboard',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('dashboard/' . static::DASHBOARD_NAME);
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->pageTextNotContains('Personalize');

    $user = $this->drupalCreateUser([
      'access content',
      'can override test_dashboard dashboard',
      'can view test_dashboard dashboard',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('dashboard/' . static::DASHBOARD_NAME);
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->pageTextContains('Personalize');
  }

  /**
   * Testing personalizing.
   */
  public function testPersonalizing(): void {
    $this->setupCreateDashboard();
    $user = $this->drupalCreateUser([
      'access content',
      'can view test_dashboard dashboard',
      'can override test_dashboard dashboard',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Setup dashboard for testing purposes.
   */
  private function setupCreateDashboard($frontendOnly = 0) {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/dashboards');
    $this->clickLink('New dashboard');
    $this->submitForm([
      'id' => static::DASHBOARD_NAME,
      'admin_label' => static::DASHBOARD_NAME,
      'frontend' => $frontendOnly,
    ], 'Save');

    $this->assertSession()->pageTextContains(static::DASHBOARD_NAME);
    $this->assertSession()->pageTextContains('Default Dashboard');
  }

}
