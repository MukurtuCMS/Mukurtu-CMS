<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\Core\Url;
use Drupal\mukurtu_design\Controller\DesignSettingsRedirectController;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests where the design settings form lives.
 *
 * The form was at /admin/config/color-settings until it gained background
 * image settings, which made that path describe only half of what it does.
 * Bookmarks and docs.mukurtu.org point at the old URL, so it has to keep
 * working.
 */
#[Group('mukurtu_design')]
class DesignSettingsRoutingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'mukurtu_design'];

  /**
   * The form is served from the design settings path.
   */
  public function testFormIsAtTheDesignSettingsPath(): void {
    $this->assertSame(
      '/admin/config/design-settings',
      Url::fromRoute('mukurtu_design.settings')->toString()
    );
  }

  /**
   * The old colour settings path still resolves.
   */
  public function testLegacyPathStillExists(): void {
    $route = \Drupal::service('router.route_provider')
      ->getRouteByName('mukurtu_design.settings_legacy');

    $this->assertSame('/admin/config/color-settings', $route->getPath());
    // The routing file writes a leading backslash; ::class does not.
    $this->assertSame(
      DesignSettingsRedirectController::class . '::settings',
      ltrim($route->getDefault('_controller'), '\\')
    );
  }

  /**
   * Both paths require the same permission.
   *
   * A redirect that is easier to reach than its destination would be a small
   * disclosure of its own.
   */
  public function testBothPathsRequireTheSamePermission(): void {
    $provider = \Drupal::service('router.route_provider');

    $this->assertSame(
      $provider->getRouteByName('mukurtu_design.settings')->getRequirement('_permission'),
      $provider->getRouteByName('mukurtu_design.settings_legacy')->getRequirement('_permission')
    );
  }

  /**
   * The redirect is permanent, and points at the form.
   */
  public function testRedirectIsPermanent(): void {
    $controller = \Drupal::classResolver(DesignSettingsRedirectController::class);

    $response = $controller->settings();

    $this->assertSame(301, $response->getStatusCode());
    $this->assertStringEndsWith('/admin/config/design-settings', $response->getTargetUrl());
  }

}
