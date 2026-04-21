<?php

namespace Drupal\Tests\twig_tweak\Kernel;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * A test for MenuViewBuilder.
 *
 * @group twig_tweak
 */
final class MenuViewBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'twig_tweak',
    'user',
    'system',
    'link',
    'menu_link_content',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('menu_link_content');

    $this->container->get('entity_type.manager')
      ->getStorage('menu')
      ->create([
        'id' => 'test-menu',
        'label' => 'Test menu',
        'description' => 'Description text.',
      ])
      ->save();

    $link_1 = MenuLinkContent::create([
      'expanded' => TRUE,
      'title' => 'Link 1',
      'link' => ['uri' => 'internal:/foo/1'],
      'menu_name' => 'test-menu',
    ]);
    $link_1->save();

    MenuLinkContent::create([
      'title' => 'Link 1.1',
      'link' => ['uri' => 'internal:/foo/1/1'],
      'menu_name' => 'test-menu',
      'parent' => $link_1->getPluginId(),
    ])->save();

    MenuLinkContent::create([
      'title' => 'Link 2',
      'link' => ['uri' => 'internal:/foo/2'],
      'menu_name' => 'test-menu',
    ])->save();
  }

  /**
   * Test callback.
   */
  public function testMenuViewBuilder(): void {

    $view_builder = $this->container->get('twig_tweak.menu_view_builder');

    $build = $view_builder->build('test-menu');
    $expected_output = <<< 'HTML'
      <ul>
        <li>
          <a href="/foo/1">Link 1</a>
          <ul>
            <li>
              <a href="/foo/1/1">Link 1.1</a>
            </li>
           </ul>
        </li>
        <li>
          <a href="/foo/2">Link 2</a>
        </li>
      </ul>
    HTML;
    $this->assertMarkup($expected_output, $build);

    $build = $view_builder->build('test-menu', 2);
    $expected_output = <<< 'HTML'
      <ul>
        <li>
          <a href="/foo/1/1">Link 1.1</a>
        </li>
       </ul>
    HTML;
    $this->assertMarkup($expected_output, $build);

    $build = $view_builder->build('test-menu', 1, 1);
    $expected_output = <<< 'HTML'
      <ul>
        <li>
          <a href="/foo/1">Link 1</a>
        </li>
        <li>
          <a href="/foo/2">Link 2</a>
        </li>
      </ul>
    HTML;
    $this->assertMarkup($expected_output, $build);
  }

  /**
   * Asserts menu markup.
   */
  private function assertMarkup(string $expected_markup, array $build): void {
    $expected_markup = preg_replace('#\s{2,}#', '', $expected_markup);
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $actual_html = DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION, '10.3.0',
      fn () => $renderer->renderInIsolation($build),
      fn () => $renderer->renderPlain($build),
    );
    $actual_markup = preg_replace('#\s{2,}#', '', $actual_html);
    self::assertSame($expected_markup, $actual_markup);
  }

}
