<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_gin_custom\EventSubscriber\LbOffCanvasMessagesSubscriber;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests that off-canvas Layout Builder dialog responses drain the messenger.
 *
 * @see \Drupal\mukurtu_gin_custom\EventSubscriber\LbOffCanvasMessagesSubscriber
 */
#[Group('mukurtu_gin_custom')]
class LbOffCanvasMessagesSubscriberTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'mukurtu_gin_custom',
  ];

  /**
   * Tests whether status_messages is injected for a given request.
   *
   * @dataProvider requestProvider
   */
  public function testOnView(string $route, ?string $wrapperFormat, array $build, array $expectedBuild): void {
    $request = Request::create('/does-not-matter');
    $request->attributes->set('_route', $route);
    if ($wrapperFormat !== NULL) {
      $request->query->set('_wrapper_format', $wrapperFormat);
    }

    $kernel = $this->createMock(HttpKernelInterface::class);
    $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $build);

    (new LbOffCanvasMessagesSubscriber())->onView($event);

    $this->assertSame($expectedBuild, $event->getControllerResult());
  }

  /**
   * Data provider covering the subscriber's gating conditions.
   */
  public static function requestProvider(): array {
    $injected = [
      'status_messages' => [
        '#type' => 'status_messages',
        '#display' => 'warning',
        '#weight' => -1000,
      ],
      '#sorted' => FALSE,
    ];

    return [
      'off-canvas dialog gets status_messages injected' => [
        'layout_builder.add_block',
        'drupal_dialog.off_canvas',
        [],
        $injected,
      ],
      'plain dialog wrapper format also gets status_messages injected' => [
        'layout_builder.add_block',
        'drupal_dialog',
        [],
        $injected,
      ],
      'other cancel-able form routes also get status_messages injected' => [
        'layout_builder.remove_block',
        'drupal_dialog.off_canvas',
        [],
        $injected,
      ],
      'full page view (no wrapper format) is left alone' => [
        'layout_builder.overrides.node.view',
        NULL,
        ['#markup' => 'canvas'],
        ['#markup' => 'canvas'],
      ],
      'non-dialog ajax wrapper format is left alone' => [
        'layout_builder.add_block',
        'drupal_ajax',
        [],
        [],
      ],
      'non-layout_builder route is left alone' => [
        'entity.node.canonical',
        'drupal_dialog.off_canvas',
        [],
        [],
      ],
      'status_messages already present is not duplicated' => [
        'layout_builder.add_block',
        'drupal_dialog.off_canvas',
        ['status_messages' => ['#type' => 'status_messages', '#already' => TRUE]],
        ['status_messages' => ['#type' => 'status_messages', '#already' => TRUE]],
      ],
      'block picker dialog is left alone' => [
        'layout_builder.choose_block',
        'drupal_dialog.off_canvas',
        [],
        [],
      ],
      'inline block picker dialog is left alone' => [
        'layout_builder.choose_inline_block',
        'drupal_dialog.off_canvas',
        [],
        [],
      ],
      'section picker dialog is left alone' => [
        'layout_builder.choose_section',
        'drupal_dialog.off_canvas',
        [],
        [],
      ],
    ];
  }

}
