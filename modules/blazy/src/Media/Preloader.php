<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\UrlHelper;
use Drupal\blazy\Blazy;
use Drupal\blazy\Utility\CheckItem;

/**
 * Provides preload utility.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo recap similiraties and make them plugins.
 */
class Preloader {

  /**
   * Preload late-discovered resources for better performance.
   *
   * @see https://web.dev/preload-critical-assets/
   * @see https://caniuse.com/?search=preload
   * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Link_types/preload
   * @see https://developer.chrome.com/blog/new-in-chrome-73/#more
   * @nottodo support multiple hero images like carousels.
   */
  public static function preload(array &$load, array $settings): void {
    $blazies = $settings['blazies'];
    $images  = array_filter($blazies->get('images', []));
    $sources = $blazies->get('resimage.sources', []);

    // @todo refine to just a hero image, not always 0 for sliders.
    if (empty($images) || empty($images[0]['uri'])) {
      return;
    }

    $links = self::generate($images, $sources, $blazies);
    foreach ($links as $key => $value) {
      if ($value) {
        $load['html_head'][$key] = $value;
      }
    }
  }

  /**
   * Extracts uris from file/ media entity, relevant for the new option Preload.
   *
   * @requires image styles defined via BlazyImage::styles().
   *
   * Also extract the found image for gallery/ zoom like, ElevateZoomPlus, etc.
   *
   * @todo merge urls here as well once puzzles are solved: URI may be fed by
   * field formatters like this one, blazy_filter, views field, or manual call.
   */
  public static function prepare(array &$settings, $items, array $entities = []): void {
    $blazies = $settings['blazies'];
    if (array_filter($blazies->get('images', []))) {
      return;
    }

    $style = $blazies->get('image.style');

    $func = function ($item, $entity, $delta = 0) use (&$settings, $blazies, $style) {
      $options  = ['entity' => $entity, 'settings' => $settings];
      $image    = BlazyImage::item($item, $options);
      $uri      = BlazyFile::uri($image);
      $valid    = BlazyFile::isValidUri($uri);
      $unstyled = $uri ? CheckItem::unstyled($settings, $uri) : FALSE;
      $url      = BlazyImage::toUrl($settings, $style, $uri);

      // Only needed the first found image, no problem which with mixed media.
      if ($uri && !$blazies->get('first.uri')) {
        $blazies->set('first.url', $url)
          ->set('first.item', $image)
          ->set('first.unstyled', $unstyled)
          ->set('first.uri', $uri);

        // The first image dimensions to differ from individual item dimensions.
        BlazyImage::dimensions($settings, $image, $uri, TRUE);
      }

      // @todo also pass $style + $image when all sources covered.
      return $uri ? [
        'delta'    => $delta,
        'unstyled' => $unstyled,
        'uri'      => $uri,
        'url'      => $url,
        'valid'    => $valid,
      ] : [];
    };

    $empties = $images = [];
    foreach ($items as $key => $item) {
      $image = [];

      // Priotize image file, then Media, etc.
      $entity = is_object($item) && isset($item->entity) ? $item->entity : NULL;
      if (!$entity) {
        $entity = $entities[$key] ?? NULL;
      }

      // Respects empty URI to keep indices intact for correct mixed media.
      $image = $func($item, $entity, $key);

      $images[] = $image;

      if (empty($image['uri'])) {
        $empties[] = TRUE;
      }
    }

    $empty = count($empties) == count($images);
    $images = $empty ? array_filter($images) : $images;

    // This is also required by BlazyResponsiveImage::sources().
    $blazies->set('images', $images);

    // Checks for [Responsive] image dimensions and sources for formatters
    // and filters. Sets dimensions once, if cropped, to reduce costs with ton
    // of images. This is less expensive than re-defining dimensions per image.
    // These also provide data for the Preload option.
    if (!$blazies->was('resimage_dimensions')) {
      $unstyled = $blazies->get('first.unstyled');
      if (!$unstyled && $blazies->get('first.uri')) {
        $resimage = BlazyResponsiveImage::toStyle($settings, $unstyled);
        if ($resimage) {
          BlazyResponsiveImage::dimensions($settings, $resimage, TRUE);
        }
        elseif ($style) {
          BlazyImage::cropDimensions($settings, $style);
        }
      }
      $blazies->set('was.resimage_dimensions', TRUE);
    }
  }

  /**
   * Generates preload urls.
   */
  private static function generate(array $images, array $sources, $blazies): \Generator {
    $loading = $blazies->get('image.loading', 'lazy');
    $heroes = in_array($loading, ['slider', 'unlazy']);
    $priority = $blazies->use('bg', FALSE) && $heroes;

    $link = function ($url, $uri, $item, $valid, $hero): array {
      // Suppress useless warning of likely failing initial image generation.
      // Better than checking file exists.
      // Each field may have different mime types for each image just like URIs.
      $mime = @mime_content_type($uri) ?: '';
      if ($item && $item_type = $item['type'] ?? NULL) {
        $mime = $item_type->value() ?: $mime;
      }

      [$type] = array_map('trim', explode('/', $mime, 2));
      $key = hash('md2', $url);

      $attrs = [
        'rel'  => 'preload',
        'as'   => $type,
        'href' => $valid ? $url : UrlHelper::stripDangerousProtocols($url),
        'type' => $mime,
      ];

      $suffix = '';
      if ($srcset = ($item['srcset'] ?? NULL)) {
        $suffix = '_responsive';
        $attrs['imagesrcset'] = $srcset->value();

        if ($sizes = ($item['sizes'] ?? NULL)) {
          $attrs['imagesizes'] = $sizes->value();
        }
      }

      // Only if BG and a hero image, set the fetchpriority.
      if ($hero) {
        $attrs['fetchpriority'] = 'high';
      }

      // Checks for external URI.
      if (UrlHelper::isExternal($uri ?: $url)) {
        $attrs['crossorigin'] = TRUE;
      }

      return [
        [
          '#tag' => 'link',
          '#attributes' => $attrs,
        ],
        'blazy' . $suffix . '_' . $type . $key,
      ];
    };

    // Responsive image with multiple sources.
    if ($sources) {
      foreach ($sources as $delta => $source) {
        $uri   = $source['uri'] ?? NULL;
        $url   = $source['fallback'] ?? NULL;
        $valid = $source['valid'] ?? FALSE;
        $start = $delta == $blazies->get('initial', -1);
        $hero  = $priority && $start;

        // Preloading 1px data URI makes no sense, see if image_url exists.
        if ($url) {
          $data_uri = Blazy::isDataUri($url);
          if ($data_uri && $url2 = $source['url'] ?? NULL) {
            $url = $url2;
          }
        }

        foreach ($source['items'] as $source_item) {
          yield empty($source_item['srcset']) || !$start ? NULL : $link($url, $uri, $source_item, $valid, $hero);
        }
      }
    }
    else {
      // Regular plain old images.
      foreach ($images as $delta => $image) {
        // Indices might be preserved even empty/ failing URI, etc.
        $uri   = $image['uri'] ?? NULL;
        $url   = $image['url'] ?? NULL;
        $valid = $image['valid'] ?? FALSE;
        $start = $delta == $blazies->get('initial', -1);
        $hero  = $priority && $start;

        // URI might be empty with mixed media, but indices are preserved.
        yield $uri && $url && $start ? $link($url, $uri, NULL, $valid, $hero) : NULL;
      }
    }
  }

}
