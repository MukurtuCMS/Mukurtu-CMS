<?php

namespace Drupal\Tests\message\Functional;

/**
 * Testing the listing functionality for the Message template entity.
 *
 * @group Message
 */
class MessageTemplateListTest extends MessageTestBase {

  /**
   * The user object.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Listing of messages.
   */
  public function testEntityTypeList() {
    $this->user = $this->drupalCreateUser(['administer message templates']);
    $this->drupalLogin($this->user);

    $this->drupalGet('admin/structure/message');
    $this->assertSession()->statusCodeEquals(200);
  }

}
