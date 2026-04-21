<?php

namespace Drupal\Tests\message_digest\Unit\Plugin\Notifier;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Insert;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\State\StateInterface;
use Drupal\message\MessageInterface;
use Drupal\message_digest\Plugin\Notifier\Digest;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the Digest plugin.
 *
 * @group message_digest
 *
 * @coversDefaultClass \Drupal\message_digest\Plugin\Notifier\Digest
 */
class DigestTest extends UnitTestCase {

  /**
   * Digest configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * Mocked database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mocked rendering service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Plugin definition.
   *
   * @var array
   */
  protected $pluginDefinition = [
    'digest_interval' => '1 day',
  ];

  /**
   * Plugin ID.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * Mocked time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Mocked state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->connection = $this->prophesize(Connection::class)->reveal();
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class)->reveal();
    $this->pluginId = $this->randomMachineName();
    $this->renderer = $this->prophesize(RendererInterface::class)->reveal();
    $this->time = $this->prophesize(TimeInterface::class)->reveal();
    $this->state = $this->prophesize(StateInterface::class)->reveal();

  }

  /**
   * Test delivery without a saved message entity.
   *
   * @covers ::deliver
   */
  public function testDeliverUnsavedMessage() {
    // Setup an unsaved message.
    $message = $this->prophesize(MessageInterface::class)->reveal();

    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessage('The message entity (or $message->original_message) must be saved in order to create a digest entry.');
    $notifier = $this->getNotifier($message);
    $notifier->deliver([]);
  }

  /**
   * Test delivery without a saved original message entity.
   *
   * @covers ::deliver
   */
  public function testDeliveryUnsavedOriginal() {
    // Setup an unsaved original message.
    $original = $this->prophesize(MessageInterface::class)->reveal();
    $message = $this->prophesize(MessageInterface::class)->reveal();
    $message->original_message = $original;

    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessage('The message entity (or $message->original_message) must be saved in order to create a digest entry.');
    $notifier = $this->getNotifier($message);
    $notifier->deliver([]);
  }

  /**
   * Test delivery with an unsaved message, but a saved original.
   *
   * @covers ::deliver
   */
  public function testDeliverySavedOriginal() {
    // Setup a saved original.
    $original = $this->prophesize(MessageInterface::class);
    $original->id()->willReturn(42);
    $message = $this->prophesize(MessageInterface::class);
    $message->getOwnerId()->willReturn(4);
    $message->getCreatedTime()->willReturn(123);
    $message->id()->willReturn(NULL);
    $message = $message->reveal();
    $message->original_message = $original->reveal();

    // Mock up the insert.
    $expected_row = [
      'receiver' => 4,
      'entity_type' => '',
      'entity_id' => '',
      'notifier' => $this->pluginId,
      'timestamp' => 123,
      'mid' => 42,
    ];
    $insert = $this->prophesize(Insert::class);
    $insert->fields($expected_row)->willReturn($insert->reveal());
    $insert->execute()->shouldBeCalled();
    $connection = $this->prophesize(Connection::class);
    $connection->insert('message_digest')->willReturn($insert->reveal());
    $this->connection = $connection->reveal();

    $notifier = $this->getNotifier($message);
    $this->assertTrue($notifier->deliver([]));
  }

  /**
   * Test that digests will be processed at the correct time.
   *
   * @covers ::processDigest
   *
   * @dataProvider processTimeProvider
   */
  public function testProcessTimeThreshold($currentTime, $lastRun, $expectation) {
    $time = $this->prophesize(TimeInterface::class);
    $time->getRequestTime()->willReturn($currentTime);
    $this->time = $time->reveal();

    $state = $this->prophesize(StateInterface::class);
    $state->get($this->pluginId . '_last_run', 0)->willReturn($lastRun);
    $this->state = $state->reveal();

    $message = $this->prophesize(MessageInterface::class)->reveal();
    $notifier = $this->getNotifier($message);
    self::assertEquals($expectation, $notifier->processDigest());
  }

  /**
   * Provides test data for testProcessTimeThreshold().
   *
   * @return array
   *   Test data.
   */
  public static function processTimeProvider() {
    return [
      // 25 hours ago.
      [952646400, 952556400, TRUE],
      // 24 hours, 15 seconds ago.
      [952646400, 952559985, TRUE],
      // 24 hours ago.
      [952646400, 952560000, TRUE],
      // 23 hours, 59 minutes, 45 seconds ago.
      [952646400, 952560015, TRUE],
      // 23 hours, 59 minutes ago.
      [952646400, 952560060, FALSE],
      // 23 hours ago.
      [952646400, 952563600, FALSE],
    ];
  }

  /**
   * Helper method to construct a digest notifier.
   *
   * @param \Drupal\message\MessageInterface $message
   *   Mocked message for the notifier.
   *
   * @return \Drupal\message_digest\Plugin\Notifier\Digest
   *   The message notifier.
   */
  protected function getNotifier(MessageInterface $message) {
    $logger = $this->prophesize(LoggerChannelInterface::class)->reveal();

    $notifier = new Digest(
      $this->configuration,
      $this->pluginId,
      $this->pluginDefinition,
      $logger,
      $this->entityTypeManager,
      $this->renderer,
      $message,
      $this->state,
      $this->connection,
      $this->time
    );

    return $notifier;
  }

}
