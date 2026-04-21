<?php

namespace Drupal\Tests\message_notify\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\message_notify\Exception\MessageNotifyException;
use Drupal\user\Entity\User;

/**
 * Test the Message notifier plugins handling.
 *
 * @group message_notify
 */
class MessageNotifyTest extends KernelTestBase {

  /**
   * Testing message template.
   *
   * @var \Drupal\message\MessageTemplateInterface
   */
  protected $messageTemplate;

  /**
   * The message notification service.
   *
   * @var \Drupal\message_notify\MessageNotifyInterface
   */
  protected $messageNotify;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'message_notify_test',
    'message_notify',
    'message',
    'user',
    'system',
    'field',
    'text',
    'filter',
    'filter_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installConfig([
      'message',
      'message_notify',
      'message_notify_test',
      'filter_test',
    ]);
    $this->installSchema('system', ['sequences']);

    $this->messageTemplate = MessageTemplate::load('message_notify_test');

    $this->messageNotify = $this->container->get('message_notify.sender');
  }

  /**
   * Test send method.
   *
   * Check the correct info is sent to delivery.
   */
  public function testDeliver() {
    $message = Message::create(['template' => $this->messageTemplate->id()]);
    $message->message_text_another = 'another field';
    $this->messageNotify->send($message, [], 'test');

    // The test notifier added the output to the message.
    $output = $message->output;
    $text = $message->getText();
    $this->assertStringContainsString((string) $text[1], (string) $output['foo']);
    $this->assertStringContainsString('another field', (string) $output['foo']);
    $this->assertStringContainsString((string) $text[0], (string) $output['bar']);
    $this->assertStringNotContainsString('another field', (string) $output['bar']);
  }

  /**
   * Test Message save on delivery.
   */
  public function testPostSendMessageSave() {
    $account = User::create(['name' => $this->randomMachineName()]);
    $account->save();
    $message = Message::create(['template' => $this->messageTemplate->id(), 'uid' => $account->id()]);
    $message->fail = FALSE;
    $this->messageNotify->send($message, [], 'test');
    $this->assertNotNull($message->id(), 'Message saved after successful delivery.');

    $message = Message::create(['template' => $this->messageTemplate->id(), 'uid' => $account->id()]);
    $message->fail = TRUE;
    $this->messageNotify->send($message, [], 'test');
    $this->assertNull($message->id(), 'Message not saved after unsuccessful delivery.');

    // Disable saving Message on delivery.
    $options = [
      'save on fail' => FALSE,
      'save on success' => FALSE,
    ];

    $message = Message::create(['template' => $this->messageTemplate->id(), 'uid' => $account->id()]);
    // @todo See above.
    $message->fail = FALSE;
    $this->messageNotify->send($message, $options, 'test');
    $this->assertTrue($message->isNew(), 'Message not saved after successful delivery.');

    $message = Message::create(['template' => $this->messageTemplate->id(), 'uid' => $account->id()]);
    $message->fail = TRUE;
    $this->messageNotify->send($message, $options, 'test');
    $this->assertTrue($message->isNew(), 'Message not saved after unsuccessful delivery.');
  }

  /**
   * Test populating the rendered output to fields.
   *
   * @dataProvider providerPostSendRenderedField
   */
  public function testPostSendRenderedField(array $options, bool $exception) {
    $this->attachRenderedFields();

    $message = Message::create(['template' => $this->messageTemplate->id()]);

    if ($exception) {
      $this->expectException(MessageNotifyException::class);
    }

    $this->messageNotify->send($message, $options, 'test');

    if (!$exception) {
      $this->assertArrayHasKey('rendered fields', $options);
      $fields = array_values($options['rendered fields']);
      $this->assertNotEmpty($fields);
      foreach ($fields as $field) {
        $this->assertNotEmpty($message->{$field}->value, 'The message field ' . $field . ' was not rendered.');
      }
    }
  }

  /**
   * Data provider for ::testPostSendRenderedField.
   *
   * @return array
   *   The test cases.
   */
  public static function providerPostSendRenderedField():array {
    $cases = [];

    $cases['plain fields'] = [
      [
        'rendered fields' => [
          'foo' => 'rendered_foo',
          'bar' => 'rendered_bar',
        ],
      ],
      FALSE,
    ];

    $cases['field with text-processing'] = [
      [
        'rendered fields' => [
          'foo' => 'rendered_baz',
          'bar' => 'rendered_bar',
        ],
      ],
      FALSE,
    ];

    $cases['missing view mode key in rendered fields'] = [
      [
        'rendered fields' => [
          'foo' => 'rendered_foo',
          // No "bar" field.
        ],
      ],
      TRUE,
    ];

    $cases['invalid field name'] = [
      [
        'rendered fields' => [
          'foo' => 'wrong_field',
          'bar' => 'rendered_bar',
        ],
      ],
      TRUE,
    ];

    return $cases;
  }

  /**
   * Helper function to attach rendered fields.
   *
   * @see MessageNotifyTest::testPostSendRenderedField()
   */
  protected function attachRenderedFields() {
    foreach (['rendered_foo', 'rendered_bar', 'rendered_baz'] as $field_name) {
      // Use formatted text for `baz`, plain for others.
      $config = [
        'field_name' => $field_name,
        'type' => 'string_long',
        'entity_type' => 'message',
      ];
      if ($field_name == 'rendered_baz') {
        $config['type'] = 'text_long';
      }
      $field_storage = FieldStorageConfig::create($config);
      $field_storage->save();

      $field = FieldConfig::create([
        'field_name' => $field_name,
        'bundle' => $this->messageTemplate->id(),
        'entity_type' => 'message',
        'label' => $field_name,
      ]);

      $field->save();
    }
  }

}
