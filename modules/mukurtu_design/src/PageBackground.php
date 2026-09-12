<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Path\PathMatcherInterface;

/**
 * Resolves the configured page background image for the current request.
 */
final class PageBackground {

  /**
   * The file usage id the settings form registers the background image under.
   */
  public const USAGE_ID = 'page_background';

  /**
   * The file usage id the settings form registers the header image under.
   */
  public const HEADER_USAGE_ID = 'header_background';

  /**
   * The text treatments a site can choose between.
   *
   * Each one pairs a scrim with a text colour in the theme, so the contrast of
   * the header does not depend on how light or dark the uploaded image happens
   * to be. See _layout.scss.
   */
  public const TREATMENTS = [
    'light' => 'Light text on a darkened image',
    'dark' => 'Dark text on a lightened image',
  ];

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
    protected readonly PathMatcherInterface $pathMatcher,
  ) {}

  /**
   * Returns the page background to render, if any.
   *
   * @return array
   *   Either an empty array when no background applies, or:
   *   - url: the image URL, ready for a CSS url() value.
   *   - treatment: one of the TREATMENTS keys.
   */
  public function resolve(): array {
    $config = $this->configFactory->get(DesignPalette::SETTINGS);

    $fid = $config->get('background.image');
    if (empty($fid)) {
      return [];
    }

    // Front page only. Behind an interior page the image sits under content
    // laid out for a plain ground and mostly shows as empty space, which is
    // also what the Plateau Peoples' Web Portal does - its interior pages
    // carry no background image at all.
    if (!$this->pathMatcher->isFrontPage()) {
      return [];
    }

    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file) {
      return [];
    }

    $treatment = $config->get('background.text_treatment');

    return [
      'url' => $this->fileUrlGenerator->generateString($file->getFileUri()),
      'treatment' => isset(self::TREATMENTS[$treatment]) ? $treatment : 'light',
    ];
  }

  /**
   * Returns the header background to render, if any.
   *
   * Unlike the page background this applies on every page, with one exception:
   * where the front page has its own full background, that image already
   * covers the header and a second one would sit on top of it.
   *
   * @return array
   *   Either an empty array, or url and treatment as resolve() returns.
   */
  public function resolveHeader(): array {
    $config = $this->configFactory->get(DesignPalette::SETTINGS);

    $fid = $config->get('header.image');
    if (empty($fid)) {
      return [];
    }

    // The page background wins on the front page: it already covers the header.
    if ($this->pathMatcher->isFrontPage() && !empty($config->get('background.image'))) {
      return [];
    }

    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file) {
      return [];
    }

    $treatment = $config->get('header.text_treatment');

    return [
      'url' => $this->fileUrlGenerator->generateString($file->getFileUri()),
      'treatment' => isset(self::TREATMENTS[$treatment]) ? $treatment : 'light',
    ];
  }

  /**
   * Returns the cacheability of a resolve() result.
   *
   * The result varies by config and by whether this request is the front page.
   */
  public function getCacheableMetadata(): CacheableMetadata {
    $metadata = CacheableMetadata::createFromObject(
      $this->configFactory->get(DesignPalette::SETTINGS)
    );
    $metadata->addCacheContexts(['url.path.is_front']);

    return $metadata;
  }

}
