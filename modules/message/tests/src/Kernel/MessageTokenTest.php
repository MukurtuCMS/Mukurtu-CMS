<?php

namespace Drupal\Tests\message\Kernel;

use Drupal\Component\Utility\Html;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\user\Entity\User;

/**
 * Test the Message and tokens integration.
 *
 * @group Message
 */
class MessageTokenTest extends KernelTestBase {

  use MessageTemplateCreateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'message',
    'system',
    'token',
    'user',
  ];

  /**
   * The user object.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();

    $this->installEntitySchema('message');
    $this->installEntitySchema('user');
    $this->installConfig(['filter', 'system']);

    $this->user = User::create([
      'uid' => mt_rand(5, 10),
      'name' => $this->randomString(),
    ]);
    $this->user->save();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Test token replacement in a message template.
   */
  public function testTokens() {
    $message_template = $this->createMessageTemplate('dummy_message', 'Dummy message', '', ['[message:uid:entity:name]']);
    $message = Message::create(['template' => $message_template->id()])
      ->setOwnerId($this->user->id());

    $message->save();

    $this->assertEquals('<p>' . Html::escape($this->user->label()) . '</p>', (string) $message, 'The message rendered the author name.');
  }

  /**
   * Test deprecated token replacement in a message template.
   */
  public function testDeprecatedTokens() {
    $message_template = $this->createMessageTemplate('dummy_message', 'Dummy message', '', ['[message:author:name]']);
    $message = Message::create(['template' => $message_template->id()])
      ->setOwnerId($this->user->id());

    $message->save();

    $this->assertEquals('<p>' . Html::escape($this->user->label()) . '</p>', (string) $message, 'The message rendered the author name.');
  }

  /**
   * Test clearing unused tokens.
   */
  public function testTokenClearing() {
    // Clearing enabled.
    $token_options = ['token options' => ['clear' => TRUE, 'token replace' => TRUE]];
    $message_template = $this->createMessageTemplate('dummy_message', 'Dummy message', '', ['[message:uid:entity:name] [bogus:token]'], $token_options);
    $message = Message::create(['template' => $message_template->id()])
      ->setOwnerId($this->user->id());

    $message->save();

    $this->assertEquals('<p>' . Html::escape($this->user->label()) . ' </p>', (string) $message, 'The message rendered the author name and stripped unused tokens.');

    // Clearing disabled.
    $token_options = ['token options' => ['clear' => FALSE, 'token replace' => TRUE]];
    $message_template->setSettings($token_options);
    $message_template->save();

    $this->assertEquals('<p>' . Html::escape($this->user->label() . ' [bogus:token]') . '</p>', (string) $message, 'The message rendered the author name and did not strip the token.');
  }

  /**
   * Test the hard coded tokens.
   */
  public function testHardCodedTokens() {
    $random_text = $this->randomString();
    $token_messages = [
      'some text @{message:author} ' . $random_text,
      'some text %{message:author} ' . $random_text,
      'some text @{wrong:token} ' . $random_text,
    ];

    // The plain_text filter replaces line breaks, so those should be here too.
    $replaced_messages = [
      '<p>some text ' . Html::escape($this->user->label() . ' ' . $random_text) . "</p>\n",
      '<p>some text <em class="placeholder">' . Html::escape($this->user->label()) . '</em> ' . Html::escape($random_text) . "</p>\n",
      '<p>some text @{wrong:token} ' . Html::escape($random_text) . "</p>\n",
    ];

    // Create the message template.
    $message_template = $this->createMessageTemplate('dummy_message', 'Dummy message', '', $token_messages);

    // Assert the arguments.
    $original_message = Message::create([
      'template' => $message_template->id(),
      'uid' => $this->user->id(),
    ]);
    $this->assertTrue($original_message->getArguments() == FALSE, 'No message arguments exist prior to saving the message.');
    $original_message->save();
    // Make very, very sure the message arguments are not coming from the
    // object save created.
    $this->entityTypeManager->getStorage('message')->resetCache();
    $message = Message::load($original_message->id());
    $this->assertNotSame($message, $original_message);

    $arguments = $message->getArguments();
    $this->assertEquals(count($arguments), 2, 'Correct number of arguments added after saving the message.');

    // Assert message is rendered as expected.
    $this->assertEquals($replaced_messages, $message->getText(), 'The text rendered as expected.');
  }

}
