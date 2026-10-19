<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Checks that every place the Mukurtu version is recorded agrees.
 *
 * The scripts/set-version.sh script writes it to VERSION.md (the dashboard), to
 * every .info.yml that declares one (/admin/modules, /admin/appearance), and to
 * the build tooling's package.json and package-lock.json files. A hand edit to
 * any one of them, or a file the script does not know about, shows up here.
 */
#[CoversNothing]
#[Group('mukurtu')]
class VersionConsistencyTest extends TestCase {

  /**
   * The profile root: tests/src/Unit is three levels below it.
   */
  private function root(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * The version in VERSION.md, which every other file must match.
   */
  private function expectedVersion(): string {
    return trim((string) file_get_contents($this->root() . '/VERSION.md'));
  }

  /**
   * Tests that VERSION.md holds a release version or a dev placeholder.
   */
  public function testVersionFileFormat(): void {
    // The same pattern set-version.sh accepts.
    $this->assertMatchesRegularExpression(
      '/^(\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?|\d+\.x-dev)$/',
      $this->expectedVersion(),
      'VERSION.md must hold a version such as 4.0.6, 4.0.6-beta1 or 4.x-dev. Set it with scripts/set-version.sh.'
    );
  }

  /**
   * Tests that every versioned .info.yml matches VERSION.md.
   */
  public function testInfoFilesMatchVersionFile(): void {
    $expected = $this->expectedVersion();
    $checked = 0;
    $mismatches = [];

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveCallbackFilterIterator(
        new \RecursiveDirectoryIterator($this->root(), \FilesystemIterator::SKIP_DOTS),
        // Test fixture modules carry their own unrelated versions, and the
        // dependency directories are not part of the distribution.
        static fn (\SplFileInfo $file): bool => !$file->isDir()
          || !in_array($file->getFilename(), ['tests', 'node_modules', 'vendor', 'web'], TRUE),
      )
    );
    foreach ($iterator as $file) {
      if (!str_ends_with($file->getFilename(), '.info.yml')) {
        continue;
      }
      if (!preg_match('/^version: *[\'"]?([^\'"\n]+)[\'"]?\s*$/m', (string) file_get_contents($file->getPathname()), $match)) {
        continue;
      }
      $checked++;
      if ($match[1] !== $expected) {
        $mismatches[] = substr($file->getPathname(), strlen($this->root()) + 1) . ': ' . $match[1];
      }
    }

    // The profile, 26 modules and the theme declare a version. A much smaller
    // count means the walk missed them, and an empty mismatch list would prove
    // nothing.
    $this->assertGreaterThanOrEqual(25, $checked, 'Too few versioned .info.yml files were found.');
    $this->assertSame([], $mismatches, "These .info.yml files do not match VERSION.md ($expected). Set the version with scripts/set-version.sh.");
  }

  /**
   * Tests that the build tooling's package files match VERSION.md.
   */
  public function testPackageFilesMatchVersionFile(): void {
    $expected = $this->expectedVersion();
    $mismatches = [];

    foreach (['package.json', 'themes/mukurtu_v4/package.json'] as $path) {
      $data = json_decode((string) file_get_contents($this->root() . '/' . $path), TRUE);
      if (($data['version'] ?? NULL) !== $expected) {
        $mismatches[] = "$path: " . ($data['version'] ?? 'none');
      }
    }

    // A lock file records the root package's version twice.
    foreach (['package-lock.json', 'themes/mukurtu_v4/package-lock.json'] as $path) {
      if (!file_exists($this->root() . '/' . $path)) {
        continue;
      }
      $data = json_decode((string) file_get_contents($this->root() . '/' . $path), TRUE);
      $recorded = [
        'version' => $data['version'] ?? NULL,
        'packages[""].version' => $data['packages']['']['version'] ?? NULL,
      ];
      foreach ($recorded as $key => $version) {
        if ($version !== $expected) {
          $mismatches[] = "$path $key: " . ($version ?? 'none');
        }
      }
    }

    $this->assertSame([], $mismatches, "These package files do not match VERSION.md ($expected). Set the version with scripts/set-version.sh.");
  }

}
