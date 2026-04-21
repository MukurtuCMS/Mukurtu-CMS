<?php

namespace Drupal\Tests\message_subscribe_email\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\message\Entity\Message;
use Drupal\message_subscribe\Subscribers\DeliveryCandidate;

/**
 * Test getting email subscribes from context.
 *
 * @group message_subscribe
 */
class MessageSubscribeEmailSubscribersTest extends MessageSubscribeEmailTestBase {

  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Opt out of default email notifications and subscribe to node 1.
    $flag = $this->flagService->getFlagById('subscribe_node');
    foreach (range(1, 2) as $i) {
      $this->users[$i]->message_subscribe_email = 0;
      $this->users[$i]->save();
      $this->flagService->flag($flag, $this->nodes[1], $this->users[$i]);
    }
    // Flag user 1 for email notifications.
    $flag = $this->flagService->getFlagById('email_node');
    $this->flagService->flag($flag, $this->nodes[1], $this->users[1]);
  }

  /**
   * Test getting the subscribers list.
   */
  public function testGetSubscribers() {
    $message = Message::create(['template' => $this->messageTemplate->id()]);

    $node = $this->nodes[1];
    $user1 = $this->users[1];
    $user2 = $this->users[2];

    $uids = $this->messageSubscribers->getSubscribers($node, $message);

    // Assert subscribers data.
    $expected_uids = [
      $user1->id() => new DeliveryCandidate(['subscribe_node'], ['email'], $user1->id()),
      $user2->id() => new DeliveryCandidate(['subscribe_node'], [], $user2->id()),
    ];

    $this->assertEquals($expected_uids, $uids, 'All expected subscribers were fetched.');

    $subscribe_options = [
      'uids' => $uids,
    ];
    $this->messageSubscribers->sendMessage($node, $message, [], $subscribe_options);

    // Assert sent emails.
    $mails = $this->getMails();
    $this->assertEquals(1, count($mails), 'Only one user was sent an email.');
    $this->assertEquals('message_notify_' . $this->messageTemplate->id(), $mails[0]['id']);
  }

  /**
   * Tests behavior with the default notifiers in place.
   */
  public function testWithDefaultNotifiers() {
    $this->config('message_subscribe.settings')
      // Override default notifiers.
      ->set('default_notifiers', ['email'])
      ->save();

    $message = Message::create(['template' => $this->messageTemplate->id()]);

    $node = $this->nodes[1];
    $user1 = $this->users[1];
    $user2 = $this->users[2];

    $uids = $this->messageSubscribers->getSubscribers($node, $message);

    // Assert subscribers data.
    $expected_uids = [
      $user1->id() => new DeliveryCandidate(['subscribe_node'], ['email'], $user1->id()),
      $user2->id() => new DeliveryCandidate(['subscribe_node'], [], $user2->id()),
    ];

    $this->assertEquals($expected_uids, $uids, 'All expected subscribers were fetched.');

    $subscribe_options = [
      'uids' => $uids,
    ];
    $this->messageSubscribers->sendMessage($node, $message, [], $subscribe_options);

    // Assert sent emails.
    $mails = $this->getMails();
    $this->assertEquals(1, count($mails), 'Only user 1 was sent an email.');
    $this->assertEquals('message_notify_' . $this->messageTemplate->id(), $mails[0]['id']);
  }

}
