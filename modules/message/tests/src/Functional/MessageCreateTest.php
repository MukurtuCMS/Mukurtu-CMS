<?php

namespace Drupal\Tests\message\Functional;

use Drupal\message\Entity\Message;

/**
 * Tests message creation and default values.
 *
 * @group Message
 */
class MessageCreateTest extends MessageTestBase {

  /**
   * The user object.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();

    $this->user = $this->drupalCreateUser();
  }

  /**
   * Tests if message create sets the default uid to currently logged in user.
   */
  public function testMessageCreateDefaultValues() {
    // Login our user to create message.
    $this->drupalLogin($this->user);

    $template = 'dummy_message';
    // Create message to be rendered without setting owner.
    $message_template = $this->createMessageTemplate($template, 'Dummy message', '', ['[message:author:name]']);
    $message = Message::create(['template' => $message_template->id()]);

    $message->save();

    /** @var \Drupal\message\Entity\Message $message */
    $this->assertEquals($this->user->id(), $message->getOwnerId(), 'The default value for uid was set correctly.');
  }

}
