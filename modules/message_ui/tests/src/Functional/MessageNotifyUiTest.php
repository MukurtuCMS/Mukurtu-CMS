<?php

namespace Drupal\Tests\message_ui\Functional;

/**
 * Testing the message notify button.
 *
 * @group Message UI
 */
class MessageNotifyUiTest extends AbstractTestMessageUi {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['message', 'message_notify_ui'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->account = $this->drupalCreateUser([
      'send message through the ui',
      'overview messages',
    ]);

    // Create Message template foo.
    $this->createMessageTemplate('foo', 'Dummy test', 'Example text.', ['Dummy message']);
  }

  /**
   * Testing the displaying of the preview.
   */
  public function testMessageNotifyUi() {

    // User login.
    $this->drupalLogin($this->account);

    // Create a message.
    $message = $this->container->get('entity_type.manager')->getStorage('message')->create([
      'template' => 'foo',
    ]);
    $message->save();

    // Go to the page of notify page.
    $edit = [
      'use_custom' => TRUE,
      'email' => 'foo@gmail.com',
    ];

    $this->drupalGet('message/' . $message->id() . '/notify');
    $this->submitForm($edit, 'Notify');

    $this->assertSession()->pageTextContains('The email sent successfully.');
  }

}
