<?php

namespace Drupal\Tests\message_digest\Kernel;

use Drupal\Core\Mail\MailFormatHelper;
use Drupal\message\Entity\Message;
use Drupal\message_digest\Exception\InvalidDigestGroupingException;

/**
 * Kernel tests for Message Digest.
 *
 * @group message_digest
 */
class MessageDigestTest extends DigestTestBase {

  /**
   * Tests the plugin deriver for daily and weekly digests.
   */
  public function testDigestNotifierPluginsExist() {
    $count = 0;
    foreach ($this->notifierManager->getDefinitions() as $plugin_id => $plugin_definition) {
      if ($plugin_definition['provider'] === 'message_digest') {
        $dummy = Message::create(['template' => 'foo']);
        // Ensure the plugin can be instantiated.
        $this->notifierManager->createInstance($plugin_id, [], $dummy);
        $count++;
      }
    }
    $this->assertEquals(2, $count, 'There are 2 digest notifiers.');
  }

  /**
   * Tests the notifier sending and delivery.
   *
   * @param bool $reference_entity
   *   Whether or not an entity should be referenced in the message digest that
   *   is being sent.
   * @param string $expected_subject
   *   The expected subject for the email that is being sent.
   *
   * @dataProvider providerTestNotifierDelivery
   */
  public function testNotifierDelivery($reference_entity, $expected_subject) {
    // Set the site name, so we can check that it is used in the subject of the
    // digest e-mail.
    $this->config('system.site')->set('name', 'Test site')->save();

    $template = $this->createMessageTemplate('foo', 'Foo', 'Foo, foo', ['Test message']);
    $dummy = Message::create(['template' => $template->id()]);
    /** @var \Drupal\message_digest\Plugin\Notifier\DigestInterface $digest_notifier */
    $digest_notifier = $this->notifierManager->createInstance('message_digest:daily', [], $dummy);

    $configuration = [];
    // If we are referencing an entity, create a test user and reference it in
    // the message digest.
    if ($reference_entity) {
      $referenced_user = $this->createUser([], 'Test user');
      $configuration = [
        'entity_type' => 'user',
        'entity_id' => $referenced_user->id(),
      ];
    }

    // Create a recipient and send the message.
    $account = $this->createUser();
    $dummy->setOwner($account);
    $dummy->save();
    $this->notifierSender->send($dummy, $configuration, $digest_notifier->getPluginId());
    $result = $this->container->get('database')
      ->select('message_digest', 'm')
      ->fields('m')
      ->execute()
      ->fetchAllAssoc('id');
    $this->assertEquals(1, count($result));
    foreach ($result as $row) {
      $this->assertEquals($account->id(), $row->receiver);
      $this->assertEquals($digest_notifier->getPluginId(), $row->notifier);
    }

    // Now deliver the message.
    $this->sendDigests();

    $this->assertMail('subject', $expected_subject, 'Expected email subject is set.');
    $this->assertMail('body', "Test message\n\n", 'Expected email body is set.');
    $this->assertMail('id', 'message_digest_digest', 'Expected email key is set.');
    $this->assertMail('to', $account->getEmail(), 'Expected email recipient is set.');

    // Verify that the aggregate alter hook was called.
    // @see message_digest_test_message_digest_aggregate_alter()
    $this->assertTrue($this->container->get('state')->get('message_digest_test_message_digest_aggregate_alter', FALSE));

    // Verify that hook_message_digest_view_mode_alter() has been called.
    // @see message_digest_test_message_digest_view_mode_alter().
    $this->assertTrue($this->container->get('state')->get('message_digest_test_message_digest_view_mode_alter', FALSE));
  }

  /**
   * Data provider for ::testNotifierDelivery().
   *
   * @return array
   *   An array of test data, each test case an array with two elements:
   *   - A boolean indicating whether or not an entity should be referenced.
   *   - The expected subject of the message digest e-mail that is sent.
   */
  public static function providerTestNotifierDelivery() {
    return [
      // Test case that does not reference an entity. In this case the site name
      // should be mentioned in the message subject.
      [
        FALSE,
        'Test site message digest',
      ],
      // Test case that references an entity. In this case the name of the
      // entity should be mentioned in the message subject. We are using a user
      // entity to test this case.
      [
        TRUE,
        'Test user message digest',
      ],
    ];
  }

