<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_gin_custom\EventSubscriber\ChooseBlockMergeSubscriber;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Route;

/**
 * Tests merging the "Add a block" list into "Choose a block".
 *
 * @see \Drupal\mukurtu_gin_custom\EventSubscriber\ChooseBlockMergeSubscriber
 */
#[Group('mukurtu_gin_custom')]
class ChooseBlockMergeSubscriberTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * The arguments the inline block controller was last called with.
   */
  protected ?array $inlineArguments = NULL;

  /**
   * Builds the subscriber with a stub inline block controller.
   *
   * @param array $inline_build
   *   What the choose_inline_block controller returns.
   */
  protected function subscriber(array $inline_build): ChooseBlockMergeSubscriber {
    $route_provider = $this->createMock(RouteProviderInterface::class);
    $route_provider->method('getRouteByName')
      ->with('layout_builder.choose_inline_block')
      ->willReturn(new Route('/inline', ['_controller' => 'restrictions_controller::inlineBlockList']));

    // The subscriber must use the route's controller, which Layout Builder
    // Restrictions replaces, not core's.
    $resolver = $this->createMock(ControllerResolverInterface::class);
    $resolver->method('getControllerFromDefinition')
      ->with('restrictions_controller::inlineBlockList')
      ->willReturn(function (...$arguments) use ($inline_build) {
        $this->inlineArguments = $arguments;
        return $inline_build;
      });

    return new ChooseBlockMergeSubscriber($route_provider, $resolver, $this->container->get('string_translation'));
  }

  /**
   * Returns an inline block list as inlineBlockList() builds it.
   */
  protected function inlineBuild(): array {
    return [
      'links' => [
        '#theme' => 'links',
        '#attributes' => ['class' => ['inline-block-list']],
        '#links' => [
          [
            'title' => 'Text',
            'attributes' => ['class' => ['js-layout-builder-block-link', 'inline-block-list__item']],
          ],
        ],
      ],
      'back_button' => ['#type' => 'link'],
    ];
  }

  /**
   * Returns a "Choose a block" build as core's controller makes it.
   */
  protected function chooseBuild(bool $access = TRUE): array {
    return [
      'add_block' => ['#type' => 'link', '#access' => $access],
      'filter' => ['#type' => 'search'],
      'block_categories' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['block-categories']],
        'Lists (Views)' => ['#type' => 'details', '#title' => 'Lists (Views)'],
      ],
    ];
  }

  /**
   * Dispatches a view event and returns the resulting build.
   */
  protected function dispatch(ChooseBlockMergeSubscriber $subscriber, array $build, string $route = 'layout_builder.choose_block'): array {
    $request = new Request();
    $request->attributes->set('_route', $route);
    $request->attributes->set('section_storage', 'storage');
    $request->attributes->set('delta', '2');
    $request->attributes->set('region', 'content');
    $event = new ViewEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $build);

    $subscriber->onView($event);
    return $event->getControllerResult();
  }

  /**
   * The inline list becomes the first group and the button goes away.
   */
  public function testMergesInlineBlocksAsFirstGroup(): void {
    $result = $this->dispatch($this->subscriber($this->inlineBuild()), $this->chooseBuild());

    $this->assertSame(['storage', 2, 'content'], $this->inlineArguments);
    $this->assertFalse($result['add_block']['#access']);

    $categories = $result['block_categories'];
    $this->assertSame('container', $categories['#type']);
    $groups = array_values(array_filter(array_keys($categories), static fn($key) => !str_starts_with($key, '#')));
    $this->assertSame(['mukurtu_content_blocks', 'Lists (Views)'], $groups);

    $group = $categories['mukurtu_content_blocks'];
    $this->assertSame('details', $group['#type']);
    $this->assertSame('Content blocks', (string) $group['#title']);
    $this->assertContains('js-layout-builder-category', $group['#attributes']['class']);

    // The links keep core's filter class and lose the second page's styling.
    $this->assertSame([], $group['links']['#attributes']['class']);
    $this->assertSame(['js-layout-builder-block-link'], $group['links']['#links'][0]['attributes']['class']);
  }

  /**
   * Users who cannot create content blocks see the dialog unchanged.
   */
  public function testLeavesDialogAloneWithoutCreateAccess(): void {
    $build = $this->chooseBuild(FALSE);

    $this->assertSame($build, $this->dispatch($this->subscriber($this->inlineBuild()), $build));
    $this->assertNull($this->inlineArguments);
  }

  /**
   * With no inline blocks to offer, the button and dialog stay as they are.
   */
  public function testLeavesDialogAloneWithNoInlineBlocks(): void {
    $build = $this->chooseBuild();

    $this->assertSame($build, $this->dispatch($this->subscriber([]), $build));
  }

  /**
   * Other routes are not touched.
   */
  public function testIgnoresOtherRoutes(): void {
    $build = $this->chooseBuild();

    $this->assertSame($build, $this->dispatch($this->subscriber($this->inlineBuild()), $build, 'layout_builder.choose_inline_block'));
    $this->assertNull($this->inlineArguments);
  }

}
