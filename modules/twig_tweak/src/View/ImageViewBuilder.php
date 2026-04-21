<?php

namespace Drupal\twig_tweak\View;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Image\ImageFactory;
use Drupal\file\FileInterface;

/**
 * Image view builder.
 */
class ImageViewBuilder {

  /**
   * The provider image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs an ImageViewBuilder object.
   */
  public function __construct(ImageFactory $imageFactory) {
    $this->imageFactory = $imageFactory;
  }

  /**
   * Builds an image.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file object.
   * @param string $style
   *   (optional) Image style.
   * @param array $attributes
   *   (optional) Image attributes.
   * @param bool $responsive
   *   (optional) Indicates that the provided image style is responsive.
   * @param bool $check_access
   *   (optional) Indicates that access check is required.
   *
   * @return array
   *   A renderable array to represent the image.
   */
  public function build(FileInterface $file, ?string $style = NULL, array $attributes = [], bool $responsive = FALSE, bool $check_access = TRUE): array {
    $access = $check_access ? $file->access('view', NULL, TRUE) : AccessResult::allowed();

    $build = $access->isAllowed() ? $this->doBuild($file, $style, $attributes, $responsive) : [];

    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($access)
      ->addCacheableDependency($file)
      ->applyTo($build);

    return $build;
  }

  /**
   * Actually builds the image.
   */
  private function doBuild(FileInterface $file, ?string $style = NULL, array $attributes = [], bool $responsive = FALSE): array {
    $build['#uri'] = $file->getFileUri();
    $build['#attributes'] = $attributes;

    if (!$style) {
      $build['#theme'] = 'image';
      return $build;
    }

    $build['#width'] = $attributes['width'] ?? NULL;
    $build['#height'] = $attributes['height'] ?? NULL;

    if (!$build['#width'] && !$build['#height']) {
      // If an image style is given, image module needs the original image
      // dimensions to calculate image style's width and height and set the
      // attributes.
      // @see https://www.drupal.org/project/twig_tweak/issues/3356042
      $image = $this->imageFactory->get($file->getFileUri());
      if ($image->isValid()) {
        $build['#width'] = $image->getWidth();
        $build['#height'] = $image->getHeight();
      }
    }

    if ($responsive) {
      $build['#type'] = 'responsive_image';
      $build['#responsive_image_style_id'] = $style;
    }
    else {
      $build['#theme'] = 'image_style';
      $build['#style_name'] = $style;
    }

    return $build;
  }

}
