<?php

namespace Drupal\Tests\message\Kernel\Plugin\QueueWorker;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\node\Entity\Node;
use Drupal\Tests\message\Kernel\MessageTemplateCreateTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests the multi-valued field check and delete after entity deletion.
 *
 * @coversDefaultClass \Drupal\message\Plugin\QueueWorker\MessageCheckAndDeleteWorker
 *
 * @group message
 */
class MessageCheckAndDeleteWorkerTest extends KernelTestBase {

  use MessageTemplateCreateTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'message', 'user', 'system', 'field', 'text', 'node'];

  /**
   * The queue worker to test.
   *
   * @var \Drupal\message\Plugin\QueueWorker\MessageCheckAndDeleteWorker
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function setUp():void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('message');

    $this->installSchema('node', ['node_access']);
    if (version_compare(\Drupal::VERSION, '10.2.0', '<')) {
      $this->installSchema('system', ['sequences']);
    }

    $this->installConfig(['filter', 'node']);
  }

  /**
   * Tests that no errors occur if data is empty.
   *
   * @covers ::processItem
   */
  public function testEmptyData() {
    // We should never have had an item queued in the first place if it was
    // empty, but just to be sure that the queue doesn't explode if we did end
    // up with this scenario.
    $this->createPlugin();
    $this->plugin->processItem(NULL);
    $this->plugin->processItem([]);
  }

  /**
   * Tests no errors occur when the messages given have already been deleted.
   *
   * @covers ::processItem
   */
  public function testAlreadyDeletedMessage() {
    $this->createPlugin();
    $this->plugin->processItem([1 => ['field_test']]);
    $this->plugin->processItem([
      8 => ['field_test'],
      100 => ['field_two', 'field_three'],
      245 => ['field_zebra'],
    ]);
  }

  /**
   * Tests that items are only deleted when appropriate.
   *
   * @covers ::processItem
   */
  public function testProcessItem() {
    // Create a message template.
    $template = strtolower($this->randomMachineName());
    $this->createMessageTemplate($template, 'Dummy message', 'This is a dummy message text', ['Dummy message template.']);

    // Add a node reference field to the template.
    $field_name = strtolower($this->randomMachineName());
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'message',
      'translatable' => FALSE,
      'entity_types' => [],
      'settings' => [
        'target_type' => 'node',
      ],
      'type' => 'entity_reference',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'label' => 'Entity reference field',
      'field_name' => $field_name,
      'entity_type' => 'message',
      'bundle' => $template,
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'node',
          ],
          'auto_create' => TRUE,
        ],
      ],
    ])->save();

    $this->createPlugin();

    // Create a message that still has at least one valid reference. This should
    // not be deleted.
    $node_type = $this->createContentType();
    $node = Node::create(['type' => $node_type->id(), 'title' => 'Zebras rule']);
    $node->save();
    $message = Message::create(['template' => $template]);
    $message->set($field_name, [1, 100]);
    $message->save();

    $this->plugin->processItem([$message->id() => [$field_name]]);
    $this->assertNotEmpty(Message::load($message->id()), 'Message exists after deleting one of two referenced nodes.');

    // If there are no valid references left, then the message should be
    // deleted.
    $node->delete();
    $this->plugin->processItem([$message->id() => [$field_name]]);
    $this->assertEmpty(Message::load($message->id()), 'Message deleted after deleting all referenced nodes.');
  }

  /**
   * Set the plugin with the given configuration.
   */
  protected function createPlugin() {
    $this->plugin = $this->container->get('plugin.manager.queue_worker')->createInstance('message_check_delete');
  }

}
