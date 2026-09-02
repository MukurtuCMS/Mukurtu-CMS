<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Guards against a bundle silently shipping with no translation support.
 *
 * This is the failure class behind the historical update_40005 bug: the 5
 * Layout Builder block types added by #1770 shipped with no
 * language.content_settings.* config at all, so they had no translation
 * support on a multilingual site and nobody noticed until it was reported.
 * This test globs every bundle-defining config file in the profile and
 * asserts a matching language.content_settings.* exists in
 * mukurtu_multilingual/config/install/, so a new bundle added without
 * translation support fails CI instead of shipping silently.
 *
 * A pure filesystem check - no Drupal bootstrap needed.
 */
#[Group('mukurtu_multilingual')]
class ConfigTranslationCoverageTest extends UnitTestCase {

  /**
   * Bundles that legitimately have no language.content_settings.* file.
   *
   * Keyed by "entity_type_id.bundle", value is the reason it's exempt.
   */
  private const EXEMPTIONS = [
    // Content translation is enabled imperatively in
    // _mukurtu_multilingual_apply_translation_overrides(), not via a shipped
    // config file, because the owning module's own config/install ships
    // language_alterable: false with no content_translation settings and
    // mukurtu_multilingual can't ship a colliding language.content_settings
    // file of its own (see mukurtu_multilingual.install).
    'node.landing_page' => 'enabled imperatively in mukurtu_multilingual.install, see _mukurtu_multilingual_apply_translation_overrides()',
  ];

  /**
   * Maps a bundle-defining config file's prefix to its entity type ID and
   * the config-name segment language.content_settings.* uses for it.
   */
  private const BUNDLE_CONFIG_PATTERNS = [
    'node.type.' => 'node',
    'block_content.type.' => 'block_content',
    'media.type.' => 'media',
    'paragraphs.paragraphs_type.' => 'paragraph',
    'taxonomy.vocabulary.' => 'taxonomy_term',
  ];

  /**
   * Every bundle-defining config file in the profile has a matching
   * language.content_settings.* file, unless explicitly exempted above.
   */
  public function testEveryBundleHasContentTranslationSettings(): void {
    $profileRoot = dirname(__DIR__, 5);
    $this->assertDirectoryExists($profileRoot . '/modules', 'Sanity check: resolved profile root is wrong.');

    $contentSettingsDir = $profileRoot . '/modules/mukurtu_multilingual/config/install';
    $existing = [];
    foreach (glob($contentSettingsDir . '/language.content_settings.*.yml') as $file) {
      $existing[] = basename($file, '.yml');
    }

    $configInstallDirs = array_merge(
      glob($profileRoot . '/config/install'),
      glob($profileRoot . '/modules/*/config/install'),
    );

    $gaps = [];
    foreach ($configInstallDirs as $dir) {
      foreach (scandir($dir) as $file) {
        foreach (self::BUNDLE_CONFIG_PATTERNS as $prefix => $entityTypeId) {
          if (str_starts_with($file, $prefix) && str_ends_with($file, '.yml')) {
            $bundle = substr($file, strlen($prefix), -4);
            $key = "$entityTypeId.$bundle";
            if (isset(self::EXEMPTIONS[$key])) {
              continue;
            }
            if (!in_array("language.content_settings.$entityTypeId.$bundle", $existing, TRUE)) {
              $gaps[$key] = "$dir/$file";
            }
          }
        }
      }
    }

    $message = "The following bundles have no language.content_settings.* file "
      . "(so translation is silently unavailable on a multilingual site). "
      . "Either ship modules/mukurtu_multilingual/config/install/language.content_settings.<type>.<bundle>.yml "
      . "(plus an update hook for existing sites), or add a documented exemption:\n"
      . implode("\n", array_map(fn ($key, $path) => "  $key ($path)", array_keys($gaps), $gaps));

    $this->assertSame([], $gaps, $message);
  }

}
