<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_browse\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mukurtu_browse\ParamConverter\MukurtuMapNodesParamConverter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Routing\Route;

/**
 * @coversDefaultClass \Drupal\mukurtu_browse\ParamConverter\MukurtuMapNodesParamConverter
 * @group mukurtu_browse
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mukurtu_browse\ParamConverter\MukurtuMapNodesParamConverter::class)]
#[\PHPUnit\Framework\Attributes\Group('mukurtu_browse')]
class MukurtuMapNodesParamConverterTest extends TestCase {

  use ProphecyTrait;

  private MukurtuMapNodesParamConverter $converter;

  protected function setUp(): void {
    parent::setUp();

    $nodeStorage = $this->prophesize(EntityStorageInterface::class);

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('node')->willReturn($nodeStorage->reveal());

    $this->converter = new MukurtuMapNodesParamConverter($entityTypeManager->reveal());
  }

  // ---------------------------------------------------------------------------
  // convert()
  // ---------------------------------------------------------------------------

  /**
   * A single numeric ID is forwarded to loadMultiple as a one-element array.
   */
  public function testConvert_singleId(): void {
    $node = new \stdClass();

    $nodeStorage = $this->prophesize(EntityStorageInterface::class);
    $nodeStorage->loadMultiple(['42'])->shouldBeCalledOnce()->willReturn([42 => $node]);

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('node')->willReturn($nodeStorage->reveal());

    $converter = new MukurtuMapNodesParamConverter($entityTypeManager->reveal());
    $result = $converter->convert('42', [], 'nodes', []);
    $this->assertSame([42 => $node], $result);
  }

  /**
   * A comma-separated list is split and forwarded to loadMultiple.
   */
  public function testConvert_multipleIds(): void {
    $nodeStorage = $this->prophesize(EntityStorageInterface::class);
    $nodeStorage->loadMultiple(['1', '2', '3'])->shouldBeCalledOnce()->willReturn([]);

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('node')->willReturn($nodeStorage->reveal());

    $converter = new MukurtuMapNodesParamConverter($entityTypeManager->reveal());
    $converter->convert('1,2,3', [], 'nodes', []);
  }

  /**
   * Whatever loadMultiple returns is passed through unchanged.
   */
  public function testConvert_returnsStorageResult(): void {
    $nodes = [7 => new \stdClass(), 8 => new \stdClass()];

    $nodeStorage = $this->prophesize(EntityStorageInterface::class);
    $nodeStorage->loadMultiple(['7', '8'])->willReturn($nodes);

    $entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $entityTypeManager->getStorage('node')->willReturn($nodeStorage->reveal());

    $converter = new MukurtuMapNodesParamConverter($entityTypeManager->reveal());
    $result = $converter->convert('7,8', [], 'nodes', []);
    $this->assertSame($nodes, $result);
  }

  // ---------------------------------------------------------------------------
  // applies()
  // ---------------------------------------------------------------------------

  /**
   * Returns TRUE when the definition type is exactly 'nodes'.
   */
  public function testApplies_trueForNodesType(): void {
    $route = $this->prophesize(Route::class)->reveal();
    $this->assertTrue($this->converter->applies(['type' => 'nodes'], 'nodes', $route));
  }

  /**
   * Returns FALSE for a different type string.
   */
  public function testApplies_falseForOtherType(): void {
    $route = $this->prophesize(Route::class)->reveal();
    $this->assertFalse($this->converter->applies(['type' => 'node'], 'nodes', $route));
  }

  /**
   * Returns FALSE when the 'type' key is absent from the definition.
   */
  public function testApplies_falseWhenTypeKeyMissing(): void {
    $route = $this->prophesize(Route::class)->reveal();
    $this->assertFalse($this->converter->applies([], 'nodes', $route));
  }

  /**
   * Returns FALSE when the 'type' key is present but empty.
   */
  public function testApplies_falseForEmptyType(): void {
    $route = $this->prophesize(Route::class)->reveal();
    $this->assertFalse($this->converter->applies(['type' => ''], 'nodes', $route));
  }

}
