<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Unit\process;

use Drupal\migrate_plus\Plugin\migrate\process\SingleValue;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests single_value process plugin.
 */
#[CoversClass(SingleValue::class)]
#[Group('migrate_plus')]
final class SingleValueTest extends MigrateProcessTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->plugin = new SingleValue([], 'single_value', []);
    parent::setUp();
  }

  /**
   * Test input treated as single value output.
   */
  public function testTreatAsSingle(): void {
    $value = ['v1', 'v2', 'v3'];
    $output = $this->plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationProperty');
    $this->assertSame($output, $value);
    $this->assertFalse($this->plugin->multiple());
  }

}
