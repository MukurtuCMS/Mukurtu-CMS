<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Unit;

use Drupal\mukurtu_core\Plugin\Geocoder\Formatter\MukurtuFormattedAddress;
use Drupal\Tests\UnitTestCase;
use Geocoder\Model\Address;

/**
 * Tests the address search result formatting (issue #1453).
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1453
 * @group mukurtu_core
 */
class MukurtuFormattedAddressTest extends UnitTestCase {

  /**
   * Builds the plugin under test.
   */
  private function createFormatter(): MukurtuFormattedAddress {
    return new MukurtuFormattedAddress([], 'mukurtu_formatted_address', []);
  }

  /**
   * A full US-style result includes state and postal code, once each.
   */
  public function testFullAddressIncludesStateAndPostalCode(): void {
    $address = Address::createFromArray([
      'streetNumber' => '123',
      'streetName' => 'Main St',
      'locality' => 'Pullman',
      'postalCode' => '99163',
      'adminLevels' => [
        ['level' => 1, 'name' => 'Washington'],
      ],
      'country' => 'United States',
    ]);

    $this->assertSame(
      '123 Main St, Pullman, Washington, 99163, United States',
      $this->createFormatter()->format($address)
    );
  }

  /**
   * When there is no state-level division, fall back to the county (level 2).
   */
  public function testFallsBackToCountyWhenNoState(): void {
    $address = Address::createFromArray([
      'streetName' => 'High St',
      'locality' => 'Oxford',
      'adminLevels' => [
        ['level' => 2, 'name' => 'Oxfordshire'],
      ],
      'country' => 'United Kingdom',
    ]);

    $this->assertSame(
      'High St, Oxford, Oxfordshire, United Kingdom',
      $this->createFormatter()->format($address)
    );
  }

  /**
   * A missing postal code must not leave a stray double comma.
   */
  public function testMissingPostalCodeLeavesNoDoubleComma(): void {
    $address = Address::createFromArray([
      'streetName' => 'Trail Rd',
      'locality' => 'Springfield',
      'adminLevels' => [
        ['level' => 1, 'name' => 'Missouri'],
      ],
      'country' => 'United States',
    ]);

    $this->assertSame(
      'Trail Rd, Springfield, Missouri, United States',
      $this->createFormatter()->format($address)
    );
  }

  /**
   * A result with no admin-level data at all still formats cleanly.
   */
  public function testMissingStateAndCountyOmitsBothCleanly(): void {
    $address = Address::createFromArray([
      'streetName' => 'Ocean Dr',
      'locality' => 'Some Atoll',
      'postalCode' => '96898',
      'country' => 'United States Minor Outlying Islands',
    ]);

    $this->assertSame(
      'Ocean Dr, Some Atoll, 96898, United States Minor Outlying Islands',
      $this->createFormatter()->format($address)
    );
  }

}
