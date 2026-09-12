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
   * Text branding turns the header into a flex row from lg.
   *
   * The header is a 12-column grid built around a ~100px logo. At 1280px that
   * leaves branding 65px, narrower than the word "Peoples'", so a wordmark
   * breaks one word per line whatever the font size. Taking more columns
   * collapses the nav to the mobile menu instead. The nav's content is a fixed
   * 931px, so a flex row lets it claim that and gives branding the remainder -
   * 269px at 1280px, which is enough for one line.
   */
  public function testTextBrandingUsesFlexFromLg(): void {
    $css = $this->css();

    $this->assertMatchesRegularExpression(
      '/\.site-header:has\(\.header__logo-text\)\s*\{[^}]*display:\s*flex/',
      $css,
      'The header becomes a flex row when the branding is text.'
    );
    $this->assertMatchesRegularExpression(
      '/:has\(\.header__logo-text\)[^{]*\.header-nav\s*\{[^}]*inline-size:\s*auto/',
      $css,
      "The nav's mobile full width is cleared, or it claims the whole row."
    );
    $this->assertMatchesRegularExpression(
      '/\\.site-header:has\\(\\.header__logo-text\\)\\s*\\{[^}]*flex-wrap:\\s*wrap/',
      $css,
      'The header stacks rather than crushing the wordmark or dropping the menu.'
    );
  }

  /**
   * The rules never touch the logo-image case.
   *
   * Every one is scoped by :has(.header__logo-text). Verified on a real site:
   * with a logo the header measures 142px, exactly as it does on main.
   */
  public function testLogoImageCaseIsUntouched(): void {
    $css = $this->css();

    preg_match_all('/(^|\})([^{}]*header__logo[^{}]*)\{/m', $css, $m);
    foreach ($m[2] as $selector) {
      $selector = trim($selector);
      if ($selector === '' || str_starts_with($selector, '@')) {
        continue;
      }
      if (str_contains($selector, 'header__logo-text')) {
        continue;
      }
      $this->assertStringNotContainsString(
        'display: flex',
        $selector,
        "Layout changes must be behind :has(.header__logo-text): $selector"
      );
    }
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
