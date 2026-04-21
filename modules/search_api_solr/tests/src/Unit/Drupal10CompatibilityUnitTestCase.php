<?php

namespace Drupal\Tests\search_api_solr\Unit;

use Drupal\Tests\UnitTestCase;

if (class_exists('\Prophecy\PhpUnit\ProphecyTrait')) {
  /**
   * Drupal 10 version.
   */
  abstract class Drupal10CompatibilityUnitTestCase extends UnitTestCase {
    use \Prophecy\PhpUnit\ProphecyTrait;

  }
}
else {
  /**
   * Drupal 9 version.
   */
  abstract class Drupal10CompatibilityUnitTestCase extends UnitTestCase {}
}
