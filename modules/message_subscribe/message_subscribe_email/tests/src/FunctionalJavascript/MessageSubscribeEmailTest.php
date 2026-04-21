<?php

namespace Drupal\Tests\message_subscribe_email\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Javascript tests for message subscribe email.
 *
 * @group message_subscribe_email
 */
class MessageSubscribeEmailTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['message_subscribe_email', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Nodes to test with.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes;

  /**
   * Users to test with.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $users;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Add some nodes.
    $type = $this->createContentType();
    foreach (range(1, 3) as $i) {
      $this->nodes[$i] = $this->drupalCreateNode(['type' => $type->id()]);
    }

    // Add some users.
    $permissions = [
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag email_node',
      'unflag email_node',
    ];

    $this->users[1] = $this->createUser($permissions);
    $this->users[2] = $this->createUser($permissions);
    $this->users[3] = $this->createUser($permissions);

    $this->flagService = $this->container->get('flag');

    // Enable node flags.
    $flags = $this->flagService->getAllFlags('node');
    $flags['subscribe_node']->enable();
    $flags['subscribe_node']->save();
    $flags['email_node']->enable();
    $flags['email_node']->save();
  }

  /**
   * Tests the flag/unflag UI.
   */
  public function testUi() {
    $flag = $this->flagService->getFlagById('subscribe_node');
    $this->drupalLogin($this->users[2]);
    $this->drupalGet($this->nodes[2]->toUrl());

    // Subscribe to the node.
    $this->clickLink('Subscribe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertTrue((bool) $this->flagService->getFlagging($flag, $this->nodes[2], $this->users[2]));

    // Unsubscribe from the node.
    $this->clickLink('Unsubscribe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertFalse((bool) $this->flagService->getFlagging($flag, $this->nodes[2], $this->users[2]));

    // Subscribe again!
    $this->clickLink('Subscribe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertTrue((bool) $this->flagService->getFlagging($flag, $this->nodes[2], $this->users[2]));
  }

}
