<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_community_records\Kernel;

/**
 * Tests CommunityRecordNodeViewBuilder::sortRecords().
 *
 * Covers the community-ordering regression where the configured
 * community_record_weights array was consumed in its raw config storage
 * order instead of being sorted by weight value.
 *
 * @group mukurtu_community_records
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_community_records')]
class CommunityRecordOrderingTest extends CommunityRecordTestBase {

  /**
   * Saves community_record_weights and invokes the real sortRecords().
   *
   * $records must be keyed by their own id(), matching how
   * CommunityRecordNodeViewBuilder::getCommunityRecords() calls it.
   *
   * @return array
   *   The sorted list of stub records, in display order.
   */
  protected function sortRecords(array $weights, array $records): array {
    \Drupal::configFactory()->getEditable('mukurtu_community_records.settings')
      ->set('community_record_weights', $weights)
      ->save();

    $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $method = new \ReflectionMethod($viewBuilder, 'sortRecords');
    $method->setAccessible(TRUE);
    return $method->invoke($viewBuilder, $records);
  }

  /**
   * When the weights array's config storage order does not match its
   * weight values, the lower-weight community's record must still sort
   * first. This reproduces the reported bug: the configured order looked
   * correct on the settings page but wasn't applied to the record tabs.
   */
  public function testSortRecords_outOfOrderStorage(): void {
    // Community 31 comes first in the array but has the higher weight.
    $weights = [31 => 5, 30 => 1];
    $records = [
      1 => new StubCommunityRecord(1, [31], 100),
      2 => new StubCommunityRecord(2, [30], 200),
    ];

    $result = $this->sortRecords($weights, $records);

    $this->assertEquals(2, $result[0]->id());
    $this->assertEquals(1, $result[1]->id());
  }

  /**
   * When the weights array is already in ascending order, it continues to
   * sort correctly.
   */
  public function testSortRecords_alreadyInOrderStorage(): void {
    $weights = [30 => 1, 31 => 5];
    $records = [
      1 => new StubCommunityRecord(1, [31], 100),
      2 => new StubCommunityRecord(2, [30], 200),
    ];

    $result = $this->sortRecords($weights, $records);

    $this->assertEquals(2, $result[0]->id());
    $this->assertEquals(1, $result[1]->id());
  }

  /**
   * An empty/unset weights config falls through to a date sort without
   * error.
   */
  public function testSortRecords_noWeights(): void {
    $records = [
      1 => new StubCommunityRecord(1, [31], 200),
      2 => new StubCommunityRecord(2, [30], 100),
    ];

    $result = $this->sortRecords([], $records);

    $this->assertEquals(2, $result[0]->id());
    $this->assertEquals(1, $result[1]->id());
  }

}

/**
 * Minimal stand-in for a node, exposing only what sortRecords() calls.
 */
class StubCommunityRecord {

  public function __construct(
    protected int $recordId,
    protected array $communityIds,
    protected int $created,
  ) {}

  public function id(): int {
    return $this->recordId;
  }

  public function get(string $fieldName): object {
    $communityIds = $this->communityIds;
    return new class($communityIds) {
      public function __construct(protected array $communityIds) {}

      public function getValue(): array {
        return array_map(fn ($id) => ['target_id' => $id], $this->communityIds);
      }

    };
  }

  public function getCreatedTime(): int {
    return $this->created;
  }

}
