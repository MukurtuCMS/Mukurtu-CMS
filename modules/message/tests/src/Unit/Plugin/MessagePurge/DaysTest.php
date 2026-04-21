<?php

namespace Drupal\Tests\message\Unit\Plugin\MessagePurge;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\message\MessagePurgeInterface;
use Drupal\message\Plugin\MessagePurge\Days;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for the days purge plugin.
 *
 * @coversDefaultClass \Drupal\message\Plugin\MessagePurge\Days
 *
 * @group Message
 */
class DaysTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Test processing zero message.
   *
   * @covers ::process
   */
  public function testProcessNone() {
    $query = $this->prophesize(QueryInterface::class)->reveal();
    $request_stack = $this->prophesize(RequestStack::class)->reveal();
    $queue = $this->prophesize(QueueInterface::class);
    $queue->createItem(Argument::any())->shouldNotBeCalled();
    $plugin = new Days([], 'days', [], $query, $queue->reveal(), $request_stack);
    $plugin->process([]);
  }

  /**
   * Tests processing more than defined queue item limit.
   *
   * @covers ::process
   */
  public function testProcess() {
    $query = $this->prophesize(QueryInterface::class)->reveal();
    $request_stack = $this->prophesize(RequestStack::class)->reveal();
    $queue = $this->prophesize(QueueInterface::class);
    $queue->createItem(Argument::size(MessagePurgeInterface::MESSAGE_DELETE_SIZE))->shouldBeCalledTimes(1);
    $queue->createItem(Argument::size(1))->shouldBeCalledTimes(1);
    $plugin = new Days([], 'days', [], $query, $queue->reveal(), $request_stack);

    $messages = range(1, MessagePurgeInterface::MESSAGE_DELETE_SIZE + 1);
    $plugin->process($messages);
  }

}
