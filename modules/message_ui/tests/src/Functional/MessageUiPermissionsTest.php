<?php

namespace Drupal\Tests\message_ui\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\message\Entity\Message;
use Drupal\message_ui\MessagePermissions;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Testing the message access use case.
 *
 * @group Message UI
 */
class MessageUiPermissionsTest extends AbstractTestMessageUi {

  /**
   * The message access control handler.
   *
   * @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface
   */
  protected $accessHandler;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->accessHandler = \Drupal::entityTypeManager()->getAccessControlHandler('message');

    $this->account = $this->drupalCreateUser();

    // Load 'authenticated' user role.
    $this->rid = Role::load(RoleInterface::AUTHENTICATED_ID)->id();

    // Create Message template foo.
    $this->createMessageTemplate('foo', 'Dummy test', 'Example text.', ['Dummy message']);
  }

  /**
   * Test message_access use case.
   */
  public function testMessageUiPermissions() {
    // User login.
    $this->drupalLogin($this->account);

    // Set our create url.
    $create_url = '/message/add/foo';

    // Verify the user can't create the message.
    $this->drupalGet($create_url);

    // The user can't create a message.
    $this->assertSession()->statusCodeEquals(403);

    // Grant and check create permissions for a message.
    $this->grantMessageUiPermission('create');
    $this->drupalGet($create_url);

    // Check for valid response.
    $this->assertSession()->statusCodeEquals(200);

    // Create a message.
    $this->submitForm([], 'Create');

    // Create the message url.
    $msg_url = '/message/1';

    // Verify the user now can see the text.
    $this->grantMessageUiPermission('view');
    $this->drupalGet($msg_url);
    // The user can view a message.
    $this->assertSession()->statusCodeEquals(200);

    // Verify can't edit the message.
    $this->drupalGet($msg_url . '/edit');
    // The user can't edit a message.
    $this->assertSession()->statusCodeEquals(403);

    // Grant permission to the user.
    $this->grantMessageUiPermission('update');
    $this->drupalGet($msg_url . '/edit');
    // The user can't edit a message.
    $this->assertSession()->statusCodeEquals(200);

    // Verify the user can't delete the message.
    $this->drupalGet($msg_url . '/delete');
    // The user can't delete the message.
    $this->assertSession()->statusCodeEquals(403);

    // Grant the permission to the user.
    $this->grantMessageUiPermission('delete');

    $this->drupalGet($msg_url . '/delete');
    $this->submitForm([], 'Delete');

    // User did not have permission to the overview page - verify access
    // denied.
    $this->assertSession()->statusCodeEquals(403);

    user_role_grant_permissions($this->rid, ['overview messages']);
    $this->drupalGet('/admin/content/message');
    $this->assertSession()->statusCodeEquals(200);

    // Create a new user with the bypass access permission and verify the
    // bypass.
    $this->drupalLogout();
    $user = $this->drupalCreateUser(['bypass message access control']);

    // Verify the user can by pass the message access control.
    $this->drupalLogin($user);
    $this->drupalGet($create_url);

    // The user can bypass the message access control.
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Checking the alteration flow for other modules.
   */
  public function testMessageUiAccessHook() {
    // Install the message ui test dummy module.
    \Drupal::service('module_installer')->install(['message_ui_test']);

    $this->drupalLogin($this->account);

    // Setting up the operation and the expected value from the access callback.
    $permissions = [
      'create' => TRUE,
      'view' => TRUE,
      'delete' => FALSE,
      'update' => FALSE,
    ];

    // Get the message template and create an instance.
    $message_template = $this->loadMessageTemplate('foo');

    /** @var \Drupal\message\Entity\Message $message */
    $message = Message::create(['template' => $message_template->id()]);
    $message->setOwner($this->account);
    $message->save();

    foreach ($permissions as $op => $value) {
      // When the hook access of the dummy module will get in action it will
      // check which value need to return. If the access control function will
      // return the expected value then we know the hook got in action.
      if ($op == 'create') {
        $returned = $this->accessHandler->createAccess($message_template->id(), $this->account);
      }
      else {
        $message->{$op} = $value;
        $returned = $this->accessHandler->access($message, $op, $this->account);
      }

      $params = [
        '@operation' => $op,
        '@value' => $value,
        '@returned' => $returned,
      ];

      $this->assertEquals($value, $returned, new FormattableMarkup('The hook return @value for @operation when it need to return @returned', $params));
    }

    // Check dynamic template.
    $class = new MessagePermissions();
    $this->assertEquals(count($class->messageTemplatePermissions()), count($this->container->get('entity_type.manager')->getStorage('message_template')->loadMultiple()) * 4);
  }

}
