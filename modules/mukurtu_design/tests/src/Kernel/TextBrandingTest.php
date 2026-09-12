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
   * Text branding gets a wider grid cell than a logo image does.
   *
   * .header__logo is one column of a grid sized for a ~100px logo, so a
   * wordmark wrapped into a four-line stack and pushed the header to 176px.
   * Widening the cell means moving where the nav starts, so both are keyed
   * off the same :has() condition and have to stay in step.
   */
  public function testTextBrandingWidensTheGridCell(): void {
    $css = $this->css();

    $this->assertStringContainsString('.site-header:has(.header__logo-text)', $css);
    $this->assertMatchesRegularExpression(
      '/\.site-header:has\(\.header__logo-text\)[^{]*\.header__logo\s*\{[^}]*grid-column/',
      $css,
      'The branding cell spans more columns.'
    );
    $this->assertMatchesRegularExpression(
      '/:has\(\.header__logo-text\)\s+\.header-nav\s*\{[^}]*grid-column/',
      $css,
      'The nav starts after the widened branding cell.'
    );
  }

  /**
   * The name scales with the viewport rather than wrapping.
   */
  public function testTheNameScalesToFit(): void {
    $this->assertMatchesRegularExpression(
      '/\.header__logo-text\s*\{[^}]*font-size:\s*clamp\(/',
      $this->css()
    );
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
