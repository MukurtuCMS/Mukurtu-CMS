<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_migrate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\Event\MigrateMapSaveEvent;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\mukurtu_migrate\Batch\MukurtuMigrateImportBatch;

/**
 * Tests the row-status bucketing done by
 * MukurtuMigrateImportBatch::onMapSave().
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/154
 */
class MukurtuMigrateImportBatchOnMapSaveTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'migrate_drupal_ui',
    'mukurtu_migrate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->resetCounters();
  }

  /**
   * Resets the batch's protected static row counters to 0 via reflection.
   */
  protected function resetCounters(): void {
    foreach (['rowCreatedOrUpdatedCount', 'rowFailedCount', 'rowIgnoredCount'] as $property) {
      $this->getCounterProperty($property)->setValue(NULL, 0);
    }
  }

  /**
   * Gets an accessible ReflectionProperty for one of the static counters.
   */
  protected function getCounterProperty(string $property): \ReflectionProperty {
    $reflection = new \ReflectionClass(MukurtuMigrateImportBatch::class);
    $prop = $reflection->getProperty($property);
    $prop->setAccessible(TRUE);
    return $prop;
  }

  /**
   * Reads a static counter's current value via reflection.
   */
  protected function getCounter(string $property): int {
    return $this->getCounterProperty($property)->getValue();
  }

  /**
   * Dispatches onMapSave() with a hand-built event carrying the given
   * source_row_status.
   */
  protected function dispatchMapSave(int $status): void {
    $map = $this->createMock(MigrateIdMapInterface::class);
    $event = new MigrateMapSaveEvent($map, ['source_row_status' => $status]);
    MukurtuMigrateImportBatch::onMapSave($event);
  }

  /**
   * STATUS_FAILED increments only the failed counter.
   */
  public function testStatusFailedIncrementsFailedCounterOnly(): void {
    $this->dispatchMapSave(MigrateIdMapInterface::STATUS_FAILED);

    $this->assertSame(1, $this->getCounter('rowFailedCount'));
    $this->assertSame(0, $this->getCounter('rowCreatedOrUpdatedCount'));
    $this->assertSame(0, $this->getCounter('rowIgnoredCount'));
  }

  /**
   * STATUS_IMPORTED increments only the created/updated counter.
   */
  public function testStatusImportedIncrementsCreatedOrUpdatedCounterOnly(): void {
    $this->dispatchMapSave(MigrateIdMapInterface::STATUS_IMPORTED);

    $this->assertSame(1, $this->getCounter('rowCreatedOrUpdatedCount'));
    $this->assertSame(0, $this->getCounter('rowFailedCount'));
    $this->assertSame(0, $this->getCounter('rowIgnoredCount'));
  }

  /**
   * STATUS_NEEDS_UPDATE also increments only the created/updated counter.
   */
  public function testStatusNeedsUpdateIncrementsCreatedOrUpdatedCounterOnly(): void {
    $this->dispatchMapSave(MigrateIdMapInterface::STATUS_NEEDS_UPDATE);

    $this->assertSame(1, $this->getCounter('rowCreatedOrUpdatedCount'));
    $this->assertSame(0, $this->getCounter('rowFailedCount'));
    $this->assertSame(0, $this->getCounter('rowIgnoredCount'));
  }

  /**
   * STATUS_IGNORED increments only the ignored counter.
   */
  public function testStatusIgnoredIncrementsIgnoredCounterOnly(): void {
    $this->dispatchMapSave(MigrateIdMapInterface::STATUS_IGNORED);

    $this->assertSame(1, $this->getCounter('rowIgnoredCount'));
    $this->assertSame(0, $this->getCounter('rowFailedCount'));
    $this->assertSame(0, $this->getCounter('rowCreatedOrUpdatedCount'));
  }

  /**
   * A mixture of statuses buckets independently and additively across
   * multiple onMapSave() calls.
   */
  public function testMixedStatusesBucketIndependently(): void {
    $this->dispatchMapSave(MigrateIdMapInterface::STATUS_IMPORTED);
    $this->dispatchMapSave(MigrateIdMapInterface::STATUS_NEEDS_UPDATE);
    $this->dispatchMapSave(MigrateIdMapInterface::STATUS_FAILED);
    $this->dispatchMapSave(MigrateIdMapInterface::STATUS_FAILED);
    $this->dispatchMapSave(MigrateIdMapInterface::STATUS_IGNORED);

    $this->assertSame(2, $this->getCounter('rowCreatedOrUpdatedCount'));
    $this->assertSame(2, $this->getCounter('rowFailedCount'));
    $this->assertSame(1, $this->getCounter('rowIgnoredCount'));
  }

}