  /**
   * Tests message aggregation.
   */
  public function testNotifierAggregation() {
    // Send several messages.
    $template = $this->createMessageTemplate('foo', 'Foo', 'Foo, foo', []);
    $account = $this->createUser();
    $message_1 = Message::create(['template' => $template->id()]);
    $message_2 = Message::create(['template' => $template->id()]);
    /** @var \Drupal\message_digest\Plugin\Notifier\DigestInterface $digest_notifier */
    $digest_notifier = $this->notifierManager->createInstance('message_digest:weekly', [], $message_1);

    $message_1->setOwner($account);
    $message_1->save();
    $message_2->setOwner($account);
    $message_2->save();
    $this->notifierSender->send($message_1, [], $digest_notifier->getPluginId());
    $this->notifierSender->send($message_2, [], $digest_notifier->getPluginId());
    $result = $this->container->get('database')
      ->select('message_digest', 'm')
      ->fields('m')
      ->execute()
      ->fetchAllAssoc('id');
    $this->assertEquals(2, count($result));
    foreach ($result as $row) {
      $this->assertEquals($account->id(), $row->receiver);
      $this->assertEquals($digest_notifier->getPluginId(), $row->notifier);
    }

    // Aggregate and mark processed.
    $start_time = $digest_notifier->getEndTime();
    $recipients = $digest_notifier->getRecipients();
    $this->assertEquals(1, count($recipients));
    foreach ($recipients as $uid) {
      $results = $digest_notifier->aggregate($uid, $start_time);
      $digest_notifier->setLastSent();
      $expected = [
        '' => [
          '' => [
            $message_1->id(),
            $message_2->id(),
          ],
        ],
      ];
      $this->assertSame($expected, $results);
    }

    // Since this has been marked as sent, the notifier should return FALSE
    // for processing again.
    $this->assertFalse($digest_notifier->processDigest());

    // Set last sent time to 8 days in the past.
    $last_run = strtotime('-8 days', $this->container->get('datetime.time')->getRequestTime());
    $this->container->get('state')->set($digest_notifier->getPluginId() . '_last_run', $last_run);
    $results = $digest_notifier->aggregate($account->id(), $start_time);
    $this->assertSame($expected, $results);

    // Aggregate should not return any results once marked sent.
    $digest_notifier->markSent($account, $message_2->id());
    $this->assertEmpty($digest_notifier->getRecipients());
  }

  /**
   * Test grouping by entity type, and ID.
   */
  public function testDigestGrouping() {
    $template = $this->createMessageTemplate('foo', 'Foo', 'Foo, foo', []);
    $account = $this->createUser();

    // Send several messages w/o grouping.
    $global_messages = [];
    foreach (range(1, 3) as $i) {
      $message = Message::create(['template' => $template->id()]);
      $message->setOwner($account);
      $message->save();
      $global_messages[$i] = $message;
      $digest_notifier = $this->notifierManager->createInstance('message_digest:weekly', [], $message);
      $this->notifierSender->send($message, [], $digest_notifier->getPluginId());
    }

    // Now send some grouped by entity type.
    $configuration = [
      'entity_type' => 'foo',
      'entity_id' => 'bar',
    ];
    $grouped_messages = [];
    foreach (range(1, 3) as $i) {
      $message = Message::create(['template' => $template->id()]);
      $message->setOwner($account);
      $message->save();
      $grouped_messages[$i] = $message;
      /** @var \Drupal\message_digest\Plugin\Notifier\DigestInterface $digest_notifier */
      $digest_notifier = $this->notifierManager->createInstance('message_digest:weekly', $configuration, $message);
      $this->notifierSender->send($message, $configuration, $digest_notifier->getPluginId());
    }

    // Aggregate and mark processed.
    $results = $digest_notifier->aggregate($account->id(), $digest_notifier->getEndTime());
    $digest_notifier->setLastSent();
    $expected = [
      '' => [
        '' => ['1', '2', '3'],
      ],
      'foo' => [
        'bar' => ['4', '5', '6'],
      ],
    ];
    $this->assertSame($expected, $results);
  }

  /**
   * Returns some message text, including HTML.
   *
   * @return array
   *   An array of message text.
   */
  protected function getMessageText() {
    $text = [];

    // Subject.
    $text[] = [
      'value' => 'Test subject',
      'format' => 'filtered_html',
    ];

    // Body.
    $text[] = [
      'value' => '<div class="foo-bar">Some sweet <a href="[site:url]">message</a>.',
      'format' => 'full_html',
    ];

    return $text;
  }

