<?php

namespace Drupal\Tests\message_subscribe\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message_notify\MessageNotifier;
use Drupal\message_subscribe\Subscribers;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Unit tests for the subscribers service.
 *
 * @group message_subscribe
 *
 * @coversDefaultClass \Drupal\message_subscribe\Subscribers
 */
class SubscribersTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Mock flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Mock config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Mock message notifier.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected $messageNotifier;

  /**
   * Mock module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Mock queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queue;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    require __DIR__ . '/../fixture_foo.module.php';

    // Setup default mock services. Individual tests can override as needed.
    $this->flagService = $this->prophesize(FlagServiceInterface::class)
      ->reveal();
    $this->configFactory = $this->prophesize(ConfigFactoryInterface::class)
      ->reveal();
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class)
      ->reveal();
    $this->messageNotifier = $this->prophesize(MessageNotifier::class)
      ->reveal();
    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class)
      ->reveal();
    $this->queue = $this->prophesize(QueueFactory::class)->reveal();
  }

  /**
   * Helper to generate a new subscriber service with mock services.
   *
   * @return \Drupal\message_subscribe\SubscribersInterface
   *   The subscribers service object.
   */
  protected function getSubscriberService() {
    return new Subscribers(
      $this->flagService,
      $this->configFactory,
      $this->entityTypeManager,
      $this->messageNotifier,
      $this->moduleHandler,
      $this->queue
    );
  }

  /**
   * Test the getFlags method.
   *
   * @covers ::getFlags
   */
  public function testGetFlags() {
    // Override config mock to allow access to the prefix variable.
    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('flag_prefix')->willReturn('blah');
    $config->get('debug_mode')->willReturn(FALSE);
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('message_subscribe.settings')->willReturn($config);
    $this->configFactory = $config_factory->reveal();

    // No flags.
    $flag_service = $this->prophesize(FlagServiceInterface::class);
    $flag_service->getAllFlags(NULL, NULL, NULL)->willReturn([]);
    $this->flagService = $flag_service->reveal();
    $subscribers = $this->getSubscriberService();
    $this->assertEquals([], $subscribers->getFlags());

    // No flags matching prefix.
    $flag = $this->prophesize(FlagInterface::class)->reveal();
    $flag_service = $this->prophesize(FlagServiceInterface::class);
    $flag_service->getAllFlags(NULL, NULL, NULL)->willReturn([
      'foo' => $flag,
      'bar' => $flag,
    ]);
    $this->flagService = $flag_service->reveal();
    $subscribers = $this->getSubscriberService();
    $this->assertEquals([], $subscribers->getFlags());

    // Matching prefix.
    $flag_service = $this->prophesize(FlagServiceInterface::class);
    $flag_service->getAllFlags(NULL, NULL, NULL)->willReturn(
      ['foo' => $flag, 'bar' => $flag, 'blah_foo' => $flag]
    );
    $this->flagService = $flag_service->reveal();
    $subscribers = $this->getSubscriberService();
    $this->assertEquals(['blah_foo' => $flag], $subscribers->getFlags());
  }

}
