<?php

declare(strict_types=1);

namespace Drupal\mukurtu_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\mukurtu_api\Service\SiteManifest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Describes the site to apps before anyone signs in.
 *
 * An app reads this once per site to brand itself, list languages and check
 * which API features the site supports.
 */
final class SiteManifestController implements ContainerInjectionInterface {

  public function __construct(
    protected SiteManifest $siteManifest,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get(SiteManifest::class));
  }

  /**
   * Returns the site manifest.
   */
  public function manifest(Request $request): CacheableJsonResponse {
    [$data, $cacheability] = $this->siteManifest->build($request);
    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

}
