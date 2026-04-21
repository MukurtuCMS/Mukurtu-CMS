<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Unit\process;

use Drupal\migrate_plus\Plugin\migrate\process\MultipleValues;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests multiple_values process plugin.
 */
#[CoversClass(MultipleValues::class)]
#[Group('migrate_plus')]
final class MultipleValuesTest extends MigrateProcessTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->plugin = new MultipleValues([], 'multiple_values', []);
    parent::setUp();
  }

  /**
   * Test input treated as multiple value output.
   */
  public function testTreatAsMultiple(): void {
    $value = ['v1', 'v2', 'v3'];
    $output = $this->plugin->transform($value, $this->migrateExecutable, $this->row, 'destinationProperty');
    $this->assertSame($output, $value);
    $this->assertTrue($this->plugin->multiple());
  }

}
