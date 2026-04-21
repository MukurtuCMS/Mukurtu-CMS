<?php

namespace Drupal\Tests\message_digest\Kernel;

use Drupal\message\Entity\Message;

/**
 * Tests basic token integration for formmatted message digests.
 *
 * @group message_digest
 *
 * @requires module token
 */
class TokenTest extends DigestTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['token'];

  /**
   * Tests token replacement when rendering digests.
   */
  public function testTokenReplacement() {
    // Send several messages.
    $template = $this->createMessageTemplate('foo', 'Foo', 'Foo, foo', [
      'Dummy title',
      'Hello [message:author:display-name]!',
    ]);
    $original_message_author = $this->createUser();
    $actual_recipient = $this->createUser();
    $messages = [];

    foreach (range(1, 3) as $i) {
      $messages[$i] = Message::create(['template' => $template->id()]);
      $messages[$i]->setOwner($original_message_author);
      // Message is saved with the original author, but sent to a new recipient.
      $messages[$i]->save();
      $messages[$i]->setOwner($actual_recipient);
      $this->notifierSender->send($messages[$i], [], 'message_digest:daily');
    }

    // Send the digest.
    $this->sendDigests();
    $this->assertMail('to', $actual_recipient->getEmail());

    // Verify that the token in the template is the proper user.
    $email = $this->getMails();
    $this->assertTrue(strpos($email[0]['body'], $actual_recipient->getDisplayName()) !== FALSE);
    $this->assertTrue(strpos($email[0]['body'], 'Dummy title') !== FALSE);
  }

  /**
   * Tests that the email subject (or other view modes) can be removed.
   */
  public function testTokenRemoveEmailSubject() {
    // Remove the email subject from the body.
    // @see message_digest_test_message_digest_view_mode_alter()
    \Drupal::state()->set('message_digest_test_remove_view_mode', 'mail_subject');

    // Send several messages.
    $template = $this->createMessageTemplate('foo', 'Foo', 'Foo, foo', [
      'Dummy title',
      'Hello [message:author:display-name]!',
    ]);
    $original_message_author = $this->createUser();
    $actual_recipient = $this->createUser();
    $messages = [];

    foreach (range(1, 3) as $i) {
      $messages[$i] = Message::create(['template' => $template->id()]);
      $messages[$i]->setOwner($original_message_author);
      // Message is saved with the original author, but sent to a new recipient.
      $messages[$i]->save();
      $messages[$i]->setOwner($actual_recipient);
      $this->notifierSender->send($messages[$i], [], 'message_digest:daily');
    }

    // Send the digest.
    $this->sendDigests();
    $this->assertMail('to', $actual_recipient->getEmail());

    // Verify that the token in the template is the proper user.
    $email = $this->getMails();
    $this->assertTrue(strpos($email[0]['body'], $actual_recipient->getDisplayName()) !== FALSE);
    // Email subject should not appear in the body.
    $this->assertSame(FALSE, strpos($email[0]['body'], 'Dummy title'));
  }

}
