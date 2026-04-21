<?php

namespace Drupal\Tests\facets\Kernel;

use Drupal\KernelTests\KernelTestBase;

if (class_exists('\Prophecy\PhpUnit\ProphecyTrait')) {
  /**
   * Drupal 10 version.
   */
  abstract class Drupal10CompatibilityKernelTestBase extends KernelTestBase {
    use \Prophecy\PhpUnit\ProphecyTrait;

  }
}
else {
  /**
   * Drupal 9 version.
   */
  abstract class Drupal10CompatibilityKernelTestBase extends KernelTestBase {}
}
