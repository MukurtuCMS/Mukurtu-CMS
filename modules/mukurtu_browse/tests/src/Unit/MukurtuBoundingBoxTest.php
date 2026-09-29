<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_browse\Unit;

use Drupal\mukurtu_browse\Plugin\views\argument\MukurtuBoundingBox;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\mukurtu_browse\Plugin\views\argument\MukurtuBoundingBox
 * @group mukurtu_browse
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\mukurtu_browse\Plugin\views\argument\MukurtuBoundingBox::class)]
#[\PHPUnit\Framework\Attributes\Group('mukurtu_browse')]
class MukurtuBoundingBoxTest extends TestCase {

  /**
   * Create a MukurtuBoundingBox instance with the Drupal Views constructor
   * bypassed, and the argument string preset.
   */
  private function createPlugin(string $argument): MukurtuBoundingBox {
    /** @var MukurtuBoundingBox $plugin */
    $plugin = $this->getMockBuilder(MukurtuBoundingBox::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();
    $plugin->argument = $argument;
    return $plugin;
  }

  /**
   * Call the protected parseBoundingBox() method via reflection.
   */
  private function parseBoundingBox(MukurtuBoundingBox $plugin): array {
    $ref = new \ReflectionMethod($plugin, 'parseBoundingBox');
    return $ref->invoke($plugin);
  }

  /**
   * Set the protected $query property via reflection.
   */
  private function setQuery(MukurtuBoundingBox $plugin, object $query): void {
    $ref = new \ReflectionProperty($plugin, 'query');
    $ref->setValue($plugin, $query);
  }

  // ---------------------------------------------------------------------------
  // parseBoundingBox()
  // ---------------------------------------------------------------------------

  /**
   * A valid "left,bottom,right,top" string is parsed into the four named keys.
   */
  public function testParseBoundingBox_validInput(): void {
    $plugin = $this->createPlugin('-122.5,47.5,-122.0,48.0');
    $bbox = $this->parseBoundingBox($plugin);

    $this->assertSame(-122.5, $bbox['left']);
    $this->assertSame(47.5, $bbox['bottom']);
    $this->assertSame(-122.0, $bbox['right']);
    $this->assertSame(48.0, $bbox['top']);
  }

  /**
   * Integer strings are cast to floats.
   */
  public function testParseBoundingBox_integersAreCastToFloat(): void {
    $plugin = $this->createPlugin('10,20,30,40');
    $bbox = $this->parseBoundingBox($plugin);

    $this->assertIsFloat($bbox['left']);
    $this->assertIsFloat($bbox['bottom']);
    $this->assertIsFloat($bbox['right']);
    $this->assertIsFloat($bbox['top']);
  }

  /**
   * Fewer than four coordinates returns an empty array.
   */
  public function testParseBoundingBox_tooFewCoordinates(): void {
    $plugin = $this->createPlugin('1.0,2.0,3.0');
    $this->assertEmpty($this->parseBoundingBox($plugin));
  }

  /**
   * More than four coordinates returns an empty array.
   */
  public function testParseBoundingBox_tooManyCoordinates(): void {
    $plugin = $this->createPlugin('1.0,2.0,3.0,4.0,5.0');
    $this->assertEmpty($this->parseBoundingBox($plugin));
  }

  /**
   * An empty argument string returns an empty array.
   */
  public function testParseBoundingBox_emptyArgument(): void {
    $plugin = $this->createPlugin('');
    $this->assertEmpty($this->parseBoundingBox($plugin));
  }

  /**
   * Non-numeric strings are cast to 0.0 but the array is still returned
   * (the method only checks count, not value validity).
   */
  public function testParseBoundingBox_nonNumericStringsCastToZero(): void {
    $plugin = $this->createPlugin('a,b,c,d');
    $bbox = $this->parseBoundingBox($plugin);

    $this->assertCount(4, $bbox);
    $this->assertSame(0.0, $bbox['left']);
    $this->assertSame(0.0, $bbox['bottom']);
  }

  // ---------------------------------------------------------------------------
  // query()
  // ---------------------------------------------------------------------------

  /**
   * A valid bounding box triggers exactly four addCondition() calls.
   */
  public function testQuery_validBbox_addsFourConditions(): void {
    $plugin = $this->createPlugin('-122.5,47.5,-122.0,48.0');

    $mockQuery = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['addCondition'])
      ->getMock();
    $mockQuery->expects($this->exactly(4))->method('addCondition');

    $this->setQuery($plugin, $mockQuery);
    $plugin->query();
  }

  /**
   * An invalid bounding box triggers no addCondition() calls.
   */
  public function testQuery_invalidBbox_addsNoConditions(): void {
    $plugin = $this->createPlugin('not,a,valid');

    $mockQuery = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['addCondition'])
      ->getMock();
    $mockQuery->expects($this->never())->method('addCondition');

    $this->setQuery($plugin, $mockQuery);
    $plugin->query();
  }

  /**
   * The correct field names and comparison operators are passed to the query.
   */
  public function testQuery_correctConditions(): void {
    $plugin = $this->createPlugin('-122.5,47.5,-122.0,48.0');

    $calls = [];
    $mockQuery = $this->getMockBuilder(\stdClass::class)
      ->addMethods(['addCondition'])
      ->getMock();
    $mockQuery->method('addCondition')
      ->willReturnCallback(function (string $field, float $value, string $op) use (&$calls): void {
        $calls[] = [$field, $value, $op];
      });

    $this->setQuery($plugin, $mockQuery);
    $plugin->query();

    $this->assertContains(['centroid_lat', 47.5, '>='], $calls);
    $this->assertContains(['centroid_lat', 48.0, '<='], $calls);
    $this->assertContains(['centroid_lon', -122.5, '>='], $calls);
    $this->assertContains(['centroid_lon', -122.0, '<='], $calls);
  }

}
