<?php

namespace Drupal\Tests\search_api\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Search API utility class.
 *
 * @coversDefaultClass \Drupal\search_api\Utility\Utility
 *
 * @group search_api
 */
class UtilityTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturn(NULL);
    $config_factory->method('get')->willReturnMap([
      ['search_api.settings', $config],
    ]);
    $container->set('config.factory', $config_factory);
    \Drupal::setContainer($container);
  }

  /**
   * Tests formatting of boost factors.
   *
   * @covers ::formatBoostFactor
   */
  public function testFormatBoostFactor() {
    $this->assertEquals('0.00', Utility::formatBoostFactor(0));
    $this->assertEquals('0.00', Utility::formatBoostFactor(0.0));
    $this->assertEquals('0.00', Utility::formatBoostFactor(0.00));
    $this->assertEquals('0.00', Utility::formatBoostFactor('0'));
    $this->assertEquals('0.00', Utility::formatBoostFactor('0.0'));
    $this->assertEquals('0.00', Utility::formatBoostFactor('0.00'));
    $this->assertEquals('0.00', Utility::formatBoostFactor(''));
    $this->assertEquals('1.00', Utility::formatBoostFactor(1));
    $this->assertEquals('1.00', Utility::formatBoostFactor(1.0));
    $this->assertEquals('1.00', Utility::formatBoostFactor(1.00));
    $this->assertEquals('1.10', Utility::formatBoostFactor(1.1));
    $this->assertEquals('1.01', Utility::formatBoostFactor(1.01));
    $this->assertEquals('1.00', Utility::formatBoostFactor('1'));
    $this->assertEquals('1.10', Utility::formatBoostFactor('1.1'));
    $this->assertEquals('1.01', Utility::formatBoostFactor('1.01'));
  }

  /**
   * Tests obtaining a list of available boost factors.
   *
   * @covers ::getBoostFactors
   */
  public function testGetBoostFactors() {
    $expected = [
      '0.00' => '0.00',
      '0.10' => '0.10',
      '0.20' => '0.20',
      '0.30' => '0.30',
      '0.50' => '0.50',
      '0.60' => '0.60',
      '0.70' => '0.70',
      '0.80' => '0.80',
      '0.90' => '0.90',
      '1.00' => '1.00',
      '1.10' => '1.10',
      '1.20' => '1.20',
      '1.30' => '1.30',
      '1.40' => '1.40',
      '1.50' => '1.50',
      '2.00' => '2.00',
      '3.00' => '3.00',
      '5.00' => '5.00',
      '8.00' => '8.00',
      '13.00' => '13.00',
      '21.00' => '21.00',
    ];
    $this->assertEquals($expected, Utility::getBoostFactors());

    $additional_factors = [
      4,
      '0.81',
      3,
      0.81,
      '3',
      '8.2',
      '0.81',
      14.123,
      0.811,
    ];
    $expected = [
      '0.00' => '0.00',
      '0.10' => '0.10',
      '0.20' => '0.20',
      '0.30' => '0.30',
      '0.50' => '0.50',
      '0.60' => '0.60',
      '0.70' => '0.70',
      '0.80' => '0.80',
      '0.81' => '0.81',
      '0.90' => '0.90',
      '1.00' => '1.00',
      '1.10' => '1.10',
      '1.20' => '1.20',
      '1.30' => '1.30',
      '1.40' => '1.40',
      '1.50' => '1.50',
      '2.00' => '2.00',
      '3.00' => '3.00',
      '4.00' => '4.00',
      '5.00' => '5.00',
      '8.00' => '8.00',
      '8.20' => '8.20',
      '13.00' => '13.00',
      '14.12' => '14.12',
      '21.00' => '21.00',
    ];
    $this->assertEquals($expected, Utility::getBoostFactors($additional_factors));
  }

}
