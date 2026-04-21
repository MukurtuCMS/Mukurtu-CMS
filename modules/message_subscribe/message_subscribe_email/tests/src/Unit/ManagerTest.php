<?php

namespace Drupal\Tests\message_subscribe_email\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message_subscribe_email\Manager;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Unit tests for the message subscribe email manager utility class.
 *
 * @coversDefaultClass \Drupal\message_subscribe_email\Manager
 *
 * @group message_subscribe_email
 */
class ManagerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests the flag retrieval.
   *
   * @covers ::getFlags
   */
  public function testGetFlagsMatching() {
    $flag = $this->prophesize(FlagInterface::class)->reveal();
    $expected = [
      'non_standard_prefix_one' => $flag,
      'non_standard_prefix_two' => $flag,
    ];
    $flag_service = $this->prophesize(FlagServiceInterface::class);
    $flag_service->getAllFlags()->willReturn([
      'foo_flag' => $flag,
      'non_standard_prefix_one' => $flag,
      'non_standard_prefix_two' => $flag,
    ]);

    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('flag_prefix')->willReturn('non_standard_prefix');
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('message_subscribe_email.settings')->willReturn($config->reveal());

    $manager = new Manager($flag_service->reveal(), $config_factory->reveal());
    $this->assertEquals($expected, $manager->getFlags());
  }

  /**
   * Tests the flag retrieval.
   *
   * @covers ::getFlags
   */
  public function testGetFlagsNoFlags() {
    $flag_service = $this->prophesize(FlagServiceInterface::class);
    $flag_service->getAllFlags()->willReturn([]);

    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('flag_prefix')->willReturn('non_standard_prefix');
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('message_subscribe_email.settings')->willReturn($config->reveal());

    $manager = new Manager($flag_service->reveal(), $config_factory->reveal());
    $this->assertEquals([], $manager->getFlags());
  }

  /**
   * Tests the flag retrieval.
   *
   * @covers ::getFlags
   */
  public function testGetFlagsNoPrefix() {
    $flag = $this->prophesize(FlagInterface::class)->reveal();
    $flag_service = $this->prophesize(FlagServiceInterface::class);
    $flag_service->getAllFlags()->willReturn(['foo_flag' => $flag]);

    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('flag_prefix')->willReturn('non_standard_prefix');
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('message_subscribe_email.settings')->willReturn($config->reveal());

    $manager = new Manager($flag_service->reveal(), $config_factory->reveal());
    $this->assertEquals([], $manager->getFlags());
  }

}
