<?php

namespace Drupal\Tests\message_digest\Kernel;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Base class for kernel tests for the message digest module.
 */
abstract class DigestTestBase extends KernelTestBase {

  use AssertMailTrait;
  use MessageTemplateCreateTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'filter_test',
    'message',
    'message_digest',
    'message_digest_test',
    'message_notify',
    'system',
    'user',
  ];

  /**
   * Message notifier plugin manager.
   *
   * @var \Drupal\message_notify\Plugin\Notifier\Manager
   */
  protected $notifierManager;

  /**
   * The message notify sender service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected $notifierSender;

  /**
   * The message digest manager.
   *
   * @var \Drupal\message_digest\DigestManagerInterface
   */
  protected $digestManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->notifierManager = $this->container->get('plugin.message_notify.notifier.manager');
    $this->notifierSender = $this->container->get('message_notify.sender');
    $this->digestManager = $this->container->get('message_digest.manager');
    $this->installEntitySchema('message');
    $this->installEntitySchema('user');
    $this->installSchema('message_digest', ['message_digest']);
    if (version_compare(\Drupal::VERSION, '10.2.0', '<')) {
      var_dump('installing schema');
      $this->installSchema('system', ['sequences']);
    }

    $this->installSchema('user', ['users_data']);
    $this->installConfig([
      'filter',
      'filter_test',
      'message_digest',
      'message_notify',
      'system',
    ]);

    // Create a dummy user to avoid UID 1 super privileges.
    $this->createUser();
  }

  /**
   * Helper method to process and deliver digests.
   */
  protected function sendDigests() {
    // Queue digests.
    $this->digestManager->processDigests();
    // Run queue.
    $this->container->get('cron')->run();
  }

}
