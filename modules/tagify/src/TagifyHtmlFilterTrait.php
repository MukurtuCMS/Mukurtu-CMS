<?php

namespace Drupal\tagify;

use Drupal\Component\Utility\Xss;

/**
 * Provides HTML filtering functionality for Tagify elements.
 */
trait TagifyHtmlFilterTrait {

  /**
   * Filters HTML to safely allow img and svg tags.
   *
   * @param string $html
   *   The HTML string to filter.
   *
   * @return string
   *   The filtered HTML with only safe img and svg elements.
   */
  protected static function filterHtmlWithImages(string $html): string {
    $allowed_tags = [
      'img',
      'image',
      // SVG.
      'svg',
      'altglyph',
      'altglyphdef',
      'altglyphitem',
      'animatecolor',
      'animatemotion',
      'animatetransform',
      'circle',
      'clippath',
      'defs',
      'desc',
      'ellipse',
      'filter',
      'font',
      'g',
      'glyph',
      'glyphref',
      'hkern',
      'image',
      'line',
      'lineargradient',
      'marker',
      'mask',
      'metadata',
      'mpath',
      'path',
      'pattern',
      'polygon',
      'polyline',
      'radialgradient',
      'rect',
      'stop',
      'switch',
      'symbol',
      'text',
      'textpath',
      'title',
      'tref',
      'tspan',
      'use',
      'view',
      'vkern',
      // SVG Filters.
      'feBlend',
      'feColorMatrix',
      'feComponentTransfer',
      'feComposite',
      'feConvolveMatrix',
      'feDiffuseLighting',
      'feDisplacementMap',
      'feDistantLight',
      'feFlood',
      'feFuncA',
      'feFuncB',
      'feFuncG',
      'feFuncR',
      'feGaussianBlur',
      'feMerge',
      'feMergeNode',
      'feMorphology',
      'feOffset',
      'fePointLight',
      'feSpecularLighting',
      'feSpotLight',
      'feTile',
      'feTurbulence',
    ];

    return Xss::filter($html, $allowed_tags);
  }

}
