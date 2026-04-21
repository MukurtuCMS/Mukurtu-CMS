<?php

namespace Drupal\Tests\geofield\Unit;

use Drupal\geofield\DmsConverter;
use Drupal\geofield\DmsPoint;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\geofield\DmsConverter
 * @group geofield
 */
class DmsConverterTest extends UnitTestCase {

  /**
   * @covers ::dmsToDecimal
   * @covers ::decimalToDms
   *
   * @dataProvider dataProvider
   */
  public function testConverter(DmsPoint $dms, $decimal) {
    $result = DmsConverter::dmsToDecimal($dms);
    $this->assertEquals($decimal, $result);

    $result = DmsConverter::decimalToDms($decimal[0], $decimal[1]);
    $this->assertEquals($dms, $result);
  }

  /**
   * Data provider for testConverter.
   *
   * @return array
   *   A list of equivalent DMS/Decimal coordinates.
   */
  public static function dataProvider(): array {
    return [
      'Simple' => [
        new DmsPoint([
          'orientation' => 'E',
          'degrees' => 40,
          'minutes' => 0,
          'seconds' => 0,
        ],
          [
            'orientation' => 'N',
            'degrees' => 9,
            'minutes' => 0,
            'seconds' => 0,
          ]
        ),
        [40, 9],
      ],
      'Negative' => [
        new DmsPoint([
          'orientation' => 'W',
          'degrees' => 40,
          'minutes' => 0,
          'seconds' => 0,
        ],
          [
            'orientation' => 'S',
            'degrees' => 9,
            'minutes' => 0,
            'seconds' => 0,
          ]
        ),
        [-40, -9],
      ],
      'Decimal' => [
        new DmsPoint([
          'orientation' => 'W',
          'degrees' => 3,
          'minutes' => 3,
          'seconds' => 3,
        ],
          [
            'orientation' => 'S',
            'degrees' => 2,
            'minutes' => 2,
            'seconds' => 2,
          ]
        ),
        [-3.0508333333, -2.0338888889],
      ],
    ];

  }

}
