<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Render\RenderContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Layout Builder menu blocks get unique heading ids (#2395).
 *
 * The block--system-menu-block.html.twig template labels the nav with a
 * heading whose id comes from the block's id attribute. Without one, every
 * menu block on the dashboard rendered aria-labelledby="-menu" and id="-menu".
 */
#[Group('mukurtu_core')]
class LayoutBuilderMenuBlockIdTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'dashboards',
    'field',
    'file',
    'geofield',
    'image',
    'layout_builder',
    'layout_discovery',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['system']);
    $this->container->get('router.builder')->rebuild();
    // The first user created is uid 1. Use it up so the menu renders for an
    // ordinary account.
    $this->createUser();
    $this->setCurrentUser($this->createUser());
  }

  /**
   * Builds and renders a Layout Builder component.
   */
  protected function renderComponent(string $uuid, string $plugin_id, bool $in_preview = FALSE, array $extra_attributes = []): string {
    $component = new SectionComponent($uuid, 'content', [
      'id' => $plugin_id,
      'label' => 'Test block',
      'label_display' => 'visible',
      'provider' => 'system',
    ]);
    $event = new SectionComponentBuildRenderArrayEvent($component, [], $in_preview);
    $this->container->get('event_dispatcher')->dispatch($event, LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY);
    $build = $event->getBuild();
    $this->assertNotEmpty($build, "$plugin_id built a render array");
    if ($extra_attributes) {
      $build['#attributes'] = $extra_attributes + ($build['#attributes'] ?? []);
    }

    $renderer = $this->container->get('renderer');
    return (string) $renderer->executeInRenderContext(new RenderContext(), fn() => $renderer->render($build));
  }

  /**
   * Returns the nav's aria-labelledby and the heading's id from rendered HTML.
   */
  protected function headingIds(string $html): array {
    $this->assertMatchesRegularExpression('/<nav[^>]* aria-labelledby="([^"]*)"/', $html);
    $this->assertMatchesRegularExpression('/<h2[^>]* id="([^"]*)"/', $html);
    preg_match('/<nav[^>]* aria-labelledby="([^"]*)"/', $html, $labelledby);
    preg_match('/<h2[^>]* id="([^"]*)"/', $html, $heading);
    return [$labelledby[1], $heading[1]];
  }

  /**
   * Each menu block labels its nav with its own heading.
   */
  public function testMenuBlocksGetUniqueHeadingIds(): void {
    [$first_labelledby, $first_heading] = $this->headingIds($this->renderComponent('11111111-1111-4111-8111-111111111111', 'system_menu_block:account'));
    [$second_labelledby, $second_heading] = $this->headingIds($this->renderComponent('22222222-2222-4222-8222-222222222222', 'system_menu_block:account'));

    $this->assertSame($first_heading, $first_labelledby);
    $this->assertSame($second_heading, $second_labelledby);
    $this->assertNotSame('-menu', $first_heading);
    $this->assertNotSame($first_heading, $second_heading);
    $this->assertSame('block-11111111-1111-4111-8111-111111111111-menu', $first_heading);
  }

  /**
   * The id is also set in the Layout Builder editor preview.
   */
  public function testPreviewGetsUniqueHeadingIds(): void {
    [$labelledby, $heading] = $this->headingIds($this->renderComponent('33333333-3333-4333-8333-333333333333', 'system_menu_block:account', TRUE));

    $this->assertSame('block-33333333-3333-4333-8333-333333333333-menu', $heading);
    $this->assertSame($heading, $labelledby);
  }

  /**
   * Blocks that are not menu blocks are left alone.
   */
  public function testOtherBlocksAreUntouched(): void {
    $component = new SectionComponent('44444444-4444-4444-8444-444444444444', 'content', [
      'id' => 'system_powered_by_block',
      'label' => 'Powered by',
      'label_display' => 'visible',
      'provider' => 'system',
    ]);
    $event = new SectionComponentBuildRenderArrayEvent($component, [], FALSE);
    $this->container->get('event_dispatcher')->dispatch($event, LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY);

    $this->assertArrayNotHasKey('id', $event->getBuild()['#attributes'] ?? []);
  }

}
