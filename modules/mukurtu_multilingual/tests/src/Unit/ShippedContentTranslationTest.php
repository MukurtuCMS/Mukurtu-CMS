<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Confirms every bundle mukurtu_multilingual ships has translation enabled.
 *
 * A bundle whose fields are marked translatable but whose
 * language.content_settings has content_translation disabled cannot be
 * translated at all, and gives no error saying so: the translate tab simply
 * never appears. ConfigTranslationCoverageTest found 12 bundles in that state,
 * the 7 media bundles among them, so media names could never be translated on
 * a multilingual site.
 *
 * The update hook that closed those gaps only ever converged existing sites.
 * Update hooks never run on a fresh install, and no hook_install() called that
 * logic either, so what protects a new site is these files shipping enabled.
 * That is what this asserts, across every bundle rather than one representative
 * case, since the gap was originally a whole category of bundle being missed.
 *
 * Replaces the kernel test that asserted the state *before* the hook ran, which
 * was the only half of it left once the hook was removed in 4.0.1.
 *
 * A pure filesystem and YAML check, so no Drupal bootstrap is needed.
 *
 * @see docs/content-language-policy.md
 */
#[Group('mukurtu_multilingual')]
class ShippedContentTranslationTest extends UnitTestCase {

  /**
   * Bundles whose absence would be a regression rather than a design change.
   *
   * The media bundles are named explicitly because they were the gap: all seven
   * shipped with translation disabled, and a glob alone would stop noticing if
   * the files were removed rather than changed.
   */
  private const REQUIRED_MEDIA_BUNDLES = [
    'audio',
    'document',
    'external_embed',
    'image',
    'remote_video',
    'soundcloud',
    'video',
  ];

  /**
   * Resolves the module's config/install directory.
   */
  private static function configInstallDir(): string {
    return dirname(__DIR__, 3) . '/config/install';
  }

  /**
   * Every shipped content language setting, keyed by "entity_type.bundle".
   */
  public static function contentSettingsProvider(): \Generator {
    $pattern = self::configInstallDir() . '/language.content_settings.*.yml';

    foreach (glob($pattern) ?: [] as $path) {
      $key = str_replace(['language.content_settings.', '.yml'], '', basename($path));
      yield $key => [$key, $path];
    }
  }

  /**
   * Guards the provider itself.
   *
   * Every case below is glob-driven, so a glob that matched nothing would leave
   * the suite green while checking no bundles at all.
   */
  public function testTheShippedSettingsAreFound(): void {
    $found = iterator_to_array(self::contentSettingsProvider());

    $this->assertGreaterThanOrEqual(
      50,
      count($found),
      'Expected the profile to ship content language settings for around 55 bundles; the glob has stopped matching.'
    );
  }

  #[DataProvider('contentSettingsProvider')]
  public function testTranslationIsEnabled(string $bundleKey, string $path): void {
    $settings = Yaml::parseFile($path);

    $this->assertTrue(
      $settings['third_party_settings']['content_translation']['enabled'] ?? FALSE,
      "$bundleKey ships with content translation disabled, so its translatable fields cannot actually be translated and no translate tab appears."
    );
  }

  /**
   * The media bundles specifically are all still covered.
   */
  public function testAllMediaBundlesAreCovered(): void {
    $dir = self::configInstallDir();

    foreach (self::REQUIRED_MEDIA_BUNDLES as $bundle) {
      $this->assertFileExists(
        "$dir/language.content_settings.media.$bundle.yml",
        "The media.$bundle content language setting is no longer shipped. All seven media bundles once shipped untranslatable."
      );
    }
  }

}
