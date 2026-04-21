<?php

namespace Drupal\Tests\message_notify\Unit\Plugin\Notifier;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\message\MessageInterface;
use Drupal\message\MessageTemplateInterface;
use Drupal\message_notify\Exception\MessageNotifyException;
use Drupal\message_notify\Plugin\Notifier\Email;
use Drupal\user\UserInterface;
use Prophecy\Argument;

/**
 * Unit tests for the Email notifier.
 *
 * @coversDefaultClass \Drupal\message_notify\Plugin\Notifier\Email
 *
 * @group message_notify
 */
class EmailTest extends UnitTestCase {

  /**
   * Digest configuration.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * Mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mocked mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The rendering service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Plugin definition.
   *
   * @var array
   */
  protected $pluginDefinition = [
    'viewModes' => [
      'mail_subject',
      'mail_body',
    ],
  ];

  /**
   * Plugin ID.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class)->reveal();
    $this->mailManager = $this->prophesize(MailManagerInterface::class)->reveal();
    $renderer = $this->prophesize(RendererInterface::class);

    if (version_compare(\Drupal::VERSION, '10.3.0', '<')) {
      $renderer->renderPlain(Argument::any())->willReturn('foo bar');
    }
    else {
      $renderer->renderInIsolation(Argument::any())->willReturn('foo bar');
    }

    $this->renderer = $renderer->reveal();
    $this->pluginId = $this->randomMachineName();
    $this->pluginDefinition['title'] = $this->randomMachineName();
  }

  /**
   * Test the send method.
   *
   * @covers ::send
   * @covers ::setMessage
   */
  public function testSend() {
    // Mock a message object.
    $message = $this->prophesize(MessageInterface::class);
    $account = $this->prophesize(UserInterface::class);
    $account->id()->willReturn(42);
    $account->getEmail()->willReturn('foo@foo.com');
    $account->getPreferredLangcode()->willReturn(Language::LANGCODE_DEFAULT);
    $message->getOwner()->willReturn($account->reveal());
    $message->getOwnerId()->willReturn(42);
    $template = $this->prophesize(MessageTemplateInterface::class)->reveal();
    $message->getTemplate()->willReturn($template);
    $message->save()->willReturn(1);

    // Mock view builder.
    $view_builder = $this->prophesize(EntityViewBuilderInterface::class);
    $view_builder->view(Argument::cetera())->willReturn([]);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getViewBuilder('message')->willReturn($view_builder->reveal());
    $this->entityTypeManager = $entity_type_manager->reveal();

    $notifier = $this->getNotifier();
    $notifier->setMessage($message->reveal());
    $this->assertFalse($notifier->send());
  }

  /**
   * Test sending without a message.
   *
   * @covers ::send
   */
  public function testSendNoMessage() {
    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessage('No message is set for this notifier.');
    $notifier = $this->getNotifier();
    $notifier->send();
  }

  /**
   * Test sending without an email.
   *
   * @covers ::deliver
   */
  public function testSendNoEmail() {
    $this->expectException(MessageNotifyException::class);
    $this->expectExceptionMessage('It is not possible to send a Message to an anonymous owner. You may set an owner using ::setOwner() or pass a "mail" to the $options array.');
    $message = $this->prophesize(MessageInterface::class);
    $account = $this->prophesize(UserInterface::class)->reveal();
    $message->getOwner()->willReturn($account);
    $notifier = $this->getNotifier($message->reveal());
    $notifier->deliver([]);
  }

  /**
   * Constructs an email notifier.
   *
   * @param \Drupal\message\MessageInterface $message
   *   (optional) The message to construct the notifier with.
   *
   * @return \Drupal\message_notify\Plugin\Notifier\Email
   *   The email notifier plugin.
   */
  protected function getNotifier(?MessageInterface $message = NULL) {
    $logger = $this->prophesize(LoggerChannelInterface::class)->reveal();

    return new Email(
      $this->configuration,
      $this->pluginId,
      $this->pluginDefinition,
      $logger,
      $this->entityTypeManager,
      $this->renderer,
      $this->mailManager,
      $message,
    );
  }

}
