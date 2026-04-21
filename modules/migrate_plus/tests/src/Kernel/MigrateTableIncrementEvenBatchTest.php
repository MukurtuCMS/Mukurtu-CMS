<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies all tests pass with batching enabled, even batches.
 */
#[Group('migrate_plus')]
#[RunTestsInSeparateProcesses]
final class MigrateTableIncrementEvenBatchTest extends MigrateTableIncrementTest {

  /**
   * The batch size to configure.
   *
   * @var int
   */
  protected static int $batchSize = 3;

}
