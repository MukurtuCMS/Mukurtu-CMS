<?php

namespace Drupal\Tests\message_subscribe_email\Kernel;

use Drupal\message\Entity\MessageTemplate;
use Drupal\Tests\message_subscribe\Kernel\MessageSubscribeTestBase;

/**
 * Test base for message subscribe email tests.
 */
abstract class MessageSubscribeEmailTestBase extends MessageSubscribeTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['message_subscribe_email'];

  /**
   * Flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Message template.
   *
   * @var \Drupal\message\MessageTemplateInterface
   */
  protected $messageTemplate;

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

    $this->installConfig(['message_subscribe_email']);
    $this->flagService = $this->container->get('flag');

    // Create node-type.
    $node_type = $this->createContentType();

    // Enable flags.
    $flags = $this->flagService->getAllFlags();

    $flag = $flags['subscribe_node'];
    $flag->set('bundles', [$node_type->id()]);
    $flag->enable();
    $flag->save();

    $flag = $flags['email_node'];
    $flag->set('bundles', [$node_type->id()]);
    $flag->enable();
    $flag->save();

    $permissions = [
      'flag subscribe_node',
      'unflag subscribe_node',
      'flag email_node',
      'unflag email_node',
    ];

    $this->users[1] = $this->createUser($permissions);
    $this->users[2] = $this->createUser($permissions);
    $this->users[3] = $this->createUser($permissions);

    // Create nodes.
    $settings = [];
    $settings['type'] = $node_type->id();
    $settings['uid'] = $this->users[1]->id();
    $this->nodes[1] = $this->createNode($settings);
    $this->nodes[2] = $this->createNode($settings);

    // Create a dummy message-type.
    $this->messageTemplate = MessageTemplate::create(['template' => 'foo']);
    $this->messageTemplate->save();

    $this->config('message_subscribe.settings')
      // Override default notifiers.
      ->set('default_notifiers', [])
      // Make sure we are notifying ourselves for this test.
      ->set('notify_own_actions', TRUE)
      ->save();

    $this->messageSubscribers = $this->container->get('message_subscribe.subscribers');
  }

}
