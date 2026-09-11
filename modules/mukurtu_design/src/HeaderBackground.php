<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Path\PathMatcherInterface;

/**
 * Resolves the configured header background image for the current page.
 */
final class HeaderBackground {

  /**
   * The file usage id the settings form registers the header image under.
   */
  public const USAGE_ID = 'header_background';

  /**
   * The text treatments a site can choose between.
   *
   * Each one pairs a scrim with a text colour in the theme, so the contrast of
   * the header does not depend on how light or dark the uploaded image happens
   * to be. See _header.scss.
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
   * Returns the header background to render, if any.
   *
   * @return array
   *   Either an empty array when no background applies, or:
   *   - url: the image URL, ready for a CSS url() value.
   *   - treatment: one of the TREATMENTS keys.
   */
  public function resolve(): array {
    $config = $this->configFactory->get(DesignPalette::SETTINGS);

    $fid = $config->get('header.image');
    if (empty($fid)) {
      return [];
    }

    // A site whose front page already carries a full-width hero block can turn
    // the header background off there without losing it everywhere else.
    if ($this->pathMatcher->isFrontPage() && !$config->get('header.show_on_front')) {
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
   * The result varies by config and, because of the front page opt-out, by
   * whether the current request is the front page.
   */
  public function getCacheableMetadata(): CacheableMetadata {
    $metadata = CacheableMetadata::createFromObject(
      $this->configFactory->get(DesignPalette::SETTINGS)
    );
    $metadata->addCacheContexts(['url.path.is_front']);

    return $metadata;
  }

}
