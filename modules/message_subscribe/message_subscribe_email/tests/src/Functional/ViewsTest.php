<?php

namespace Drupal\Tests\message_subscribe_email\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests the views provided by this module for the UI.
 *
 * @group message_subscribe_email
 */
class ViewsTest extends BrowserTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['message_subscribe_email', 'node', 'system'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The message subscription service.
   *
   * @var \Drupal\message_subscribe\SubscribersInterface
   */
  protected $messageSubscribers;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->flagService = $this->container->get('flag');
    $this->messageSubscribers = $this->container->get('message_subscribe.subscribers');

    // Enable flags.
    foreach (['node', 'term', 'user'] as $entity_type) {
      foreach (['email', 'subscribe'] as $flag_type) {
        if ($flag = $this->flagService->getFlagById($flag_type . '_' . $entity_type)) {
          $flag->enable();
          $flag->save();
        }
      }
    }

    // Set the view name explicitly since flags can be created after installs.
    foreach ($this->messageSubscribers->getFlags() as $flag_name => $flag) {
      $expected = $flag_name . '_email:default';
      $flag->setThirdPartySetting('message_subscribe_ui', 'view_name', $expected);
      $flag->save();
    }
  }

  /**
   * Tests that the views are properly used in the UI.
   */
  public function testViews() {
    // Verify flags are properly using the email views.
    foreach ($this->messageSubscribers->getFlags() as $flag_name => $flag) {
      $expected = $flag_name . '_email:default';
      $this->assertEquals($expected, $flag->getThirdPartySetting('message_subscribe_ui', 'view_name'));
    }

    // Add a few users.
    $permissions = [
      'access content',
      'access user profiles',
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag email_node',
      'unflag email_node',
      'flag subscribe_user',
      'unflag subscribe_user',
      'flag email_user',
      'unflag email_user',
    ];
    foreach (range(1, 3) as $i) {
      $users[$i] = $this->drupalCreateUser($permissions);
    }

    // Add an admin user.
    $permissions[] = 'administer message subscribe';
    $admin = $this->drupalCreateUser($permissions);
    $this->drupalLogin($admin);

    foreach ($users as $user) {
      // Default should be to receive email.
      $this->assertTrue((bool) $user->message_subscribe_email->value, 'User defaults to getting email subscriptions');

      // Admin can visit all subscriptions.
      $this->drupalGet('user/' . $user->id() . '/message-subscribe');
      $this->assertSession()->statusCodeEquals(200);
      $this->drupalGet('user/' . $user->id() . '/message-subscribe/subscribe_node');
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->pageTextContains('You are not subscribed to any items.');
    }

    // Add a node, and subscribe user 2 to that node.
    $this->drupalLogin($users[2]);
    $type = $this->createContentType();
    $node = $this->createNode(['type' => $type->id()]);
    $flag = $this->flagService->getFlagById('subscribe_node');
    $this->flagService->flag($flag, $node, $users[2]);
    $this->drupalGet('user/' . $users[2]->id() . '/message-subscribe/subscribe_node');
    // The node title (label) appears on the list of subscribed content.
    $this->assertSession()->pageTextContains($node->label());
    $this->assertSession()->pageTextContains("Don't send email");

    // Subscribe user 2 to user 1.
    $flag = $this->flagService->getFlagById('subscribe_user');
    $this->flagService->flag($flag, $users[1], $users[2]);
    $this->drupalGet('user/' . $users[2]->id() . '/message-subscribe/subscribe_user');
    $this->assertSession()->pageTextContains($users[1]->label());
    $this->assertSession()->pageTextContains("Don't send email");

    // Login user 3.
    $this->drupalLogin($users[3]);
    $this->drupalGet('user/' . $users[3]->id() . '/message-subscribe');
    $this->assertSession()->pageTextContains('You are not subscribed to any items.');
    $flag = $this->flagService->getFlagById('subscribe_node');
    $this->flagService->flag($flag, $node, $users[3]);
    $this->drupalGet('user/' . $users[3]->id() . '/message-subscribe');
    // The node title (label) appears on the list of subscribed content.
    $this->assertSession()->pageTextContains($node->label());
  }

}
