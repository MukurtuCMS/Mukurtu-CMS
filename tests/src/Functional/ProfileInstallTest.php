<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests a fresh install of the profile.
 *
 * The install itself runs under strict config schema checking, so this
 * class fails if any shipped config doesn't match its schema (#2386).
 */
#[Group('mukurtu')]
class ProfileInstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'mukurtu';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The profile's Gin settings replace Gin's own on install.
   *
   * They live in the profile's config/optional so they aren't saved before
   * Gin and its schema are installed. Gin's teal preset is 4.48:1 against
   * white, under the 4.5:1 WCAG 2.1 AA requires (1.4.3), so the profile
   * ships a darker custom teal.
   */
  public function testGinSettingsOverrideApplies(): void {
    $settings = $this->config('gin.settings');
    $this->assertSame('custom', $settings->get('preset_accent_color'));
    $this->assertSame('#0e7873', $settings->get('accent_color'));
    $this->assertSame('gin', $this->config('system.theme')->get('admin'));
  }

}
