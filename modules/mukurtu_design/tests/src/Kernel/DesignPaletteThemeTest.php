<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\Core\Theme\ActiveTheme;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_design\DesignPalette;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that DesignPalette::enablePalette() attaches the palette library
 * based on which theme is actually rendering, not just whether the current
 * route is flagged _admin_route - Entity Browser's own modal route
 * (EntityBrowser::route()) is one such admin-flagged route that still
 * renders with this site's default front-end theme for anonymous/most
 * authenticated visitors (they lack "view the administration theme"), so
 * a route-based check alone would incorrectly skip it.
 */
#[Group('mukurtu_design')]
class DesignPaletteThemeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'mukurtu_design'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['mukurtu_design']);
  }

  protected function setActiveTheme(string $name): void {
    // ThemeManager::setActiveTheme() unconditionally calls
    // ThemeInitialization::loadActiveTheme(), which - if 'engine' is
    // truthy (the ActiveTheme default is 'twig') - tries to call load() on
    // 'extension' as if it were a real \Drupal\Core\Extension\Extension
    // object; the default there is just the string 'html.twig'. An empty
    // engine short-circuits that block entirely, which is all this test
    // needs - it only cares what getName() returns.
    \Drupal::theme()->setActiveTheme(new ActiveTheme(['name' => $name, 'engine' => '']));
  }

  public function testPaletteAttachedWhenActiveThemeIsDefault(): void {
    $this->config('system.theme')->set('default', 'mukurtu_v4')->save();
    $this->setActiveTheme('mukurtu_v4');

    $attachments = [];
    \Drupal::classResolver(DesignPalette::class)->enablePalette($attachments);

    $libraries = $attachments['#attached']['library'] ?? [];
    $this->assertNotEmpty(array_filter($libraries, fn ($library) => str_starts_with($library, 'mukurtu_v4/palette.')));
  }

  public function testPaletteNotAttachedWhenActiveThemeIsNotDefault(): void {
    $this->config('system.theme')->set('default', 'mukurtu_v4')->save();
    $this->setActiveTheme('gin');

    $attachments = [];
    \Drupal::classResolver(DesignPalette::class)->enablePalette($attachments);

    $libraries = $attachments['#attached']['library'] ?? [];
    $this->assertEmpty(array_filter($libraries, fn ($library) => str_starts_with($library, 'mukurtu_v4/palette.')));
  }

}
