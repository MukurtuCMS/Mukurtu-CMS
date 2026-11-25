<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_design\CustomCss;
use Drupal\mukurtu_design\DesignPalette;
use Drupal\mukurtu_design\Form\MukurtuDesignSettingsForm;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the custom CSS setting on Design Settings (#125).
 */
#[Group('mukurtu_design')]
class CustomCssTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'mukurtu_design'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'mukurtu_design']);
    $this->config('system.theme')->set('default', 'mukurtu_v4')->save();
    // The first user is uid 1, which passes every permission check. Use it
    // up here so the permission tests below test real permissions.
    $this->createUser();
  }

  /**
   * Saves the given CSS to config.
   */
  protected function saveCss(string $css): void {
    $this->config(DesignPalette::SETTINGS)->set(CustomCss::KEY, $css)->save();
  }

  /**
   * The custom CSS stylesheets currently on disk.
   */
  protected function stylesheets(): array {
    if (!is_dir(CustomCss::DIRECTORY)) {
      return [];
    }
    $files = \Drupal::service('file_system')->scanDirectory(CustomCss::DIRECTORY, '/^' . CustomCss::FILE_PREFIX . '.*\.css$/');
    $names = array_column($files, 'filename');
    sort($names);
    return $names;
  }

  /**
   * Builds the mukurtu_v4 libraries the way the library alter sees them.
   */
  protected function libraries(): array {
    $libraries = [];
    \Drupal::classResolver(CustomCss::class)->alterThemeLibraries($libraries, 'mukurtu_v4');
    return $libraries;
  }

  /**
   * Runs the attachment step with the given theme rendering the page.
   */
  protected function attachmentsFor(string $theme): array {
    // A real ActiveTheme needs the theme installed, and mukurtu_v4 sits in
    // the profile, out of a kernel test's reach. Only its name matters here.
    $active_theme = $this->createMock(ActiveTheme::class);
    $active_theme->method('getName')->willReturn($theme);
    $theme_manager = $this->createMock(ThemeManagerInterface::class);
    $theme_manager->method('getActiveTheme')->willReturn($active_theme);

    $custom_css = new CustomCss(
      $this->container->get('config.factory'),
      $theme_manager,
      $this->container->get('file_system'),
      $this->container->get('file_url_generator'),
    );
    $attachments = [];
    $custom_css->attach($attachments);
    return $attachments;
  }

  /**
   * A fresh install ships the setting empty and writes no stylesheet.
   */
  public function testInstallsEmpty(): void {
    $this->assertSame('', $this->config(DesignPalette::SETTINGS)->get(CustomCss::KEY));
    $this->assertSame([], $this->stylesheets());
  }

  /**
   * Saving CSS writes it to a file named for its contents.
   */
  public function testSaveWritesStylesheet(): void {
    $css = 'body { outline: 2px solid red; }';
    $this->saveCss($css);

    $this->assertSame([basename(CustomCss::uri($css))], $this->stylesheets());
    $this->assertSame($css, file_get_contents(CustomCss::uri($css)));
  }

  /**
   * Changing the CSS replaces the old file, so no stale copy is left behind.
   */
  public function testChangeReplacesStylesheet(): void {
    $this->saveCss('a { color: red; }');
    $this->saveCss('a { color: blue; }');

    $this->assertSame([basename(CustomCss::uri('a { color: blue; }'))], $this->stylesheets());
  }

  /**
   * Clearing the CSS removes the file, and leaves the palette file alone.
   *
   * Both live in the same directory, and the palette file name also starts
   * with "custom-".
   */
  public function testClearingRemovesOnlyCustomCssFiles(): void {
    $this->config(DesignPalette::SETTINGS)->set('palette', 'custom')->save();
    $this->assertFileExists(DesignPalette::CUSTOM_PALETTE_CSS_URI);

    $this->saveCss('a { color: red; }');
    $this->saveCss('');

    $this->assertSame([], $this->stylesheets());
    $this->assertFileExists(DesignPalette::CUSTOM_PALETTE_CSS_URI);
  }

  /**
   * The library points at the current file, after the palette stylesheets.
   */
  public function testLibraryPointsAtCurrentFile(): void {
    $css = 'a { color: red; }';
    $this->saveCss($css);

    $stylesheets = $this->libraries()[CustomCss::LIBRARY]['css']['theme'];

    $this->assertCount(1, $stylesheets);
    $url = array_key_first($stylesheets);
    $this->assertStringEndsWith(basename(CustomCss::uri($css)), $url);
    $this->assertGreaterThan(1000000, $stylesheets[$url]['weight']);
    $this->assertFalse($stylesheets[$url]['preprocess']);
  }

  /**
   * With no CSS the library still exists, so attaching it is never an error.
   */
  public function testLibraryIsEmptyWithoutCss(): void {
    $this->assertSame(['css' => ['theme' => []]], $this->libraries()[CustomCss::LIBRARY]);
  }

  /**
   * Other extensions' libraries are not touched.
   */
  public function testOtherExtensionsUntouched(): void {
    $this->saveCss('a { color: red; }');
    $libraries = [];
    \Drupal::classResolver(CustomCss::class)->alterThemeLibraries($libraries, 'gin');

    $this->assertSame([], $libraries);
  }

  /**
   * A missing file is written again when the library is built.
   */
  public function testMissingFileIsRegenerated(): void {
    $css = 'a { color: red; }';
    $this->saveCss($css);
    unlink(CustomCss::uri($css));

    $this->libraries();

    $this->assertSame($css, file_get_contents(CustomCss::uri($css)));
  }

  /**
   * The CSS is attached when the site theme renders the page.
   */
  public function testAttachedOnSiteTheme(): void {
    $this->saveCss('a { color: red; }');

    $attachments = $this->attachmentsFor('mukurtu_v4');

    $this->assertContains('mukurtu_v4/' . CustomCss::LIBRARY, $attachments['#attached']['library']);
    $this->assertContains('config:' . DesignPalette::SETTINGS, $attachments['#cache']['tags']);
  }

  /**
   * The admin theme never gets the CSS, so it cannot break the fix-it form.
   */
  public function testNotAttachedOnAdminTheme(): void {
    $this->saveCss('a { color: red; }');

    $attachments = $this->attachmentsFor('gin');

    $this->assertArrayNotHasKey('#attached', $attachments);
    // Still varies on the setting, so the page updates once CSS is added.
    $this->assertContains('config:' . DesignPalette::SETTINGS, $attachments['#cache']['tags']);
  }

  /**
   * Nothing is attached while the setting is empty.
   */
  public function testNotAttachedWhenEmpty(): void {
    $this->assertArrayNotHasKey('#attached', $this->attachmentsFor('mukurtu_v4'));
  }

  /**
   * Users without the permission do not see the field.
   */
  public function testFieldHiddenWithoutPermission(): void {
    $this->setUpCurrentUser([], ['administer mukurtu design settings']);

    $build = \Drupal::formBuilder()->getForm(MukurtuDesignSettingsForm::class);

    $this->assertFalse($build['custom_css_section']['#access']);
  }

  /**
   * Users with the permission see the field, open when there is CSS.
   */
  public function testFieldShownWithPermission(): void {
    $this->setUpCurrentUser([], [MukurtuDesignSettingsForm::CUSTOM_CSS_PERMISSION]);

    $build = \Drupal::formBuilder()->getForm(MukurtuDesignSettingsForm::class);
    $this->assertTrue($build['custom_css_section']['#access']);
    $this->assertFalse($build['custom_css_section']['#open']);
    $this->assertSame('textarea', $build['custom_css_section'][CustomCss::KEY]['#type']);

    $this->saveCss('a { color: red; }');
    $build = \Drupal::formBuilder()->getForm(MukurtuDesignSettingsForm::class);
    $this->assertTrue($build['custom_css_section']['#open']);
    $this->assertSame('a { color: red; }', $build['custom_css_section'][CustomCss::KEY]['#default_value']);
  }

  /**
   * Submits the settings form with the given custom CSS.
   */
  protected function submitForm(string $css): FormState {
    $form_state = (new FormState())->setValues([
      'palette' => 'red-bone',
      'background' => ['text_treatment' => 'light', 'tint' => 1],
      'header' => ['text_treatment' => 'light', 'tint' => 1],
      CustomCss::KEY => $css,
      'op' => 'Save configuration',
    ]);
    \Drupal::formBuilder()->submitForm(MukurtuDesignSettingsForm::class, $form_state);
    return $form_state;
  }

  /**
   * Valid CSS saves; CSS that loads another site's file does not.
   */
  public function testFormValidatesAndSaves(): void {
    $this->setUpCurrentUser([], [
      'administer mukurtu design settings',
      MukurtuDesignSettingsForm::CUSTOM_CSS_PERMISSION,
    ]);

    $form_state = $this->submitForm('a { color: red; }');
    $this->assertSame([], $form_state->getErrors());
    $this->assertSame('a { color: red; }', $this->config(DesignPalette::SETTINGS)->get(CustomCss::KEY));

    $form_state = $this->submitForm('@import url(https://example.com/x.css);');
    $errors = $form_state->getErrors();
    $this->assertArrayHasKey(CustomCss::KEY, $errors);
    $this->assertStringContainsString('@import', (string) $errors[CustomCss::KEY]);
    $this->assertStringContainsString('other websites', (string) $errors[CustomCss::KEY]);
    $this->assertSame('a { color: red; }', $this->config(DesignPalette::SETTINGS)->get(CustomCss::KEY));
  }

  /**
   * Saving the form without the permission keeps the stored CSS.
   */
  public function testFormWithoutPermissionKeepsCss(): void {
    $this->saveCss('a { color: red; }');
    $this->setUpCurrentUser([], ['administer mukurtu design settings']);

    $form_state = $this->submitForm('');

    $this->assertSame([], $form_state->getErrors());
    $this->assertSame('a { color: red; }', $this->config(DesignPalette::SETTINGS)->get(CustomCss::KEY));
  }

  /**
   * The update hook adds the key once and never clears saved CSS.
   */
  public function testUpdateHookSeedsKey(): void {
    \Drupal::moduleHandler()->loadInclude('mukurtu_design', 'install');
    $config = \Drupal::configFactory()->getEditable(DesignPalette::SETTINGS);
    $config->clear(CustomCss::KEY)->save();

    mukurtu_design_update_40007();
    $this->assertSame('', $this->config(DesignPalette::SETTINGS)->get(CustomCss::KEY));

    $this->saveCss('a { color: red; }');
    mukurtu_design_update_40007();
    $this->assertSame('a { color: red; }', $this->config(DesignPalette::SETTINGS)->get(CustomCss::KEY));
  }

}
