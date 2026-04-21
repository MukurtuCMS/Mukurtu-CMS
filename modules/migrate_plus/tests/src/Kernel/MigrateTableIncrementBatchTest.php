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
final class MigrateTableIncrementBatchTest extends MigrateTableIncrementTest {

  /**
   * The batch size to configure.
   *
   * @var int
   */
  protected static int $batchSize = 2;

}
