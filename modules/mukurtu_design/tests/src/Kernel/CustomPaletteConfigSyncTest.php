<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_design\DesignPalette;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the custom palette stylesheet follows its config.
 *
 * Before #2181 only the settings form wrote the file, so a config import,
 * `drush config:set` or recipe left the site serving the old colours.
 */
#[Group('mukurtu_design')]
class CustomPaletteConfigSyncTest extends KernelTestBase {

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
    $this->installConfig(['system', 'mukurtu_design']);
  }

  /**
   * Returns the generated stylesheet, or NULL if there is none.
   */
  protected function css(): ?string {
    $contents = @file_get_contents(DesignPalette::CUSTOM_PALETTE_CSS_URI);
    return $contents === FALSE ? NULL : $contents;
  }

  /**
   * Puts the site on the custom palette, then marks the file as stale.
   */
  protected function customPaletteWithStaleFile(): void {
    $this->config(DesignPalette::SETTINGS)
      ->set('palette', 'custom')
      ->set('colors', self::COLORS)
      ->save();
    file_put_contents(DesignPalette::CUSTOM_PALETTE_CSS_URI, '/* stale */');
  }

  /**
   * Switching to the custom palette writes the stylesheet.
   */
  public function testSwitchingToCustomWritesStylesheet(): void {
    $this->assertNull($this->css());

    $this->config(DesignPalette::SETTINGS)
      ->set('palette', 'custom')
      ->set('colors', self::COLORS)
      ->save();

    $this->assertStringContainsString('--brand-primary: #112233;', $this->css());
  }

  /**
   * Changing a colour in config rewrites the stylesheet.
   */
  public function testColorChangeRewritesStylesheet(): void {
    $this->customPaletteWithStaleFile();

    $this->config(DesignPalette::SETTINGS)->set('colors.brand_primary', '#abcdef')->save();

    $this->assertStringContainsString('--brand-primary: #abcdef;', $this->css());
  }

  /**
   * A config import rewrites the stylesheet.
   */
  public function testConfigImportRewritesStylesheet(): void {
    $this->customPaletteWithStaleFile();

    $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.sync'));
    $sync = $this->container->get('config.storage.sync');
    $settings = $sync->read(DesignPalette::SETTINGS);
    $settings['colors']['brand_secondary'] = '#fedcba';
    $sync->write(DesignPalette::SETTINGS, $settings);

    $this->configImporter()->import();

    $this->assertStringContainsString('--brand-secondary: #fedcba;', $this->css());
  }

  /**
   * Colour changes on a shipped palette write nothing.
   */
  public function testShippedPaletteWritesNothing(): void {
    $this->config(DesignPalette::SETTINGS)
      ->set('palette', 'red-bone')
      ->set('colors.brand_primary', '#abcdef')
      ->save();

    $this->assertNull($this->css());
  }

  /**
   * Saving unrelated settings leaves the stylesheet alone.
   */
  public function testUnrelatedChangeLeavesStylesheet(): void {
    $this->customPaletteWithStaleFile();

    $this->config(DesignPalette::SETTINGS)->set('header.text_treatment', 'dark')->save();

    $this->assertSame('/* stale */', $this->css());
  }

}
