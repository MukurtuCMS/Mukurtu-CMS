<?php

namespace Drupal\Tests\message_subscribe\Unit\Subscribers;

use Drupal\message_subscribe\Subscribers\DeliveryCandidate;
use Drupal\message_subscribe\Subscribers\DeliveryCandidateInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the delivery candidate class.
 *
 * @group message_subscribe
 *
 * @coversDefaultClass \Drupal\message_subscribe\Subscribers\DeliveryCandidate
 */
class DeliveryCandidateTest extends UnitTestCase {

  /**
   * Test construction.
   *
   * @covers ::__construct
   * @covers ::getFlags
   * @covers ::getNotifiers
   * @covers ::getAccountId
   */
  public function testConstruct() {
    $candidate = new DeliveryCandidate(['foo'], ['bar'], 123);
    $this->assertEquals(['foo' => 'foo'], $candidate->getFlags());
    $this->assertEquals(['bar' => 'bar'], $candidate->getNotifiers());
    $this->assertEquals(123, $candidate->getAccountId());
  }

  /**
   * Test adding and removing flags.
   *
   * @covers ::addFlag
   * @covers ::removeFlag
   * @covers ::getFlags
   * @covers ::setFlags
   */
  public function testAddRemoveFlag() {
    $candidate = new DeliveryCandidate([], [], 42);
    $this->assertEmpty($candidate->getFlags());
    $this->assertInstanceOf(DeliveryCandidateInterface::class, $candidate->addFlag('foo'));
    $this->assertEquals(['foo' => 'foo'], $candidate->getFlags());
    $this->assertInstanceOf(DeliveryCandidateInterface::class, $candidate->removeFlag('foo'));
    $this->assertEmpty($candidate->getFlags());
    $this->assertInstanceOf(DeliveryCandidateInterface::class, $candidate->setFlags([
      'foo',
      'bar',
    ]));
    $this->assertEquals(['foo' => 'foo', 'bar' => 'bar'], $candidate->getFlags());
  }

  /**
   * Test adding and removing flags.
   *
   * @covers ::addNotifier
   * @covers ::removeNotifier
   * @covers ::getNotifiers
   * @covers ::setNotifiers
   */
  public function testAddRemoveNotifier() {
    $candidate = new DeliveryCandidate([], [], 42);
    $this->assertEmpty($candidate->getNotifiers());
    $this->assertInstanceOf(DeliveryCandidateInterface::class, $candidate->addNotifier('foo'));
    $this->assertEquals(['foo' => 'foo'], $candidate->getNotifiers());
    $this->assertInstanceOf(DeliveryCandidateInterface::class, $candidate->removeNotifier('foo'));
    $this->assertEmpty($candidate->getNotifiers());
    $this->assertInstanceOf(DeliveryCandidateInterface::class, $candidate->setNotifiers([
      'foo',
      'bar',
    ]));
    $this->assertEquals(['foo' => 'foo', 'bar' => 'bar'], $candidate->getNotifiers());
  }

  /**
   * Test setting account ID.
   *
   * @covers ::getAccountId
   * @covers ::setAccountId
   */
  public function testAccountId() {
    $candidate = new DeliveryCandidate([], [], 42);
    $this->assertEquals(42, $candidate->getAccountId());
    $this->assertInstanceOf(DeliveryCandidateInterface::class, $candidate->setAccountId(123));
    $this->assertEquals(123, $candidate->getAccountId());
  }

}
