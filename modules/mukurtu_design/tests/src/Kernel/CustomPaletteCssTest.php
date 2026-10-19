<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_design\DesignPalette;
use Drupal\mukurtu_design\PaletteContrastAnalyzer;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the custom palette stylesheet and mukurtu_design_update_40006().
 *
 * The custom library loads only the generated file on top of style.css, so
 * any theme property it does not set falls back to the compiled-in palette's
 * value. See issue #2179.
 */
#[Group('mukurtu_design')]
class CustomPaletteCssTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'mukurtu_design'];

  /**
   * Six distinct brand colours.
   */
  protected const COLORS = [
    'brand_primary' => '#112233',
    'brand_primary_dark' => '#223344',
    'brand_primary_accent' => '#334455',
    'brand_secondary' => '#445566',
    'brand_secondary_dark' => '#556677',
    'brand_secondary_accent' => '#667788',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['mukurtu_design']);
    require_once $this->root . '/' . \Drupal::service('extension.list.module')->getPath('mukurtu_design') . '/mukurtu_design.install';
  }

  /**
   * Returns the DesignPalette service object.
   */
  protected function palette(): DesignPalette {
    return \Drupal::classResolver(DesignPalette::class);
  }

  /**
   * Returns the generated stylesheet, or NULL if there is none.
   */
  protected function css(): ?string {
    $contents = @file_get_contents(DesignPalette::CUSTOM_PALETTE_CSS_URI);
    return $contents === FALSE ? NULL : $contents;
  }

  /**
   * The stylesheet sets the brand colours and the derived properties.
   */
  public function testGeneratesBrandAndDerivedProperties(): void {
    $this->palette()->generateCustomCss(self::COLORS);
    $css = $this->css();

    foreach (DesignPalette::CSS_VAR_MAPPING as $key => $property) {
      $this->assertStringContainsString("$property: " . self::COLORS[$key] . ';', $css);
    }
    $this->assertStringContainsString('--mukurtu-breadcrumb-gradient-color: var(--brand-secondary-dark);', $css);
    $this->assertStringContainsString('--mukurtu-panel-pane-content-background-color: #eeecec;', $css);
  }

  /**
   * The derived properties are set even with no brand colours.
   */
  public function testDerivedPropertiesWithoutColors(): void {
    $this->palette()->generateCustomCss([]);
    $css = $this->css();

    $this->assertStringContainsString('No custom colors defined', $css);
    foreach (DesignPalette::CUSTOM_DERIVED_VARS as $property => $value) {
      $this->assertStringContainsString("$property: $value;", $css);
    }
  }

  /**
   * The contrast analyzer measures against the panel colour the file sets.
   */
  public function testAnalyzerUsesDerivedValues(): void {
    $analyzer = new PaletteContrastAnalyzer(\Drupal::service('extension.list.theme'));
    $method = new \ReflectionMethod($analyzer, 'tokenValues');

    // A stylesheet whose compiled-in palette sets a different panel colour.
    $css = ':root { --mukurtu-panel-pane-content-background-color: #f1efe9; }';
    [$tokens] = $method->invoke($analyzer, $css, self::COLORS);

    $this->assertSame('#eeecec', $tokens['--mukurtu-panel-pane-content-background-color']);
  }

  /**
   * The update rewrites a custom palette stylesheet written before #2179.
   */
  public function testUpdateRegeneratesStaleStylesheet(): void {
    $this->config(DesignPalette::SETTINGS)
      ->set('palette', 'custom')
      ->set('colors', self::COLORS)
      ->save();
    // Create the directory, then overwrite the file with what the generator
    // wrote before the derived properties existed.
    $this->palette()->generateCustomCss(self::COLORS);
    file_put_contents(DesignPalette::CUSTOM_PALETTE_CSS_URI, ":root {\n  --brand-primary: #112233;\n}\n");
    $this->assertStringNotContainsString('--mukurtu-breadcrumb-gradient-color', $this->css());

    $message = mukurtu_design_update_40006();

    $this->assertStringContainsString('Regenerated', $message);
    $this->assertStringContainsString('--mukurtu-breadcrumb-gradient-color', $this->css());
    $this->assertStringContainsString('--brand-secondary-dark: #556677;', $this->css());
  }

  /**
   * The update leaves sites on a shipped palette alone.
   */
  public function testUpdateSkipsShippedPalette(): void {
    $this->config(DesignPalette::SETTINGS)->set('palette', 'red-bone')->save();
    $this->assertNull($this->css());

    $message = mukurtu_design_update_40006();

    $this->assertStringContainsString('nothing to regenerate', $message);
    $this->assertNull($this->css(), 'No stylesheet is written for a shipped palette.');
  }

}
