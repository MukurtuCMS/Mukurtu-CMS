<?php

namespace Drupal\Tests\message_digest_ui\Kernel;

use Drupal\message\Entity\Message;
use Drupal\message_subscribe\Subscribers\DeliveryCandidate;
use Drupal\Tests\message_subscribe_email\Kernel\MessageSubscribeEmailTestBase;

/**
 * Basic tests for the Message Digest UI module.
 *
 * @group message_digest_ui
 */
class MessageDigestUiTest extends MessageSubscribeEmailTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'message_digest',
    'message_digest_ui',
    'options',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'message_digest_ui',
      'message_subscribe_email',
      'message_subscribe',
    ]);
    $this->messageSubscribers = $this->container->get('message_subscribe.subscribers');

    // Add a few more users.
    $permissions = [
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag email_node',
      'unflag email_node',
    ];
    foreach (range(3, 4) as $i) {
      $this->users[$i] = $this->createUser($permissions);
    }

    // Add an additional node.
    $type = $this->nodes[1]->getType();
    $this->nodes[2] = $this->createNode(['type' => $type]);
  }

  /**
   * Test that notifiers are not altered for users that are not using digests.
   */
  public function testNotificationsDefault() {
    $this->assertEmpty(($this->users[2]->message_digest->value));

    // Subscribe user 2 to node 1.
    $flag = $this->flagService->getFlagById('subscribe_node');
    $this->flagService->flag($flag, $this->nodes[1], $this->users[2]);

    // Check default 'immediate' is working.
    $message = Message::create(['template' => $this->messageTemplate->id()]);
    $uids = $this->messageSubscribers->getSubscribers($this->nodes[1], $message);

    // Assert subscribers data.
    $expected_uids = [
      $this->users[2]->id() => new DeliveryCandidate(['subscribe_node'], ['email'], $this->users[2]->id()),
    ];

    $this->assertEquals($expected_uids, $uids, 'All expected subscribers were fetched.');
  }

  /**
   * Tests that notifiers are properly altered for digest users.
   */
  public function testNotifiersDigest() {
    // Set user 2 to receive daily digests.
    $this->users[2]->message_digest = 'message_digest:daily';
    $this->users[2]->save();
    $this->assertEquals('message_digest:daily', $this->users[2]->message_digest->value);

    // Subscribe users 2 and 3 to node 1.
    $flag = $this->flagService->getFlagById('subscribe_node');
    $this->container->set('current_user', $this->users[2]);
    $this->flagService->flag($flag, $this->nodes[1], $this->users[2]);
    $this->container->set('current_user', $this->users[3]);
    $this->flagService->flag($flag, $this->nodes[1], $this->users[3]);

    // Subscribe user 3 to node 2, and set to digests.
    $this->flagService->flag($flag, $this->nodes[2], $this->users[3]);
    $flaggings = $this->flagService->getAllEntityFlaggings($this->nodes[2], $this->users[3]);
    $this->assertEquals(2, count($flaggings));
    // User 3 should not have digests set for this node initially.
    $this->assertEmpty($flaggings[6]->message_digest->value);
    // Set to receive digests for node 2.
    $flaggings[6]->message_digest = 'message_digest:daily';
    $flaggings[6]->save();

    // Verify that the corresponding email flagging has the user's digest set.
    $flaggings = $this->flagService->getAllEntityFlaggings($this->nodes[1], $this->users[2]);
    $this->assertEquals(2, count($flaggings));
    $email_flag = $flaggings[2];
    $this->assertEquals('message_digest:daily', $email_flag->message_digest->value);

    // Assert subscribers data.
    $expected_uids = [
      $this->users[2]->id() => new DeliveryCandidate(['subscribe_node'], ['message_digest:daily'], $this->users[2]->id()),
      $this->users[3]->id() => new DeliveryCandidate(['subscribe_node'], ['email'], $this->users[3]->id()),
    ];

    $message = Message::create(['template' => $this->messageTemplate->id()]);
    $uids = $this->messageSubscribers->getSubscribers($this->nodes[1], $message);
    $this->assertEquals($expected_uids, $uids, 'All expected subscribers were fetched.');
  }

  /**
   * Test with advanced contexts.
   */
  public function testWithContexts() {
    // Set user 2  and 3 to receive daily digests.
    $this->users[2]->message_digest = 'message_digest:daily';
    $this->users[2]->save();
    $this->assertEquals('message_digest:daily', $this->users[2]->message_digest->value);
    $this->users[3]->message_digest = 'message_digest:daily';
    $this->users[3]->save();

    // Subscribe users 2 and 3 to node 1.
    $flag = $this->flagService->getFlagById('subscribe_node');
    $this->container->set('current_user', $this->users[2]);
    $this->flagService->flag($flag, $this->nodes[1], $this->users[2]);
    $this->container->set('current_user', $this->users[3]);
    $this->flagService->flag($flag, $this->nodes[1], $this->users[3]);

    // Subscribe users 2 and 3 to node 2.
    $this->flagService->flag($flag, $this->nodes[2], $this->users[3]);
    $this->flagService->flag($flag, $this->nodes[2], $this->users[2]);

    // Set user 2 to receive instant notifications for node 1.
    $flaggings = $this->flagService->getAllEntityFlaggings($this->nodes[1], $this->users[2]);
    $flaggings[2]->message_digest = '0';
    $flaggings[2]->save();

    // Set user 3 to receive weekly notifications for node 1. Digest should
    // still be daily below since it is smaller.
    $flaggings = $this->flagService->getAllEntityFlaggings($this->nodes[1], $this->users[3]);
    $flaggings[4]->message_digest = 'message_digest:weekly';
    $flaggings[4]->save();

    // Send a message about node 1 and 2. Since user 2 has daily for node 2 and
    // immediately for node 1, message should be sent immediately.
    $context = [
      'node' => [
        $this->nodes[1]->id(),
        $this->nodes[2]->id(),
      ],
    ];

    // Assert subscribers data.
    $expected_uids = [
      $this->users[2]->id() => new DeliveryCandidate(['subscribe_node'], ['email'], $this->users[2]->id()),
      $this->users[3]->id() => new DeliveryCandidate(['subscribe_node'], ['message_digest:daily'], $this->users[3]->id()),
    ];

    $message = Message::create(['template' => $this->messageTemplate->id()]);
    $uids = $this->messageSubscribers->getSubscribers($this->nodes[1], $message, [], $context);
    $this->assertEquals($expected_uids, $uids, 'All expected subscribers were fetched.');
  }

}
