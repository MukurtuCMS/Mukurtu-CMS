<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\message\Entity\Message;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;

/**
 * Tests mukurtu_submissions_send_email_notifications() - the notify_emails
 * send path, added once PR #1760 landed a reusable "render a message and
 * mail it to a raw address" pattern this module didn't have before (see
 * mukurtu_notifications_process_digest() on main for the origin of the
 * pattern this mirrors).
 *
 * Exercises the helper directly rather than the full
 * mukurtu_submissions_node_insert() hook - that hook's OTHER half
 * (notify_uids, via message_subscribe/DeliveryCandidate) needs the much
 * heavier message_subscribe/mukurtu_notifications stack
 * MukurtuSubmissionsKernelTestBase deliberately excludes; this send path
 * only needs 'message' itself.
 *
 * @group mukurtu_submissions
 */
class NotifyEmailNotificationTest extends MukurtuSubmissionsKernelTestBase {

  use AssertMailTrait;
  use MessageTemplateCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'message',
    'message_notify',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // message_notify's own config/install ships the mail_subject/mail_body
    // entity_view_mode entities the display below depends on.
    $this->installConfig(['filter', 'system', 'message_notify']);
    $this->installEntitySchema('message');

    // Two deltas (subject, body) + matching mail_subject/mail_body
    // displays - mirrors mukurtu_submissions_update_40011()'s real
    // production setup for mukurtu_submission_received, so this test
    // exercises the same "isolate one delta per mode" mechanism
    // send_email_notifications() now depends on.
    $this->createMessageTemplate('test_submission_received', 'Test', 'Test template', [
      'Test subject line.',
      '<p>A new submission was received.</p>',
    ]);

    $repository = \Drupal::service('entity_display.repository');
    $subject_display = $repository->getViewDisplay('message', 'test_submission_received', 'mail_subject');
    $subject_display->setComponent('partial_0', ['region' => 'content', 'weight' => 0]);
    $subject_display->removeComponent('partial_1');
    $subject_display->save();

    $body_display = $repository->getViewDisplay('message', 'test_submission_received', 'mail_body');
    $body_display->setComponent('partial_1', ['region' => 'content', 'weight' => 0]);
    $body_display->removeComponent('partial_0');
    $body_display->save();
  }

  protected function createTestMessage(): Message {
    $message = Message::create(['template' => 'test_submission_received']);
    $message->save();
    return $message;
  }

  public function testValidAddressesReceiveMail(): void {
    $message = $this->createTestMessage();

    mukurtu_submissions_send_email_notifications($message, ['reviewer@example.com'], \Drupal::service('email.validator'));

    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $this->assertEquals('reviewer@example.com', $mails[0]['to']);
    $this->assertStringContainsString('Test subject line.', $mails[0]['subject']);
    $this->assertStringContainsString('A new submission was received.', (string) $mails[0]['body']);
  }

  public function testInvalidAddressesAreSkipped(): void {
    $message = $this->createTestMessage();

    mukurtu_submissions_send_email_notifications($message, ['not-an-email'], \Drupal::service('email.validator'));

    $this->assertCount(0, $this->getMails());
  }

  public function testMultipleAddressesEachReceiveMail(): void {
    $message = $this->createTestMessage();

    mukurtu_submissions_send_email_notifications(
      $message,
      ['reviewer-one@example.com', 'not-an-email', 'reviewer-two@example.com'],
      \Drupal::service('email.validator')
    );

    $mails = $this->getMails();
    $this->assertCount(2, $mails);
    $this->assertEqualsCanonicalizing(
      ['reviewer-one@example.com', 'reviewer-two@example.com'],
      array_column($mails, 'to')
    );
  }

}
