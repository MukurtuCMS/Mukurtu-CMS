<?php

namespace Drupal\Tests\twig_tweak\Kernel;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * A test for BlockViewBuilder.
 *
 * @group twig_tweak
 */
final class BlockViewBuilderTest extends KernelTestBase {

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
   * Test callback.
   *
   * @see \Drupal\twig_tweak_test\Plugin\Block\FooBlock
   */
  public function testBlockViewBuilder(): void {

    $view_builder = $this->container->get('twig_tweak.block_view_builder');

    // -- Default output.
    $this->setUpCurrentUser(['name' => 'User 1']);
    $build = $view_builder->build('twig_tweak_test_foo');
    $expected_build = [
      'content' => [
        '#markup' => 'Foo',
        '#cache' => [
          'contexts' => ['url'],
          'tags' => ['tag_from_build'],
        ],
      ],
      '#theme' => 'block',
      '#id' => NULL,
      '#attributes' => [
        'id' => 'foo',
      ],
      '#contextual_links' => [],
      '#configuration' => [
        'id' => 'twig_tweak_test_foo',
        'label' => '',
        'label_display' => 'visible',
        'provider' => 'twig_tweak_test',
        'content' => 'Foo',
      ],
      '#plugin_id' => 'twig_tweak_test_foo',
      '#base_plugin_id' => 'twig_tweak_test_foo',
      '#derivative_plugin_id' => NULL,
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['tag_from_blockAccess'],
        'max-age' => 35,
        'keys' => [
          'twig_tweak_block',
          'twig_tweak_test_foo',
          '[configuration]=04c46ea912d2866a3a36c67326da1ef38f1c93cc822d6c45e1639f3decdebbdc',
          '[wrapper]=1',
        ],
      ],
    ];

    // @todo Remove this once we drop support for Drupal 9.2.
    // @see https://www.drupal.org/node/3230199
    if (\version_compare(\Drupal::VERSION, '9.3.0-dev', '<')) {
      $expected_build['#configuration'] = [
        'id' => 'twig_tweak_test_foo',
        'label' => '',
        'provider' => 'twig_tweak_test',
        'label_display' => 'visible',
        'content' => 'Foo',
      ];
    }

    self::assertSame($expected_build, $build);
    self::assertSame('<div id="foo">Foo</div>', $this->renderPlain($build));

    // -- Non-default configuration.
    $configuration = [
      'content' => 'Bar',
      'label' => 'Example',
      'id' => 'example',
    ];
    $build = $view_builder->build('twig_tweak_test_foo', $configuration);
    $expected_build['content']['#markup'] = 'Bar';
    $expected_build['#configuration']['label'] = 'Example';
    $expected_build['#configuration']['content'] = 'Bar';
    $expected_build['#configuration']['id'] = 'example';
    $expected_build['#id'] = 'example';
    $expected_build['#cache']['keys'] = [
      'twig_tweak_block',
      'twig_tweak_test_foo',
      '[configuration]=8e53716fcf7e5d5c45effd55e9b2a267bbaf333f7253766f572d58e4f7991b36',
      '[wrapper]=1',
    ];
    self::assertSame($expected_build, $build);
    self::assertSame('<div id="block-example"><h2>Example</h2>Bar</div>', $this->renderPlain($build));

    // -- Without wrapper.
    $build = $view_builder->build('twig_tweak_test_foo', [], FALSE);
    $expected_build = [
      'content' => [
        '#markup' => 'Foo',
        // Since the block is built without wrapper #attributes must remain in
        // 'content' element.
        '#attributes' => [
          'id' => 'foo',
        ],
        '#cache' => [
          'contexts' => ['url'],
          'tags' => ['tag_from_build'],
        ],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['tag_from_blockAccess'],
        'max-age' => 35,
        'keys' => [
          'twig_tweak_block',
          'twig_tweak_test_foo',
          '[configuration]=04c46ea912d2866a3a36c67326da1ef38f1c93cc822d6c45e1639f3decdebbdc',
          '[wrapper]=0',
        ],
      ],
    ];
    self::assertSame($expected_build, $build);
    self::assertSame('Foo', $this->renderPlain($build));

    // -- Unprivileged user.
    $this->setUpCurrentUser(['name' => 'User 2']);
    $build = $view_builder->build('twig_tweak_test_foo');
    $expected_build = [
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['tag_from_blockAccess'],
        'max-age' => 35,
        'keys' => [
          'twig_tweak_block',
          'twig_tweak_test_foo',
          '[configuration]=04c46ea912d2866a3a36c67326da1ef38f1c93cc822d6c45e1639f3decdebbdc',
          '[wrapper]=1',
        ],
      ],
    ];
    self::assertSame($expected_build, $build);
    self::assertSame('', $this->renderPlain($build));
  }

  /**
   * Renders a render array.
   */
  private function renderPlain(array $build): string {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $content = (string) DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION, '10.3.0',
      fn () => $renderer->renderInIsolation($build),
      fn () => $renderer->renderPlain($build),
    );
    return rtrim(preg_replace('#\s{2,}#', '', $content));
  }

}