  /**
   * Tests sending with an entity_id and no type.
   */
  public function testInvalidEntityType() {
    $configuration = [
      'entity_id' => 42,
    ];
    $template = $this->createMessageTemplate('foo', 'Foo', 'Foo, foo', []);
    $message = Message::create(['template' => $template->id()]);
    $digest_notifier = $this->notifierManager->createInstance('message_digest:weekly', $configuration, $message);

    $this->expectException(InvalidDigestGroupingException::class);
    $this->expectExceptionMessage('Tried to create a message digest without both entity_type () and entity_id (42). These either both need to be empty, or have values.');
    $this->notifierSender->send($message, $configuration, $digest_notifier->getPluginId());
  }

  /**
   * Tests sending with an entity_type and no ID.
   */
  public function testInvalidEntityId() {
    $configuration = [
      'entity_type' => 'foo',
    ];
    $template = $this->createMessageTemplate('foo', 'Foo', 'Foo, foo', []);
    $message = Message::create(['template' => $template->id()]);
    $digest_notifier = $this->notifierManager->createInstance('message_digest:weekly', $configuration, $message);

    $this->expectException(InvalidDigestGroupingException::class);
    $this->expectExceptionMessage('Tried to create a message digest without both entity_type (foo) and entity_id (). These either both need to be empty, or have values.');
    $this->notifierSender->send($message, $configuration, $digest_notifier->getPluginId());
  }

  /**
   * Test old message purging.
   */
  public function testMessageCleanup() {
    $template = $this->createMessageTemplate('foo', 'Foo', 'Foo, foo', []);
    $account = $this->createUser();
    $messages = [];
    // Send 10 messages.
    foreach (range(1, 10) as $i) {
      $message = Message::create(['template' => $template->id()]);
      $message->setOwner($account);
      $message->save();
      $digest_notifier = $this->notifierManager->createInstance('message_digest:weekly', [], $message);
      $this->notifierSender->send($message, [], $digest_notifier->getPluginId());
      $messages[$i] = $message;
    }
    $digest = $digest_notifier->aggregate($account->id(), $digest_notifier->getEndTime());
    $this->assertEquals(10, count($digest['']['']));
    $digest_notifier->markSent($account, $messages[10]->id());

    // Delete 5 messages.
    foreach (range(1, 5) as $i) {
      $messages[$i]->delete();
    }
    $this->digestManager->cleanupOldMessages();
    $result = $this->container->get('database')->select('message_digest', 'md')
      ->fields('md')
      ->execute()
      ->fetchAllAssoc('id');
    $this->assertEquals(5, count($result));
    foreach ($result as $row) {
      $this->assertGreaterThan(5, $row->mid);
    }
  }

  /**
   * Tests that the message_digest table is cleaned up when deleting entities.
   */
  public function testOrphanedEntityReferences() {
    $template = $this->createMessageTemplate('foo', 'Foo', 'Foo, foo', []);

    // Create 3 test users and send 3 messages, one to each user.
    $users = $messages = [];
    for ($i = 0; $i < 3; $i++) {
      $user = $this->createUser();

      $message = Message::create(['template' => $template->id()]);
      $message->setOwner($user);
      $message->save();

      $digest_notifier = $this->notifierManager->createInstance('message_digest:weekly', [], $message);
      $this->notifierSender->send($message, [], $digest_notifier->getPluginId());

      $users[$i] = $user;
      $messages[$i] = $message;
    }

    // There should be 3 message digests.
    $this->assertRowCount(3);

    // Delete one of the messages and verify that the corresponding message
    // digest is cleaned up.
    $orphaned_message_id = $messages[0]->id();
    $messages[0]->delete();
    $this->assertRowCount(2);
    foreach ($this->getAllMessageDigests() as $digest) {
      if ($digest->mid == $orphaned_message_id) {
        $this->fail('When a message is deleted its corresponding message digest is cleaned up.');
      }
    }

    // Delete one of the users and verify that the corresponding message digest
    // is cleaned up.
    $orphaned_user_id = $users[1]->id();
    $users[1]->delete();
    $this->assertRowCount(1);
    foreach ($this->getAllMessageDigests() as $digest) {
      if ($digest->receiver == $orphaned_user_id) {
        $this->fail('When a user is deleted its corresponding message digest is cleaned up.');
      }
    }
  }

