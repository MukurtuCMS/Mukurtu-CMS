<?php

namespace Drupal\Tests\twig_tweak\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Assert as PHPUnitAssert;

/**
 * A base class for Twig Tweak kernel tests.
 */
abstract class AbstractTestCase extends KernelTestBase {

  /**
   * Asserts cache metadata.
   */
  protected static function assertCache(array $expected_cache, array $actual_cache): void {
    self::sortCache($expected_cache);
    self::sortCache($actual_cache);
    PHPUnitAssert::assertSame($expected_cache, $actual_cache);
  }

  /**
   * Asserts render array.
   */
  protected static function assertRenderArray(array $expected_build, array $actual_build): void {
    self::sortCache($expected_build['#cache']);
    self::sortCache($actual_build['#cache']);
    PHPUnitAssert::assertSame($expected_build, $actual_build);
  }

  /**
   * Sort cache metadata.
   *
   * @see https://www.drupal.org/node/3230171
   */
  private static function sortCache(array &$cache): void {
    if (\array_key_exists('tags', $cache)) {
      sort($cache['tags']);
    }
    if (\array_key_exists('contexts', $cache)) {
      sort($cache['contexts']);
    }
  }

}
