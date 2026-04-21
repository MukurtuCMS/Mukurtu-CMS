<?php

namespace Drupal\Tests\message_ui\Functional;

use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Testing the display of the preview.
 *
 * @group Message UI
 */
class MessageUiShowPreviewTest extends AbstractTestMessageUi {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->account = $this->drupalCreateUser();

    // Load 'authenticated' user role.
    $this->rid = Role::load(RoleInterface::AUTHENTICATED_ID)->id();

    // Create Message template foo.
    $this->createMessageTemplate('foo', 'Dummy test', 'Example text.', ['Dummy message']);

    // Grant and check create permissions for a message.
    $this->grantMessageUiPermission('create');

    // Don't show the text of the message.
    $this->configSet('show_preview', TRUE);
  }

  /**
   * Testing the displaying of the preview.
   */
  public function testMessageUiPreviewDisplaying() {

    // User login.
    $this->drupalLogin($this->account);

    // Verify the user can't create the message.
    $this->drupalGet('/message/add/foo');

    // Make sure we can see the message text.
    $this->assertSession()->pageTextContains('Dummy message');

    // Don't show the message text.
    $this->configSet('show_preview', FALSE);
    drupal_static_reset();

    // Verify the user can't create the message.
    $this->drupalGet('/message/add/foo');

    // Make sure we can see the message text.
    $this->assertSession()->pageTextNotContains('Dummy message');
  }

}
