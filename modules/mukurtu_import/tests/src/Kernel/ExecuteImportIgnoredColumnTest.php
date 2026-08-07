<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\Form\ExecuteImportForm;

/**
 * Tests that a mapping containing an explicitly-ignored column (target -1)
 * doesn't crash dependency detection when running an import.
 *
 * Regression test: a mapping entry's target can be the int -1
 * "Ignore - Do not import" sentinel rather than a field name -- either
 * from a user manually choosing "Ignore" in Customize Settings (which
 * saves the raw form value unfiltered), or from
 * ImportFileSummaryForm::resolveMappingForFile() auto-resolving an
 * unmatched CSV header to it. ExecuteImportForm::detectUpstreamDependencies()
 * and sortByDependencies() both did `explode('/', $mapping['target'], 2)`
 * assuming every target is a string, throwing "explode(): Argument #2
 * ($string) must be of type string, int given" and aborting the entire
 * "Run Import" submit before any migration could even start.
 */
class ExecuteImportIgnoredColumnTest extends MukurtuImportTestBase {

  /**
   * Test that detectUpstreamDependencies() tolerates a -1 target instead
   * of throwing a TypeError.
   */
  public function testIgnoredColumnDoesNotCrashDependencyDetection() {
    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('user');
    $strategy->setTargetBundle('user');
    $strategy->setMapping([
      ['source' => 'Name', 'target' => 'name'],
      // An explicitly-ignored column, as either a manual "Ignore" choice
      // or resolveMappingForFile()'s auto-resolution would produce.
      ['source' => 'Notes', 'target' => -1],
    ]);

    $form = ExecuteImportForm::create($this->container);
    $reflection = new \ReflectionClass($form);
    $method = $reflection->getMethod('detectUpstreamDependencies');
    $method->setAccessible(TRUE);

    $entity_type_index = ['user' => [1 => $strategy]];
    $upstream_lookup_columns = [];

    // No TypeError should be thrown. $upstream_lookup_columns is an
    // out-param, so invoke via invokeArgs() with an explicit reference.
    $method->invokeArgs($form, [$strategy, $entity_type_index, &$upstream_lookup_columns]);
    $this->assertTrue(TRUE);

    $sortMethod = $reflection->getMethod('sortByDependencies');
    $sortMethod->setAccessible(TRUE);
    $result = $sortMethod->invoke($form, [1], [1 => $strategy], $entity_type_index);
    $this->assertEquals([1], $result);
  }

}
