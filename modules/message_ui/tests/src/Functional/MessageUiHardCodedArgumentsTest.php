<?php

namespace Drupal\Tests\message_ui\Functional;

use Drupal\message\Entity\Message;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Testing the editing of the hard coded arguments.
 *
 * @group Message UI
 */
class MessageUiHardCodedArgumentsTest extends AbstractTestMessageUi {

  /**
   * The first user object.
   *
   * @var \Drupal\user\UserInterface
   */
  public $user1;

  /**
   * The second user object.
   *
   * @var \Drupal\user\UserInterface
   */
  public $user2;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->user1 = $this->drupalCreateUser();
    $this->user2 = $this->drupalCreateUser();
  }

  /**
   * Verify that a user can update the arguments for each instance.
   */
  public function testHardCoded() {
    // Load 'authenticated' user role.
    $role = Role::load(RoleInterface::AUTHENTICATED_ID);

    /** @var \Drupal\user\Entity\Role $role */
    user_role_grant_permissions($role->id(), ['bypass message access control']);

    $this->drupalLogin($this->user1);

    // Create Message Template of 'Dummy Test'.
    $this->createMessageTemplate(
      'dummy_message',
      'Dummy test',
      'This is a dummy message with a dummy message',
      ['@{message:author:name}']);

    // Get the message template and create an instance.
    $message_template = $this->loadMessageTemplate('dummy_message');
    /** @var \Drupal\message\Entity\Message $message */
    $message = Message::create(['template' => $message_template->id()]);
    $message->setOwner($this->user1);
    $message->save();

    // Verifying the message hard coded value is set to the user 1.
    $this->drupalGet('message/' . $message->id());

    // The message token is set to the user 1.
    $this->assertSession()->pageTextContains($this->user1->getAccountName());

    $message->setOwner($this->user2);
    $message->save();
    $this->drupalGet('message/' . $message->id());

    // The message token is set to the user 1 after editing the message.
    $this->assertSession()->pageTextNotContains($this->user2->getAccountName());

    // Update the message arguments automatically.
    $edit = [
      'name' => $this->user2->getAccountName() . ' (' . $this->user2->id() . ')',
      'replace_tokens' => 'update',
    ];
    $this->drupalGet('message/' . $message->id() . '/edit');

    $this->submitForm($edit, 'Update');

    // The message token as updated automatically.
    $this->assertSession()->pageTextContains($this->user2->getAccountName());

    // Update the message arguments manually.
    $edit = [
      'name' => $this->user2->label(),
      'replace_tokens' => 'update_manually',
      'edit-messageauthorname' => 'Dummy name',
    ];
    $this->drupalGet('message/' . $message->id() . '/edit');

    $this->submitForm($edit, 'Update');

    // The hard coded token was updated with a custom value.
    $this->assertSession()->pageTextContains('Dummy name');

  }

}
