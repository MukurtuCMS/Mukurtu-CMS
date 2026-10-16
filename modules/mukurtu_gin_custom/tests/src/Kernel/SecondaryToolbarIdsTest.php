<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Gin's secondary toolbar gets its own tab and tray ids (#2397).
 *
 * Gin renders the toolbar's tabs and trays a second time in its secondary
 * toolbar. Without separate copies, every tab and tray id appeared twice.
 */
#[Group('mukurtu_gin_custom')]
class SecondaryToolbarIdsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mukurtu_gin_custom',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 3) . '/mukurtu_gin_custom.module';
  }

  /**
   * Builds toolbar variables the way core leaves them, with tabs pre-rendered.
   */
  protected function toolbarVariables(): array {
    $tab = [
      '#type' => 'link',
      '#title' => 'Shortcuts',
      '#url' => Url::fromUri('base:shortcuts'),
      '#id' => 'toolbar-item-shortcuts',
      '#attributes' => [
        'id' => 'toolbar-item-shortcuts',
        'class' => ['toolbar-item'],
        'data-toolbar-tray' => 'toolbar-item-shortcuts-tray',
      ],
      '#options' => [
        'attributes' => [
          'id' => 'toolbar-item-shortcuts',
          'class' => ['toolbar-item'],
          'data-toolbar-tray' => 'toolbar-item-shortcuts-tray',
        ],
      ],
      '#markup' => Markup::create('<a id="toolbar-item-shortcuts">Shortcuts</a>'),
      '#children' => Markup::create('<a id="toolbar-item-shortcuts">Shortcuts</a>'),
      '#printed' => TRUE,
    ];

    return [
      'attributes' => ['id' => 'toolbar-administration'],
      'tabs' => [
        'shortcuts' => ['link' => $tab, 'attributes' => new Attribute()],
        'home' => ['link' => ['#markup' => 'Home'], 'attributes' => new Attribute()],
      ],
      'trays' => [
        'shortcuts' => [
          'links' => ['#markup' => 'Tray links'],
          'attributes' => new Attribute([
            'id' => 'toolbar-item-shortcuts-tray',
            'data-toolbar-tray' => 'toolbar-item-shortcuts-tray',
          ]),
        ],
      ],
    ];
  }

  /**
   * Renders a render array to a string.
   */
  protected function renderToString(array $build): string {
    $renderer = $this->container->get('renderer');
    return (string) $renderer->executeInRenderContext(new RenderContext(), fn() => $renderer->render($build));
  }

  /**
   * Secondary copies get "-secondary" ids, and the main toolbar keeps its own.
   */
  public function testSecondaryCopiesGetTheirOwnIds(): void {
    $variables = $this->toolbarVariables();
    mukurtu_gin_custom_preprocess_toolbar($variables);

    $this->assertArrayHasKey('#secondary', $variables);
    $tab = $variables['#secondary']['tabs']['shortcuts']['link'];
    $html = $this->renderToString($tab);
    $this->assertStringContainsString('id="toolbar-item-shortcuts-secondary"', $html);
    $this->assertStringContainsString('data-toolbar-tray="toolbar-item-shortcuts-tray"', $html);
    // Gin builds the tab's class from '#id', so it is unchanged.
    $this->assertSame('toolbar-item-shortcuts', $tab['#id']);

    $tray = $variables['#secondary']['trays']['shortcuts']['attributes'];
    $this->assertSame('toolbar-item-shortcuts-tray-secondary', (string) $tray['id']);
    $this->assertSame('toolbar-item-shortcuts-tray', (string) $tray['data-toolbar-tray']);

    // The main toolbar's tabs and trays are untouched.
    $this->assertSame('toolbar-item-shortcuts', $variables['tabs']['shortcuts']['link']['#options']['attributes']['id']);
    $this->assertTrue($variables['tabs']['shortcuts']['link']['#printed']);
    $this->assertSame('toolbar-item-shortcuts-tray', (string) $variables['trays']['shortcuts']['attributes']['id']);
  }

  /**
   * Tabs without an id are copied as they are.
   */
  public function testTabsWithoutAnIdAreCopiedUnchanged(): void {
    $variables = $this->toolbarVariables();
    mukurtu_gin_custom_preprocess_toolbar($variables);

    $this->assertSame($variables['tabs']['home'], $variables['#secondary']['tabs']['home']);
  }

  /**
   * Ids that already end in "-secondary" are not suffixed again.
   */
  public function testExistingSecondaryIdsAreNotSuffixedAgain(): void {
    $variables = $this->toolbarVariables();
    $variables['trays']['shortcuts']['attributes']->setAttribute('id', 'toolbar-item-user-secondary');
    mukurtu_gin_custom_preprocess_toolbar($variables);

    $this->assertSame('toolbar-item-user-secondary', (string) $variables['#secondary']['trays']['shortcuts']['attributes']['id']);
  }

}
