<?php

namespace Drupal\Tests\twig_tweak\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A test for RegionViewBuilder.
 *
 * @group twig_tweak
 */
final class RegionViewBuilderTest extends AbstractTestCase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'twig_tweak',
    'twig_tweak_test',
    'user',
    'system',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('block');
    $this->container->get('theme_installer')->install(['stark']);

    $values = [
      'id' => 'public_block',
      'plugin' => 'system_powered_by_block',
      'theme' => 'stark',
      'region' => 'sidebar_first',
    ];
    Block::create($values)->save();

    $values = [
      'id' => 'private_block',
      'plugin' => 'system_powered_by_block',
      'theme' => 'stark',
      'region' => 'sidebar_first',
    ];
    Block::create($values)->save();
  }

  /**
   * Test callback.
   */
  public function testRegionViewBuilder(): void {
    if (version_compare(\Drupal::VERSION, '11.1.dev', '<')) {
      $this->markTestSkipped();
    }

    $view_builder = $this->container->get('twig_tweak.region_view_builder');
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');

    $build = $view_builder->build('sidebar_first');
    // The build should be empty because 'stark' is not a default theme.
    $expected_build = [
      '#cache' => [
        'contexts' => [],
        'tags' => ['config:block_list'],
        'max-age' => Cache::PERMANENT,
      ],
    ];
    self::assertSame($expected_build, $build);

    // Specify the theme name explicitly.
    $build = $view_builder->build('sidebar_first', 'stark');
    $expected_build = [
      // Only public_block should be rendered.
      // @see twig_tweak_test_block_access()
      'public_block' => [
        '#cache' =>
          [
            'contexts' => [],
            'tags' => [
              'block_view',
              'config:block.block.public_block',
            ],
            'max-age' => Cache::PERMANENT,
            'keys' => [
              'entity_view',
              'block',
              'public_block',
            ],
          ],
        '#weight' => 0,
        '#lazy_builder' => [
          'Drupal\\block\\BlockViewBuilder::lazyBuilder',
          [
            'public_block',
            'full',
            NULL,
          ],
        ],
      ],
      '#region' => 'sidebar_first',
      '#theme_wrappers' => ['region'],
      // Even if the block is not accessible its cache metadata from access
      // callback should be here.
      '#cache' => [
        'contexts' => ['user'],
        'tags' => [
          'config:block.block.public_block',
          'config:block_list',
          'tag_for_private_block',
          'tag_for_public_block',
        ],
        'max-age' => 123,
      ],
    ];

    self::assertRenderArray($expected_build, $build);

    $expected_html = <<< 'HTML'
      <div>
        <div id="block-public-block">
          <span>Powered by <a href="https://www.drupal.org">Drupal</a></span>
        </div>
      </div>
    HTML;
    $actual_html = DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION, '10.3.0',
      fn () => $renderer->renderInIsolation($build),
      fn () => $renderer->renderPlain($build),
    );
    self::assertSame(self::normalizeHtml($expected_html), self::normalizeHtml($actual_html));

    // Set 'stark' as default site theme and check if the view builder without
    // 'theme' argument returns the same result.
    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('default', 'stark')
      ->save();

    $build = $view_builder->build('sidebar_first');
    self::assertRenderArray($expected_build, $build);

    Html::resetSeenIds();
    $actual_html = DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION, '10.3.0',
      fn () => $renderer->renderInIsolation($build),
      fn () => $renderer->renderPlain($expected_build),
    );
    self::assertSame(self::normalizeHtml($expected_html), self::normalizeHtml($actual_html));
  }

  /**
   * Normalizes the provided HTML.
   */
  private static function normalizeHtml(string $html): string {
    return rtrim(preg_replace(['#\s{2,}#', '#\n#'], '', $html));
  }

}