  /**
   * Tests the message digest manager processing.
   */
  public function testManagerProcessing() {
    $this->digestManager->processDigests();
    $request_time = $this->container->get('datetime.time')->getRequestTime();
    $this->assertEquals($request_time, $this->container->get('state')->get('message_digest:daily_last_run'));
    $this->assertEquals($request_time, $this->container->get('state')->get('message_digest:weekly_last_run'));
    $this->assertEmpty($this->getMails());

    // Actually add some messages now, and reset last sent time.
    $last_run = strtotime('-8 days', $this->container->get('datetime.time')->getRequestTime());
    $this->container->get('state')->set('message_digest:weekly_last_run', $last_run);
    $template = $this->createMessageTemplate('foo', 'Foo', 'Foo, foo', $this->getMessageText());
    $account = $this->createUser();
    $messages = [];
    // Send 10 messages.
    foreach (range(1, 10) as $i) {
      $message = Message::create(['template' => $template->id()]);
      $message->setOwner($account);
      $message->save();
      $digest_notifier = $this->notifierManager->createInstance('message_digest:weekly', [], $message);
      $this->notifierSender->send($message, [], $digest_notifier->getPluginId());
      $messages[$i] = $message;
    }
    // Ensure cron function actually calls the process method.
    $this->container->get('cron')->run();
    $emails = $this->getMails();
    $this->assertEquals(1, count($emails));
    $this->assertMail('to', $account->getEmail());
    // Since the core mail service converts HTML to text, that should be be the
    // case here too.
    if (version_compare(\Drupal::VERSION, '10.2.2', '>')) {
      $expected_text = "<div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
 <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
 <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
 <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
 <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
 <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
 <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
 <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
 <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
 <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
 <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>

";
    }
    else {
      $expected_text = "<div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
  <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
  <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
  <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
  <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
  <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
  <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
  <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
  <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
  <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>
<hr />  <div>
    <div>
  Test subject
</div>

  </div>
  <div>
    <div>
  <div class=\"foo-bar\">Some sweet <a href=\"http://localhost/\">message</a>.
</div>

  </div>

";
    }
    $expected = MailFormatHelper::wrapMail(MailFormatHelper::htmlToText($expected_text));
    $email = reset($emails);
    $this->assertEquals($expected, $email['body']);
  }

  /**
   * Checks that message digest plugins can be correctly serialized.
   */
  public function testDigestSerialization() {
    foreach (['daily', 'weekly'] as $interval) {
      $plugin_id = "message_digest:$interval";
      $dummy = Message::create(['template' => 'foo']);
      /** @var \Drupal\message_digest\Plugin\Notifier\DigestInterface $notifier */
      $notifier = $this->notifierManager->createInstance($plugin_id, [], $dummy);
      /** @var \Drupal\message_digest\Plugin\Notifier\DigestInterface $unserialized_notifier */
      $unserialized_notifier = unserialize(serialize($notifier));
      $this->assertEquals($plugin_id, $unserialized_notifier->getPluginId());
    }
  }

  /**
   * Tests that a message is not sent if its owner has been deleted.
   */
  public function testOrphanedMessage() {
    // Create a test user.
    $user = $this->createUser();

    // Create a test message owned by the test user.
    $template = $this->createMessageTemplate('foo', 'Foo', 'Foo, foo', []);
    $message = Message::create(['template' => $template->id()]);
    /** @var \Drupal\message_digest\Plugin\Notifier\DigestInterface $digest_notifier */
    $digest_notifier = $this->notifierManager->createInstance('message_digest:daily', [], $message);
    $message->setOwner($user);
    $message->save();

    // Delete the user.
    $user->delete();

    // Deliver the message and send out the digests.
    $this->notifierSender->send($message, [], $digest_notifier->getPluginId());
    $this->sendDigests();

    // Check that no mails have been sent.
    $this->assertEmpty($this->getMails());
  }

  /**
   * Returns all rows from the message_digest table.
   *
   * @return array
   *   An array of all table rows, keyed by ID.
   *
   * @throws \Exception
   *   Thrown when the database connection is not available on the container.
   */
  protected function getAllMessageDigests() {
    return $this->container->get('database')
      ->select('message_digest', 'm')
      ->fields('m')
      ->execute()
      ->fetchAllAssoc('id');
  }

  /**
   * Checks that the message_digest table contains the expected number of rows.
   *
   * @param int $expected_count
   *   The expected number of rows.
   *
   * @throws \Exception
   *   Thrown when the database connection is not available on the container.
   */
  protected function assertRowCount($expected_count) {
    $this->assertEquals($expected_count, count($this->getAllMessageDigests()));
  }

}
