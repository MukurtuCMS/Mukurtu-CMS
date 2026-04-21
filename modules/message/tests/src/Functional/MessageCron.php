<?php

namespace Drupal\Tests\message\Functional;

use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;

/**
 * Test message purging upon cron.
 *
 * @group Message
 */
class MessageCron extends MessageTestBase {

  /**
   * The user object.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The purge plugin manager.
   *
   * @var \Drupal\message\MessagePurgePluginManager
   */
  protected $purgeManager;

  /**
   * The cron service.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected $cron;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $timeService;

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();

    $this->purgeManager = $this->container->get('plugin.manager.message.purge');
    $this->account = $this->drupalCreateUser();
    $this->cron = $this->container->get('cron');
    $this->timeService = $this->container->get('datetime.time');
  }

  /**
   * Testing the deletion of messages in cron according to settings.
   */
  public function testPurge() {
    // Create a purge-able message template with max quota 2 and max days 0.
    $quota = $this->purgeManager->createInstance('quota', ['data' => ['quota' => 2]]);
    $days = $this->purgeManager->createInstance('days', ['data' => ['days' => 0]]);
    $settings = [
      'purge_override' => TRUE,
      'purge_methods' => [
        'quota' => $quota->getConfiguration(),
        'days' => $days->getConfiguration(),
      ],
    ];

    /** @var \Drupal\message\Entity\MessageTemplate $message_template */
    $message_template = MessageTemplate::create(['template' => 'template1']);
    $message_template
      ->setSettings($settings)
      ->save();

    // Make sure the purging data is actually saved.
    $message_template = MessageTemplate::load($message_template->id());
    $this->assertEquals($message_template->getSetting('purge_methods'), $settings['purge_methods'], 'Purge settings are stored in message template.');

    // Create a purge-able message template with max quota 1 and max days 2.
    $quota = $this->purgeManager->createInstance('quota', ['data' => ['quota' => 1]]);
    $days = $this->purgeManager->createInstance('days', ['data' => ['days' => 2]]);
    $settings = [
      'purge_override' => TRUE,
      'purge_methods' => [
        'quota' => $quota->getConfiguration(),
        'days' => $days->getConfiguration(),
      ],
    ];
    $message_template = MessageTemplate::create(['template' => 'template2']);
    $message_template
      ->setSettings($settings)
      ->save();

    // Create a non purge-able message (no purge methods enabled).
    $settings['purge_enabled'] = FALSE;
    $settings = [
      'purge_override' => TRUE,
      'purge_methods' => [],
    ];

    $message_template = MessageTemplate::create(['template' => 'template3']);
    $message_template
      ->setSettings($settings)
      ->save();

    // Create messages.
    for ($i = 0; $i < 4; $i++) {
      Message::Create(['template' => 'template1'])
        ->setCreatedTime($this->timeService->getRequestTime() - 3 * 86400)
        ->setOwnerId($this->account->id())
        ->save();
    }

    for ($i = 0; $i < 3; $i++) {
      Message::Create(['template' => 'template2'])
        ->setCreatedTime($this->timeService->getRequestTime() - 3 * 86400)
        ->setOwnerId($this->account->id())
        ->save();
    }

    for ($i = 0; $i < 3; $i++) {
      Message::Create(['template' => 'template3'])
        ->setCreatedTime($this->timeService->getRequestTime() - 3 * 86400)
        ->setOwnerId($this->account->id())
        ->save();
    }

    // Trigger message's hook_cron() as well as the queue processing.
    $this->cron->run();

    // Four template1 messages were created. The first two should have been
    // deleted.
    $this->assertEmpty(array_diff(Message::queryByTemplate('template1'), [3, 4]), 'Two messages deleted due to quota definition.');

    // All template2 messages should have been deleted.
    $this->assertEquals(Message::queryByTemplate('template2'), [], 'Three messages deleted due to age definition.');

    // template3 messages should not have been deleted.
    $remaining = [8, 9, 10];
    $this->assertEmpty(array_diff(Message::queryByTemplate('template3'), $remaining), 'Messages with disabled purging settings were not deleted.');
  }

  /**
   * Test global purge settings and overriding them.
   */
  public function testPurgeGlobalSettings() {
    // Set global purge settings.
    $quota = $this->purgeManager->createInstance('quota', ['data' => ['quota' => 1]]);
    $days = $this->purgeManager->createInstance('days', ['data' => ['days' => 2]]);
    $methods = [
      'quota' => $quota->getConfiguration(),
      'days' => $days->getConfiguration(),
    ];
    \Drupal::configFactory()->getEditable('message.settings')
      ->set('purge_enable', TRUE)
      ->set('purge_methods', $methods)
      ->save();

    MessageTemplate::create(['template' => 'template1'])->save();

    // Create an overriding template with no purge methods.
    $data = [
      'purge_override' => TRUE,
      'purge_methods' => [],
    ];

    MessageTemplate::create(['template' => 'template2'])
      ->setSettings($data)
      ->save();

    for ($i = 0; $i < 2; $i++) {
      Message::create(['template' => 'template1'])
        ->setCreatedTime(time() - 3 * 86400)
        ->setOwnerId($this->account->id())
        ->save();

      Message::create(['template' => 'template2'])
        ->setCreatedTime(time() - 3 * 86400)
        ->setOwnerId($this->account->id())
        ->save();
    }

    // Trigger message's hook_cron() as well as the queue processing.
    $this->cron->run();

    $this->assertCount(0, Message::queryByTemplate('template1'), 'All template1 messages deleted.');
    $this->assertCount(2, Message::queryByTemplate('template2'), 'Template2 messages were not deleted due to settings override.');
  }

}
