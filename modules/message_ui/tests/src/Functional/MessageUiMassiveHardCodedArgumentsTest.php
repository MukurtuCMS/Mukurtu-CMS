<?php

namespace Drupal\Tests\message_ui\Functional;

use Drupal\message\Entity\Message;

/**
 * Testing the update of the hard coded arguments in massive way.
 *
 * @group Message UI
 */
class MessageUiMassiveHardCodedArgumentsTest extends AbstractTestMessageUi {

  /**
   * The message template object.
   *
   * @var \Drupal\message\MessageTemplateInterface
   */
  protected $messageTemplate;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->account = $this->drupalCreateUser();
  }

  /**
   * Test removal of added arguments.
   */
  public function testRemoveAddingArguments() {
    // Create Message Template of 'Dummy Test.
    $this->messageTemplate = $this->createMessageTemplate('dummy_message', 'Dummy test', 'This is a dummy message', ['@{message:author:name} @{message:author:mail}']);

    // Set a queue worker for the update arguments when updating a message
    // template.
    $this->configSet('update_tokens.update_tokens', TRUE);
    $this->configSet('update_tokens.how_to_update', 'update_with_item');

    /** @var \Drupal\message\Entity\Message $message */
    $message = Message::create(['template' => $this->messageTemplate->id()]);

    $message
      ->setOwner($this->account)
      ->save();

    $original_arguments = $message->getArguments();

    // Update message instance when removing a hard coded argument.
    $this->configSet('update_tokens.how_to_act', 'update_when_removed');

    // Set message text.
    $this->messageTemplate->set('text', [
      [
        'value' => '@{message:author:name}.',
        'format' => filter_default_format(),
      ],
    ]);
    $this->messageTemplate->save();

    // Fire the queue worker.
    $queue = \Drupal::queue('message_ui_arguments');
    $queue->createQueue();
    $item = $queue->claimItem();
    $queue->createItem($item->data);
    $this->container->get('cron')->run();

    // Verify the arguments has changed.
    $message = Message::load($message->id());

    $this->assertTrue($original_arguments != $message->getArguments(), 'The message arguments has changed during the queue worker work.');

    // Creating a new message and hard coded arguments.
    $message = Message::create(['template' => $this->messageTemplate->id()]);
    $message->setOwner($this->account)->save();

    $original_arguments = $message->getArguments();

    // Process the message instance when adding hard coded arguments.
    $this->configSet('update_tokens.how_to_act', 'update_when_added');

    $message_template = $this->loadMessageTemplate('dummy_message');
    $message_template->set('text', ['@{message:user:name}.']);
    $message_template->save();

    // Fire the queue worker.
    $queue = \Drupal::queue('message_ui_arguments');
    $item = $queue->claimItem();

    $queue->createItem($item->data);

    // Verify the arguments has changed.
    $message = Message::load($message->id());
    $this->assertTrue($original_arguments == $message->getArguments(), 'The message arguments has changed during the queue worker work.');
  }

}
