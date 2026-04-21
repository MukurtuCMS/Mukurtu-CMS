<?php

namespace Drupal\Tests\twig_tweak\Kernel;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;

/**
 * A test class for testing the image view builder.
 *
 * @group twig_tweak
 */
final class ImageViewBuilderTest extends AbstractTestCase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'twig_tweak',
    'twig_tweak_test',
    'user',
    'system',
    'file',
    'image',
    'responsive_image',
    'breakpoint',
  ];

  /**
   * The public image URI.
   *
   * @var string
   */
  protected string $publicImageUri;

  /**
   * The private image URI.
   *
   * @var string
   */
  protected string $privateImageUri;

  /**
   * The public image file.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $publicImage;

  /**
   * The private image file.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $privateImage;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');

    $file_system = $this->container->get('file_system');

    $file_system->prepareDirectory($this->siteDirectory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $private_directory = $this->siteDirectory . '/private';

    $file_system->prepareDirectory($private_directory, FileSystemInterface::CREATE_DIRECTORY);
    $this->setSetting('file_private_path', $private_directory);

    $image_style = ImageStyle::create([
      'name' => 'small',
      'label' => 'Small',
    ]);
    // Add a crop effect:
    $image_style->addImageEffect([
      'id' => 'image_resize',
      'data' => [
        'width' => 10,
        'height' => 10,
      ],
      'weight' => 0,
    ]);
    $image_style->save();

    $responsive_image_style = ResponsiveImageStyle::create([
      'id' => 'wide',
      'label' => 'Wide',
      'breakpoint_group' => 'twig_tweak_image_view_builder',
      'fallback_image_style' => 'small',
    ]);
    $responsive_image_style->save();

    // Create a copy of a test image file in root. Original sizes: 40x20px.
    $this->publicImageUri = 'public://image-test-do.jpg';
    $file_system->copy('core/tests/fixtures/files/image-test.jpg', $this->publicImageUri, FileExists::Replace);
    $this->assertFileExists($this->publicImageUri);
    $this->publicImage = File::create([
      'uri' => $this->publicImageUri,
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $this->publicImage->save();

    // Create a copy of a test image file in root. Original sizes: 40x20px.
    $this->privateImageUri = 'private://image-test-do.png';
    $file_system->copy('core/tests/fixtures/files/image-test.png', $this->privateImageUri, FileExists::Replace);
    $this->assertFileExists($this->privateImageUri);
    $this->privateImage = File::create([
      'uri' => $this->privateImageUri,
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $this->privateImage->save();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * Test callback.
   */
  public function testImageViewBuilder(): void {
    // @todo Remove this once we drop support for Drupal 10.
    if (version_compare(\Drupal::VERSION, '11.0.dev', '<')) {
      self::markTestSkipped();
    }

    $view_builder = $this->container->get('twig_tweak.image_view_builder');

    $uri = $this->publicImage->getFileUri();
    $image = \Drupal::service('image.factory')->get($uri);
    $imageOriginalWidth = $image->getWidth();
    $imageOriginalHeight = $image->getHeight();
    self::assertTrue($image->isValid());
    self::assertEquals(40, $imageOriginalWidth);
    self::assertEquals(20, $imageOriginalHeight);

    // -- Without style.
    $build = $view_builder->build($this->publicImage);
    $expected_build = [
      '#uri' => $this->publicImageUri,
      '#attributes' => [],
      '#theme' => 'image',
      '#cache' => [
        'contexts' => [
          'user',
          'user.permissions',
        ],
        'tags' => [
          'file:1',
          'tag_for_' . $this->publicImageUri,
        ],
        'max-age' => 70,
      ],
    ];
    self::assertRenderArray($expected_build, $build);
    self::assertSame('<img src="/files/image-test-do.jpg" alt="" />', $this->renderPlain($build));

    // -- With style.
    $build = $view_builder->build($this->publicImage, 'small', ['alt' => 'Image Test Do']);
    $expected_build = [
      '#uri' => $this->publicImageUri,
      '#attributes' => ['alt' => 'Image Test Do'],
      '#width' => $imageOriginalWidth,
      '#height' => $imageOriginalHeight,
      '#theme' => 'image_style',
      '#style_name' => 'small',
      '#cache' => [
        'contexts' => [
          'user',
          'user.permissions',
        ],
        'tags' => [
          'file:1',
          'tag_for_' . $this->publicImageUri,
        ],
        'max-age' => 70,
      ],
    ];
    self::assertRenderArray($expected_build, $build);
    self::assertSame('<img alt="Image Test Do" src="/files/styles/small/public/image-test-do.jpg?itok=abc" width="10" height="10" loading="lazy" />', $this->renderPlain($build));

    // -- With responsive style.
    $build = $view_builder->build($this->publicImage, 'wide', ['alt' => 'Image Test Do'], TRUE);
    $expected_build = [
      '#uri' => $this->publicImageUri,
      '#attributes' => ['alt' => 'Image Test Do'],
      '#width' => $imageOriginalWidth,
      '#height' => $imageOriginalHeight,
      '#type' => 'responsive_image',
      '#responsive_image_style_id' => 'wide',
      '#cache' => [
        'contexts' => [
          'user',
          'user.permissions',
        ],
        'tags' => [
          'file:1',
          'tag_for_' . $this->publicImageUri,
        ],
        'max-age' => 70,
      ],
    ];
    self::assertRenderArray($expected_build, $build);
    self::assertSame('<picture><img width="10" height="10" src="/files/styles/small/public/image-test-do.jpg?itok=abc" alt="Image Test Do" loading="lazy" /></picture>', $this->renderPlain($build));

    // -- Private image with access check.
    $build = $view_builder->build($this->privateImage);
    $expected_build = [
      '#cache' => [
        'contexts' => ['user'],
        'tags' => [
          'file:2',
          'tag_for_' . $this->privateImageUri,
        ],
        'max-age' => 70,
      ],
    ];
    self::assertRenderArray($expected_build, $build);
    self::assertSame('', $this->renderPlain($build));

    // -- Private image without access check.
    $build = $view_builder->build($this->privateImage, NULL, [], FALSE, FALSE);
    $expected_build = [
      '#uri' => $this->privateImageUri,
      '#attributes' => [],
      '#theme' => 'image',
      '#cache' => [
        'contexts' => [],
        'tags' => ['file:2'],
        'max-age' => Cache::PERMANENT,
      ],
    ];
    self::assertRenderArray($expected_build, $build);
    self::assertSame('<img src="/files/image-test-do.png" alt="" />', $this->renderPlain($build));
  }

  /**
   * Renders a render array.
   */
  private function renderPlain(array $build): string {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $html = DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION, '10.3.0',
      fn () => $renderer->renderInIsolation($build),
      fn () => $renderer->renderPlain($build),
    );
    $html = preg_replace('#src=".+/files/#s', 'src="/files/', $html);
    $html = preg_replace('#\?itok=.+?"#', '?itok=abc"', $html);
    $html = preg_replace(['#\s{2,}#', '#\n#'], '', $html);
    return rtrim($html);
  }

}
