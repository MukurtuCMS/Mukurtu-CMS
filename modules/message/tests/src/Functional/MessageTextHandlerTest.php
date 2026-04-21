<?php

namespace Drupal\Tests\message\Functional;

use Drupal\message\Entity\Message;

/**
 * Test the views text handler.
 *
 * @group Message
 */
class MessageTextHandlerTest extends MessageTestBase {

  /**
   * The user object.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter_test', 'message_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();

    $this->account = $this->drupalCreateUser(['overview messages']);
  }

  /**
   * Testing the deletion of messages in cron according to settings.
   */
  public function testTextHandler() {
    $text = [
      ['value' => 'Dummy text message', 'format' => 'filtered_html'],
    ];
    $this->createMessageTemplate('dummy_message', 'Dummy message', '', $text);
    Message::create(['template' => 'dummy_message'])->save();

    $this->drupalLogin($this->account);
    $this->drupalGet('admin/content/message');
    $this->assertSession()->pageTextContains('Dummy text message');

    $this->drupalGet('message-test');
    $this->assertSession()->pageTextContains('Dummy text message');
  }

  /**
   * Testing the message text is not empty if it contains html.
   */
  public function testHtmlTextHandler() {
    $text = [
      ['value' => htmlspecialchars('<p> Some HTML text</p>'), 'format' => 'full_html'],
    ];
    $this->createMessageTemplate('html_dummy_message', 'HTML Dummy message', '', $text);
    Message::create(['template' => 'html_dummy_message'])->save();

    $this->drupalLogin($this->account);
    $this->drupalGet('admin/content/message');
    $this->assertSession()->responseContains(htmlspecialchars('<p> Some HTML text</p>'));
  }

}
