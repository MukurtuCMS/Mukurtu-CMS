<?php

namespace Drupal\Tests\message\Kernel\Plugin\MessagePurge;

use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;

/**
 * Integration tests for the 'quota' purge plugin.
 *
 * @coversDefaultClass \Drupal\message\Plugin\MessagePurge\Quota
 *
 * @group message
 */
class QuotaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['message', 'user'];

  /**
   * The plugin to test.
   *
   * @var \Drupal\message\Plugin\MessagePurge\Quota
   */
  protected $plugin;

  /**
   * A message template.
   *
   * @var \Drupal\message\MessageTemplateInterface
   */
  protected $template;

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();

    $this->installEntitySchema('message');

    $this->template = MessageTemplate::create([
      'template' => 'foo',
    ]);
    $this->template->save();
  }

  /**
   * Tests the fetch method.
   *
   * @covers ::fetch
   */
  public function testFetch() {
    $configuration = [
      'weight' => 4,
      'data' => [
        'quota' => 10,
      ],
    ];
    $this->createPlugin($configuration);

    // No IDs should return if there are no messages.
    $this->assertEquals([], $this->plugin->fetch($this->template));

    // Add some message using this template.
    foreach (range(1, 5) as $i) {
      $message = Message::create(['template' => $this->template->id()]);
      $message->save();
    }

    // None should be returned as there are less than 10.
    $this->createPlugin($configuration);
    $this->assertEquals([], $this->plugin->fetch($this->template));

    // Set quota to 3.
    $configuration['data']['quota'] = 3;
    $this->createPlugin($configuration);
    $this->assertEquals([1 => 1, 2 => 2], $this->plugin->fetch($this->template));
  }

  /**
   * Set the plugin with the given configuration.
   *
   * @param array $configuration
   *   The plugin configuration.
   */
  protected function createPlugin(array $configuration) {
    $this->plugin = $this->container->get('plugin.manager.message.purge')->createInstance('quota', $configuration);
  }

}
