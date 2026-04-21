<?php

namespace Drupal\Tests\message_subscribe_email\Kernel;

use Drupal\message\Entity\Message;
use Drupal\message_subscribe\Subscribers\DeliveryCandidate;

/**
 * Test automatic email notification flagging.
 *
 * @group message_subscribe_email
 */
class MessageSubscribeEmailNotificationsTest extends MessageSubscribeEmailTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $flag = $this->flagService->getFlagById('subscribe_node');
    $this->flagService->flag($flag, $this->nodes[1], $this->users[1]);
  }

  /**
   * Test opting in/out of default email notifications.
   */
  public function testEmailNotifications() {
    $message = Message::create(['template' => $this->messageTemplate->id()]);

    $uids = $this->messageSubscribers->getSubscribers($this->nodes[1], $message);

    // Assert subscribers data.
    $expected_uids = [
      $this->users[1]->id() => new DeliveryCandidate(['subscribe_node'], ['email'], $this->users[1]->id()),
    ];

    $this->assertEquals($expected_uids, $uids, 'All expected subscribers were fetched.');

    $this->flagService->unflag($this->flagService->getFlagById('subscribe_node'), $this->nodes[1], $this->users[1]);

    // Subscribe to node 2 *with* email notifications.
    $this->flagService->flag($this->flagService->getFlagById('subscribe_node'), $this->nodes[2], $this->users[1]);

    // Opt out of default email notifications.
    $this->users[1]->message_subscribe_email = 0;
    $this->users[1]->save();

    $this->flagService->flag($this->flagService->getFlagById('subscribe_node'), $this->nodes[1], $this->users[1]);

    $uids = $this->messageSubscribers->getSubscribers($this->nodes[1], $message);

    // Assert subscribers data.
    $expected_uids = [
      $this->users[1]->id() => new DeliveryCandidate(['subscribe_node'], [], $this->users[1]->id()),
    ];

    $this->assertEquals($expected_uids, $uids, 'All expected subscribers were fetched.');

    // Test with advanced contexts, passing node 2 directly, but node 1 in the
    // context.
    $context = [
      'node' => [$this->nodes[1]->id()],
    ];
    $uids = $this->messageSubscribers->getSubscribers($this->nodes[2], $message, [], $context);
    $this->assertEquals($expected_uids, $uids);
  }

  /**
   * Verify flag action access for the email_* flags.
   */
  public function testFlagActionAccess() {
    $node = $this->nodes[1];
    $user = $this->users[1];
    $email_flag = $this->flagService->getFlagById('email_node');
    $subscribe_flag = $this->flagService->getFlagById('subscribe_node');

    // When the item is flagged, flag and unflag access should be available.
    $access = $email_flag->actionAccess('flag', $user, $node);
    $this->assertTrue($access->isAllowed());
    $access = $email_flag->actionAccess('unflag', $user);
    $this->assertTrue($access->isAllowed());

    // Unflag the entity, and now only the unflag action should be available.
    $this->flagService->unflag($subscribe_flag, $node, $user);
    $access = $email_flag->actionAccess('flag', $user, $node);
    $this->assertFalse($access->isAllowed());
    $access = $email_flag->actionAccess('unflag', $user, $node);
    $this->assertTrue($access->isAllowed());
  }

}
