<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the theme's support for text-based site branding.
 *
 * Core's branding block can show the site name instead of an uploaded logo,
 * but the theme's override printed the block content as one blob, so the name
 * came out as a bare text node and fell back to ordinary link styling: 16px,
 * regular weight, brand red, underlined. See #1037.
 *
 * These are structural guards. The rendering itself is verified on a real
 * site, where the markup, the type and the contrast can all be measured; the
 * numbers are in the pull request.
 */
#[Group('mukurtu_design')]
class TextBrandingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Returns the branding template source.
   */
  protected function template(): string {
    $path = \Drupal::service('extension.list.theme')->getPath('mukurtu_v4');

    return file_get_contents($path . '/templates/block/block--mukurtu-v4-branding.html.twig');
  }

  /**
   * Returns the compiled stylesheet.
   */
  protected function css(): string {
    $path = \Drupal::service('extension.list.theme')->getPath('mukurtu_v4');

    return file_get_contents($path . '/css/style.css');
  }

  /**
   * The site name is wrapped so it can be styled.
   */
  public function testSiteNameHasItsOwnHook(): void {
    $this->assertStringContainsString('header__logo-text', $this->template());
    $this->assertStringContainsString('.header__logo-text', $this->css(), 'The hook is styled.');
  }

  /**
   * The wrapper is skipped when the site name is switched off.
   *
   * The content.site_name value is present and truthy even when the block has
   * the name disabled, so a naive check emitted an empty span next to the
   * logo. The template has to test the rendered output instead.
   */
  public function testTheWrapperIsSkippedWhenEmpty(): void {
    $template = $this->template();

    $this->assertStringContainsString('content.site_name|render|trim', $template);
    $this->assertStringNotContainsString('{% if content.site_name %}', $template);
  }

  /**
   * Branding keeps the fixed front-page link, not a hardcoded path.
   *
   * Restored in #2192 and easy to lose again while editing this template.
   */
  public function testBrandingLinksToTheFrontPage(): void {
    $template = $this->template();

    $this->assertStringContainsString("path('<front>')", $template);
    $this->assertStringContainsString('rel="home"', $template);
    $this->assertStringNotContainsString('href="/"', $template);
  }

  /**
   * Text branding follows the header treatment over a background image.
   *
   * The link sets its own colour, so without this it stays brand red on the
   * scrim whichever treatment is chosen.
   */
  public function testBrandingFollowsTheHeaderTreatment(): void {
    $this->assertMatchesRegularExpression(
      '/\.site-header--has-background\s+\.header__logo\s+a\s*\{[^}]*color:\s*inherit/',
      $this->css()
    );
  }

}
