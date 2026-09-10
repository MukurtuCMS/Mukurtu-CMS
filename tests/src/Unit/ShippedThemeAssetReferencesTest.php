<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Checks that the theme's compiled CSS points at assets that exist.
 *
 * The compiled style.css is committed, so a bad url() ships. This started with
 * a missing slash in components/00-base/typography/_fonts.scss:
 *
 *   url('..fonts/BCSans-Regular.woff')
 *
 * which resolved one directory too high and 404d. Nobody noticed because it
 * was the woff fallback for a single weight and every current browser takes
 * the woff2 listed before it, so the font still rendered.
 *
 * A malformed path is invisible in review and invisible in use until the one
 * browser that needs the fallback asks for it. It is cheap to check instead.
 *
 * A pure filesystem check, so no Drupal bootstrap is needed.
 */
#[Group('mukurtu')]
class ShippedThemeAssetReferencesTest extends UnitTestCase {

  /**
   * Resolves the profile root from this file's location.
   */
  private static function profileRoot(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Every committed CSS file in the theme.
   */
  public static function cssFileProvider(): \Generator {
    $root = self::profileRoot();
    $dir = $root . '/themes/mukurtu_v4/css';
    if (!is_dir($dir)) {
      return;
    }

    // Recurse: PHP's glob() has no "**", so a pattern would silently miss the
    // nested palette stylesheets and this would check far less than it looks.
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
    );

    $files = [];
    foreach ($iterator as $file) {
      if ($file->getExtension() === 'css') {
        $files[] = $file->getPathname();
      }
    }
    sort($files);

    foreach ($files as $path) {
      yield str_replace("$root/", '', $path) => [$path];
    }
  }

  /**
   * Guards the provider: a glob that matched nothing would prove nothing.
   */
  public function testCssFilesAreFound(): void {
    $found = iterator_to_array(self::cssFileProvider());

    $this->assertNotEmpty($found, 'No compiled CSS was found; this test is checking nothing.');
    $this->assertArrayHasKey('themes/mukurtu_v4/css/style.css', $found, 'The main stylesheet is missing from the scan.');
  }

  /**
   * Relative url() references resolve to a file that exists.
   *
   * Only relative paths are checked. Absolute URLs, protocol relative URLs and
   * data: URIs are somebody else's problem, and CSS variables inside url()
   * cannot be resolved statically.
   */
  #[DataProvider('cssFileProvider')]
  public function testRelativeUrlReferencesResolve(string $path): void {
    $css = file_get_contents($path);
    $this->assertIsString($css);

    preg_match_all('#url\(\s*["\']?([^"\')]+)["\']?\s*\)#', $css, $matches);
    $checked = 0;

    foreach ($matches[1] as $reference) {
      $reference = trim($reference);

      // Skip anything not a plain relative file path.
      if ($reference === ''
        || str_starts_with($reference, 'data:')
        || str_starts_with($reference, 'http:')
        || str_starts_with($reference, 'https:')
        || str_starts_with($reference, '//')
        || str_starts_with($reference, '/')
        || str_starts_with($reference, '#')
        || str_contains($reference, 'var(')) {
        continue;
      }

      // Drop any ?query or #fragment before resolving.
      $file = preg_replace('/[?#].*$/', '', $reference);
      $resolved = realpath(dirname($path) . '/' . $file);
      $checked++;

      $this->assertNotFalse(
        $resolved,
        sprintf(
          '%s references "%s", which does not resolve to a file. A missing slash such as "..fonts/" instead of "../fonts/" looks right and 404s.',
          basename($path),
          $reference
        )
      );
    }

    // Not every stylesheet has url() references; that is fine.
    $this->addToAssertionCount($checked ?: 1);
  }

  /**
   * The SCSS sources carry no obviously malformed relative path either.
   *
   * Catching it in the source as well as the build output means a wrong path
   * is reported even if nobody has recompiled yet.
   */
  public function testScssSourcesHaveNoMalformedRelativePaths(): void {
    $root = self::profileRoot();
    $found = [];

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($root . '/themes/mukurtu_v4/components', \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
      if ($file->getExtension() !== 'scss') {
        continue;
      }
      $contents = file_get_contents($file->getPathname());
      // ".." immediately followed by something other than "/" inside a url().
      if (preg_match_all('#url\(\s*["\']?\.\.[^/"\')]#', $contents, $hits)) {
        $found[] = str_replace("$root/", '', $file->getPathname());
      }
    }

    $this->assertSame([], $found, 'These SCSS files contain a relative url() missing its slash after "..".');
  }

}
