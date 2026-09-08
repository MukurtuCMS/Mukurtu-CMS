<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the logo/favicon preview injected into the core theme settings form.
 *
 * Core's \Drupal\system\Form\ThemeSettingsForm::buildForm() is invoked with
 * no theme argument on the global settings form
 * (/admin/appearance/settings), so its $theme parameter default of '' (not
 * NULL) ends up in the form's build info args. Before the fix, this hook
 * used `$form_state->getBuildInfo()['args'][0] ?? $default`, and since ''
 * is not NULL, the fallback never triggered: ThemeSettingsProvider::
 * getSetting() was called with an empty-string theme, which isn't a real
 * theme, so logo.url/favicon.url were always NULL and no preview was ever
 * added on the global form.
 *
 * @see mukurtu_core_form_system_theme_settings_alter()
 */
#[Group('mukurtu_core')]
class ThemeSettingsAlterLogoFaviconPreviewTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    \Drupal::service('theme_installer')->install(['stark']);

    // mukurtu_core is not in $modules (its dependency chain pulls in many
    // modules unrelated to this hook), so extension.list.module cannot
    // resolve it here; load the .module file directly by its known path
    // relative to this test instead, matching this codebase's convention
    // for testing a single procedural hook implementation in isolation.
    require_once dirname(__DIR__, 3) . '/mukurtu_core.module';
  }

  /**
   * Builds a synthetic $form array shaped like core's ThemeSettingsForm.
   *
   * Includes the '#states' => ['invisible' => ...] core puts on 'settings'
   * (visible only once "Use the logo/favicon supplied by the theme" is
   * unchecked), so tests can assert the injected preview lands outside
   * that gated container rather than merely proving the key exists.
   *
   * @see \Drupal\system\Form\ThemeSettingsForm::buildForm()
   */
  protected function coreShapedForm(): array {
    $states = ['invisible' => ['input[name="default_logo"]' => ['checked' => TRUE]]];
    return [
      'logo' => ['default_logo' => [], 'settings' => ['#states' => $states]],
      'favicon' => ['default_favicon' => [], 'settings' => ['#states' => $states]],
    ];
  }

  /**
   * Builds a form state whose build info args match the given theme form.
   */
  protected function formStateWithArgs(array $args): FormState {
    $form_state = new FormState();
    $form_state->addBuildInfo('args', $args);
    return $form_state;
  }

  /**
   * The global settings form (build arg '') gets a preview from the site's
   * default theme now that '' is treated the same as an absent arg.
   */
  public function testPreviewRendersOnGlobalSettingsFormWithDefaultTheme(): void {
    $form = $this->coreShapedForm();
    $form_state = $this->formStateWithArgs(['']);

    mukurtu_core_form_system_theme_settings_alter($form, $form_state, 'system_theme_settings');

    $this->assertArrayHasKey('logo_preview', $form['logo']);
    $this->assertSame('img', $form['logo']['logo_preview']['#tag']);
    $this->assertStringContainsString('logo.svg', $form['logo']['logo_preview']['#attributes']['src']);

    $this->assertArrayHasKey('favicon_preview', $form['favicon']);
    $this->assertSame('img', $form['favicon']['favicon_preview']['#tag']);
    $this->assertStringContainsString('favicon.ico', $form['favicon']['favicon_preview']['#attributes']['src']);
  }

  /**
   * The preview must NOT be nested inside 'settings': core wraps that
   * container in '#states' => ['invisible' => [default_logo checked]],
   * and 'logo.use_default'/'favicon.use_default' default to TRUE on a
   * fresh site, so a preview nested there would be display:none on every
   * untouched install - precisely the case where showing the currently
   * active (theme-supplied) logo/favicon is most useful.
   */
  public function testPreviewNotHiddenInsideStatesGatedContainer(): void {
    $form = $this->coreShapedForm();
    $form_state = $this->formStateWithArgs(['']);

    mukurtu_core_form_system_theme_settings_alter($form, $form_state, 'system_theme_settings');

    $this->assertArrayNotHasKey('logo_preview', $form['logo']['settings']);
    $this->assertArrayNotHasKey('favicon_preview', $form['favicon']['settings']);
  }

  /**
   * A custom saved logo/favicon path (the exact QA repro: "Use the logo
   * supplied by the theme" off + a custom path) is reflected in the preview.
   */
  public function testPreviewRendersCustomSavedLogoAndFavicon(): void {
    \Drupal::configFactory()->getEditable('system.theme.global')
      ->set('logo.use_default', FALSE)
      ->set('logo.path', 'core/misc/druplicon.png')
      ->set('favicon.use_default', FALSE)
      ->set('favicon.path', 'core/misc/favicon.ico')
      ->save();

    $form = $this->coreShapedForm();
    $form_state = $this->formStateWithArgs(['']);

    mukurtu_core_form_system_theme_settings_alter($form, $form_state, 'system_theme_settings');

    $this->assertStringContainsString('druplicon.png', $form['logo']['logo_preview']['#attributes']['src']);
    $this->assertStringContainsString('favicon.ico', $form['favicon']['favicon_preview']['#attributes']['src']);
  }

  /**
   * The per-theme settings form (/admin/appearance/settings/{theme}) passes
   * a real, non-empty theme machine name as the build arg, and must keep
   * working exactly as before the fix.
   */
  public function testPreviewRendersOnPerThemeSettingsForm(): void {
    $form = $this->coreShapedForm();
    $form_state = $this->formStateWithArgs(['stark']);

    mukurtu_core_form_system_theme_settings_alter($form, $form_state, 'system_theme_settings_theme_form');

    $this->assertArrayHasKey('logo_preview', $form['logo']);
    $this->assertStringContainsString('logo.svg', $form['logo']['logo_preview']['#attributes']['src']);
    $this->assertArrayHasKey('favicon_preview', $form['favicon']);
  }

  /**
   * Nothing is added, and nothing errors, when the logo/favicon settings
   * elements are absent from the form (e.g. file.module disabled, which
   * removes both fieldsets from core's ThemeSettingsForm entirely).
   */
  public function testNoPreviewWhenSettingsElementsAbsent(): void {
    $form = [];
    $form_state = $this->formStateWithArgs(['']);

    mukurtu_core_form_system_theme_settings_alter($form, $form_state, 'system_theme_settings');

    $this->assertArrayNotHasKey('logo', $form);
    $this->assertArrayNotHasKey('favicon', $form);
  }

}
