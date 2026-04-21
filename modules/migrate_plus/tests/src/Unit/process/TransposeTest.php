<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Unit\process;

use Drupal\migrate_plus\Plugin\migrate\process\Transpose;
use Drupal\Tests\migrate\Unit\process\MigrateProcessTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the transpose process plugin.
 */
#[CoversClass(Transpose::class)]
#[Group('migrate_plus')]
final class TransposeTest extends MigrateProcessTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->plugin = new Transpose([], 'array_pop', []);
    parent::setUp();
  }

  /**
   * Test transpose plugin.
   *
   * @param array $input
   *   The input values.
   * @param mixed $expected_output
   *   The expected output.
   *
   * @dataProvider transposeDataProvider
   */
  #[DataProvider('transposeDataProvider')]
  public function testTranspose(array $input, array $expected_output): void {
    $output = $this->plugin->transform($input, $this->migrateExecutable, $this->row, 'destinationProperty');
    $this->assertSame($output, $expected_output);
  }

  /**
   * Data provider for testTranspose().
   *
   * @return array
   *   An array containing input values and expected output values.
   */
  public static function transposeDataProvider(): array {
    return [
      'empty array' => [
        'input' => [],
        'expected_output' => [],
      ],
      'simple array' => [
        'input' => [1, 2, 3],
        'expected_output' => [[1, 2, 3]],
      ],
      'image files and alt text' => [
        'input' => [
          ['2.png', '3.png', '5.png', '7.png'],
          ['two', 'three', 'five', 'seven'],
        ],
        'expected_output' => [
          ['2.png', 'two'],
          ['3.png', 'three'],
          ['5.png', 'five'],
          ['7.png', 'seven'],
        ],
      ],
      'indexed arrays' => [
        'input' => [
          ['a' => 1, 'b' => 2],
          ['c' => 3, 'd' => 4],
          ['e' => 5, 'f' => 6],
          ['g' => 7, 'h' => 8],
        ],
        'expected_output' => [[1, 3, 5, 7], [2, 4, 6, 8]],
      ],
    ];
  }

}
