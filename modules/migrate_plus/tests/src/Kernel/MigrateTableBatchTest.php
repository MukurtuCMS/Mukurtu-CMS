<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies all tests pass with batching enabled, uneven batches.
 */
#[Group('migrate_plus')]
#[RunTestsInSeparateProcesses]
final class MigrateTableBatchTest extends MigrateTableTest {

  /**
   * The batch size to configure (a size of 1 disables batching).
   *
   * @var int
   */
  protected $batchSize = 2;

}
