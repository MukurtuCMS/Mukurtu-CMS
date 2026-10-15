<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\MenuBlockIdHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Layout Builder menu blocks get unique heading IDs.
 *
 * Core's block--system-menu-block.html.twig derives the heading ID from the
 * block's own ID, which core only sets for blocks that have a config entity.
 * Layout Builder blocks do not, so every one of them rendered id="-menu" and
 * every aria-labelledby resolved to the first heading on the page. On the Mukurtu
 * Dashboard that was all 16 blocks (issue #2395).
 *
 * @see \Drupal\mukurtu_core\Hook\MenuBlockIdHooks::preprocessBlock()
 */
#[Group('mukurtu_core')]
class MenuBlockIdTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    // KernelTestBase does not resolve dependencies, so block has to be listed
    // even though mukurtu_core requires it: without it there is no 'block'
    // theme hook and the render test silently produces an empty string.
    'block',
    'user',
    'field',
    'text',
    'filter',
    'geofield',
    'leaflet',
    'node',
    'mukurtu_core',
  ];

  /**
   * Builds the $variables array core's template_preprocess_block() produces.
   *
   * @param string|null $id
   *   The block's existing HTML ID, or NULL for a Layout Builder block.
   * @param string $base
   *   The base plugin ID.
   * @param string $derivative
   *   The derivative plugin ID, which for a menu block is the menu name.
   */
  protected function blockVariables(?string $id, string $base = 'system_menu_block', string $derivative = 'main'): array {
    $variables = [
      'base_plugin_id' => $base,
      'plugin_id' => $derivative ? $base . ':' . $derivative : $base,
      'derivative_plugin_id' => $derivative,
      'attributes' => [],
    ];
    if ($id !== NULL) {
      $variables['attributes']['id'] = $id;
    }
    return $variables;
  }

  /**
   * Tests that a menu block with no ID is given one.
   */
  public function testMenuBlockWithoutIdGetsOne(): void {
    $variables = $this->blockVariables(NULL);

    (new MenuBlockIdHooks())->preprocessBlock($variables);

    $this->assertNotEmpty($variables['attributes']['id']);
    // The template appends '-menu', so an empty ID is what produced the bug.
    $this->assertNotSame('-menu', $variables['attributes']['id'] . '-menu');
    $this->assertStringContainsString('main', $variables['attributes']['id']);
  }

  /**
   * Tests that repeated blocks for one menu still get distinct IDs.
   *
   * Two blocks showing the same menu derive the same name, so uniqueness has
   * to come from Html::getUniqueId() rather than from the name alone.
   */
  public function testRepeatedMenuBlocksGetDistinctIds(): void {
    $hooks = new MenuBlockIdHooks();
    $ids = [];
    foreach (range(1, 16) as $unused) {
      $variables = $this->blockVariables(NULL);
      $hooks->preprocessBlock($variables);
      $ids[] = $variables['attributes']['id'];
    }

    $this->assertCount(16, array_unique($ids), 'Every block got its own ID.');
    // The heading IDs the template builds from them must be unique too.
    $headingIds = array_map(static fn ($id) => $id . '-menu', $ids);
    $this->assertCount(16, array_unique($headingIds));
  }

  /**
   * Tests that a block that already has an ID is left alone.
   *
   * Blocks placed in a region get their ID from core, and changing it would
   * break CSS and JS that targets it.
   */
  public function testExistingIdIsNotOverwritten(): void {
    $variables = $this->blockVariables('block-olivero-main-menu');

    (new MenuBlockIdHooks())->preprocessBlock($variables);

    $this->assertSame('block-olivero-main-menu', $variables['attributes']['id']);
  }

  /**
   * Tests that non-menu blocks are left without an ID, as core leaves them.
   */
  public function testNonMenuBlockIsNotTouched(): void {
    $variables = $this->blockVariables(NULL, 'system_branding_block', '');

    (new MenuBlockIdHooks())->preprocessBlock($variables);

    $this->assertArrayNotHasKey('id', $variables['attributes']);
  }

  /**
   * Tests that the hook is registered, not merely present on disk.
   *
   * The fix is a preprocess hook: if the attribute stopped being discovered
   * the class would still pass every test above while the dashboard went
   * back to emitting id="-menu".
   */
  public function testHookIsRegistered(): void {
    $this->assertTrue(
      \Drupal::moduleHandler()->hasImplementations('preprocess_block', ['mukurtu_core']),
      'mukurtu_core implements hook_preprocess_block().',
    );
  }

  /**
   * Tests the rendered markup, end to end through the real template.
   *
   * The unit-level tests above pin the hook's logic, but the defect was in
   * what the template did with it. This renders two menu blocks the way
   * Layout Builder does, with no block ID, and checks the markup people
   * actually get.
   */
  public function testRenderedMenuBlocksHaveUniqueHeadingIds(): void {
    $this->installConfig(['system']);
    // The defect is in a template, so this needs a real theme rendering real
    // markup; without one, #theme => 'block' produces nothing and every
    // assertion about the output below would pass on an empty string.
    \Drupal::service('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    \Drupal::theme()->setActiveTheme(
      \Drupal::service('theme.initialization')->initTheme('stark'),
    );

    $build = [];
    foreach (['main', 'footer'] as $delta => $menu) {
      $build[$delta] = [
        '#theme' => 'block',
        // 'provider' is part of every real block's configuration; core's own
        // preprocess hooks read it without a guard.
        '#configuration' => [
          'label' => ucfirst($menu),
          'label_display' => 'visible',
          'provider' => 'system',
        ],
        '#plugin_id' => 'system_menu_block:' . $menu,
        '#base_plugin_id' => 'system_menu_block',
        '#derivative_plugin_id' => $menu,
        // Layout Builder blocks have no config entity, so no ID.
        '#id' => NULL,
        'content' => ['#markup' => ''],
      ];
    }

    $html = (string) \Drupal::service('renderer')->renderRoot($build);

    $this->assertStringNotContainsString('id="-menu"', $html, 'The bug markup is gone.');
    $this->assertStringNotContainsString('aria-labelledby="-menu"', $html);

    preg_match_all('/aria-labelledby="([^"]+)"/', $html, $labelledBy);
    $this->assertCount(2, $labelledBy[1], 'Both blocks rendered a labelled nav.');
    $this->assertCount(2, array_unique($labelledBy[1]), 'The two navs point at different headings.');

    // Each reference must resolve to a heading that is actually in the markup.
    foreach ($labelledBy[1] as $headingId) {
      $this->assertStringContainsString('id="' . $headingId . '"', $html);
    }
  }

}
