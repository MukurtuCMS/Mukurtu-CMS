<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\Core\Render\RenderContext;
use Drupal\Core\Template\Attribute;
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
   * Returns the compiled stylesheet, with comments stripped.
   *
   * Sass keeps /* ... *\/ comments in the output, so a comment that quotes a
   * declaration - "pinned at `flex: 0 0 auto` the nav kept its width" - is
   * indistinguishable from the declaration itself to a regex. Every assertion
   * here is about what the stylesheet does, so the prose goes first.
   */
  protected function css(): string {
    $path = \Drupal::service('extension.list.theme')->getPath('mukurtu_v4');
    $css = file_get_contents($path . '/css/style.css');

    return preg_replace('#/\*.*?\*/#s', '', $css);
  }

  /**
   * The site name is wrapped so it can be styled.
   */
  public function testSiteNameHasItsOwnHook(): void {
    $this->assertStringContainsString('header__logo-text', $this->template());
    $this->assertStringContainsString('.header__logo-text', $this->css(), 'The hook is styled.');
  }

  /**
   * Renders the branding template with the given parts switched on.
   *
   * The template is loaded by path rather than through a placed block. The
   * theme lives inside the install profile, and a kernel test has no active
   * profile, so theme_installer cannot reach it: core leaves $theme_list
   * undefined in ExtensionInstallStorage and the next config save dies in
   * schema discovery. Naming the profile instead pulls in the profile's whole
   * config override set, which fails on unrelated keys. Rendering the file
   * directly exercises the same Twig with none of that.
   *
   * The values mirror what SystemBrandingBlock::build() produces: a render
   * array per part, with the disabled ones set to NULL.
   */
  protected function renderBranding(bool $logo, bool $name, bool $slogan, string $site_name = 'Test site'): string {
    // The template calls path('<front>'), which needs a built router. Kernel
    // tests do not build one.
    $this->container->get('router.builder')->rebuild();

    // createTemplate() rather than load(): Drupal's Twig loader only serves
    // registered theme and module namespaces, and nothing registers a theme
    // that has not been installed.
    $template = $this->container->get('twig')->createTemplate($this->template());

    $variables = [
      'attributes' => new Attribute(),
      'title_prefix' => [],
      'title_suffix' => [],
      'content' => [
        'site_logo' => $logo ? ['#type' => 'html_tag', '#tag' => 'img', '#attributes' => ['src' => '/logo.svg', 'alt' => '']] : NULL,
        'site_name' => $name ? ['#markup' => $site_name] : NULL,
        'site_slogan' => $slogan ? ['#markup' => 'A slogan'] : NULL,
      ],
    ];

    // The template's |render filter needs a render context to collect
    // bubbleable metadata into, the same as any other render call.
    return (string) $this->container->get('renderer')->executeInRenderContext(
      new RenderContext(),
      static fn() => $template->render($variables),
    );
  }

  /**
   * The site name is escaped exactly once.
   *
   * The template tests emptiness with |render|trim, which returns a plain
   * string and drops the Markup wrapper. Printing that value escaped a second
   * time, so a site called "Arts & Culture" rendered as "Arts &amp;amp;
   * Culture". The fix is to test the rendered copy but print the original.
   */
  public function testTheSiteNameIsEscapedOnce(): void {
    $markup = $this->renderBranding(FALSE, TRUE, FALSE, "Peoples' Portal & Archive");

    $this->assertStringContainsString("Peoples' Portal &amp; Archive", $markup);
    $this->assertStringNotContainsString('&amp;amp;', $markup);
  }

  /**
   * The wrapper is skipped when the site name is switched off.
   *
   * The content.site_name value is present and truthy even when the block has
   * the name disabled, so a naive check emitted an empty span next to the
   * logo. The template has to test the rendered output instead.
   */
  public function testTheWrapperIsSkippedWhenEmpty(): void {
    $markup = $this->renderBranding(TRUE, FALSE, FALSE);

    $this->assertStringNotContainsString('header__logo-text', $markup);
    $this->assertStringNotContainsString('{% if content.site_name %}', $this->template());
  }

  /**
   * No empty link when every branding part is switched off.
   *
   * An <a> with no logo and no name is still a tab stop, with no accessible
   * name and nothing to see when it takes focus (WCAG 2.4.4, 4.1.2).
   */
  public function testNoEmptyLinkWhenNothingIsEnabled(): void {
    $markup = $this->renderBranding(FALSE, FALSE, FALSE);

    $this->assertStringNotContainsString('<a ', $markup);
    $this->assertStringNotContainsString('rel="home"', $markup);
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
   * A long unbreakable word cannot force the page sideways.
   *
   * `break-word` is not enough: it breaks lines but is ignored when the
   * browser computes min-content width, and branding is a grid item with the
   * default `min-width: auto`, so the cell stayed as wide as the whole word.
   * Measured at 123px past a 320px viewport before this (WCAG 1.4.10).
   */
  public function testTheWordmarkCanBreakALongWord(): void {
    $this->assertMatchesRegularExpression(
      '/\.header__logo-text\s*\{[^}]*overflow-wrap:\s*anywhere/',
      $this->css(),
      'break-word does not reduce min-content width; anywhere does.'
    );
  }

  /**
   * The nav can shrink, so it collapses instead of overflowing.
   *
   * nav-resize.js swaps in the mobile drawer when the primary nav <ul> wraps.
   * Pinned at `flex: 0 0 auto` the nav kept its 931px on a narrower row, so it
   * ran past the viewport instead - 19px at lg, and further again under a
   * text-spacing override (1.4.12) or a longer set of translated labels.
   */
  public function testTheNavCanShrinkSoItCollapses(): void {
    $css = $this->css();

    $this->assertMatchesRegularExpression(
      '/:has\(\.header__logo-text\)[^{]*\.header-nav\s*\{[^}]*flex:\s*0\s+1\s+auto/',
      $css,
      'A nav that cannot shrink overflows rather than collapsing.'
    );
    $this->assertDoesNotMatchRegularExpression(
      '/:has\(\.header__logo-text\)[^{]*\.header-nav\s*\{[^}]*flex:\s*0\s+0\s+auto/',
      $css
    );
  }

  /**
   * Focus rings over a background image take the treatment colour.
   *
   * --focus-color is a light blue and --brand-primary-dark a red; both measure
   * under 3:1 against one or other scrim, so the focus indicator failed 1.4.11
   * exactly where the text around it passed. currentcolor is the treatment
   * colour, which is chosen for contrast against that scrim.
   *
   * The menu button also needs `color: inherit`: a <button> takes `buttontext`
   * from the UA stylesheet rather than inheriting, so currentcolor resolved to
   * white under both treatments and drew a white ring on the white scrim.
   */
  public function testFocusRingsFollowTheHeaderTreatment(): void {
    $css = $this->css();

    $this->assertMatchesRegularExpression(
      '/\.site-header--has-background\s+\.header__logo\s+a:focus[^{]*\{[^}]*outline-color:\s*currentcolor/',
      $css,
      "The branding link's focus ring must contrast with the scrim."
    );
    $this->assertMatchesRegularExpression(
      '/\.site-header--has-background\s+\.mobile-nav-button\s*\{[^}]*color:\s*inherit/',
      $css,
      'Without this currentcolor on the button is not the treatment colour.'
    );
    $this->assertMatchesRegularExpression(
      '/\.site-header--has-background\s+\.mobile-nav-button:focus[^{]*\{[^}]*outline-color:\s*currentcolor/',
      $css,
      "The menu button's focus ring must contrast with the scrim."
    );
  }

  /**
   * The wordmark keeps a hover and focus affordance.
   *
   * `.header__logo a { color }` outsells the base `a:hover` rule on
   * specificity, so the wordmark had no hover state at all and the focus
   * outline was its only interactive signal.
   */
  public function testTheWordmarkHasAHoverAffordance(): void {
    $this->assertMatchesRegularExpression(
      '/\.header__logo a:hover[^{]*\{[^}]*text-decoration:\s*underline/',
      $this->css(),
      'The cue must not be colour alone (WCAG 1.4.1).'
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
