<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_browse\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mukurtu_browse\ParamConverter\MukurtuMapNodesParamConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;

/**
 * @coversDefaultClass \Drupal\mukurtu_browse\ParamConverter\MukurtuMapNodesParamConverter
 * @group mukurtu_browse
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mukurtu_browse\ParamConverter\MukurtuMapNodesParamConverter::class)]
#[\PHPUnit\Framework\Attributes\Group('mukurtu_browse')]
class MukurtuMapNodesParamConverterTest extends TestCase {

  private EntityTypeManagerInterface $entityTypeManager;
  private EntityStorageInterface $nodeStorage;
  private MukurtuMapNodesParamConverter $converter;

  protected function setUp(): void {
    parent::setUp();

    $this->nodeStorage = $this->createMock(EntityStorageInterface::class);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeManager
      ->method('getStorage')
      ->with('node')
      ->willReturn($this->nodeStorage);

    $this->converter = new MukurtuMapNodesParamConverter($this->entityTypeManager);
  }

  // ---------------------------------------------------------------------------
  // convert()
  // ---------------------------------------------------------------------------

  /**
   * A single numeric ID is forwarded to loadMultiple as a one-element array.
   */
  public function testConvert_singleId(): void {
    $node = new \stdClass();
    $this->nodeStorage
      ->expects($this->once())
      ->method('loadMultiple')
      ->with(['42'])
      ->willReturn([42 => $node]);

    $result = $this->converter->convert('42', [], 'nodes', []);
    $this->assertSame([42 => $node], $result);
  }

  /**
   * A comma-separated list is split and forwarded to loadMultiple.
   */
  public function testConvert_multipleIds(): void {
    $this->nodeStorage
      ->expects($this->once())
      ->method('loadMultiple')
      ->with(['1', '2', '3'])
      ->willReturn([]);

    $this->converter->convert('1,2,3', [], 'nodes', []);
  }

  /**
   * Whatever loadMultiple returns is passed through unchanged.
   */
  public function testConvert_returnsStorageResult(): void {
    $nodes = [7 => new \stdClass(), 8 => new \stdClass()];
    $this->nodeStorage->method('loadMultiple')->willReturn($nodes);

    $result = $this->converter->convert('7,8', [], 'nodes', []);
    $this->assertSame($nodes, $result);
  }

  // ---------------------------------------------------------------------------
  // applies()
  // ---------------------------------------------------------------------------

  /**
   * Returns TRUE when the definition type is exactly 'nodes'.
   */
  public function testApplies_trueForNodesType(): void {
    $route = $this->createMock(Route::class);
    $this->assertTrue($this->converter->applies(['type' => 'nodes'], 'nodes', $route));
  }

  /**
   * Returns FALSE for a different type string.
   */
  public function testApplies_falseForOtherType(): void {
    $route = $this->createMock(Route::class);
    $this->assertFalse($this->converter->applies(['type' => 'node'], 'nodes', $route));
  }

  /**
   * Returns FALSE when the 'type' key is absent from the definition.
   */
  public function testApplies_falseWhenTypeKeyMissing(): void {
    $route = $this->createMock(Route::class);
    $this->assertFalse($this->converter->applies([], 'nodes', $route));
  }

  /**
   * Returns FALSE when the 'type' key is present but empty.
   */
  public function testApplies_falseForEmptyType(): void {
    $route = $this->createMock(Route::class);
    $this->assertFalse($this->converter->applies(['type' => ''], 'nodes', $route));
  }

}
