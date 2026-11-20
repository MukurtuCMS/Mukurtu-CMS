<?php

declare(strict_types=1);

namespace Drupal\mukurtu_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\mukurtu_core\MukurtuVersion;
use Drupal\mukurtu_design\DesignPalette;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Describes the site to apps before anyone signs in.
 *
 * An app reads this once per site to brand itself, list languages and check
 * which API features the site supports. Everything here is already public on
 * the site's own pages, so it never includes anything that needs a sign-in.
 */
final class SiteManifestController implements ContainerInjectionInterface {

  /**
   * Version of this manifest's shape. Bump it when a key changes meaning.
   */
  const API_VERSION = 0;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LanguageManagerInterface $languageManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected ThemeSettingsProvider $themeSettings,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('module_handler'),
      $container->get(ThemeSettingsProvider::class),
    );
  }

  /**
   * Returns the site manifest.
   */
  public function manifest(Request $request): CacheableJsonResponse {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['languages:language_interface', 'url.site']);

    $site = $this->configFactory->get('system.site');
    $design = $this->configFactory->get(DesignPalette::SETTINGS);
    $themeConfig = $this->configFactory->get('system.theme');
    $theme = (string) $themeConfig->get('default');
    $cacheability->addCacheableDependency($site)
      ->addCacheableDependency($design)
      ->addCacheableDependency($themeConfig)
      ->addCacheableDependency($this->configFactory->get('system.theme.global'))
      ->addCacheableDependency($this->configFactory->get("$theme.settings"));

    $default = $this->languageManager->getDefaultLanguage()->getId();
    $languages = [];
    foreach ($this->languageManager->getLanguages() as $language) {
      $languages[] = [
        'id' => $language->getId(),
        'name' => $language->getName(),
        'direction' => $language->getDirection(),
        'default' => $language->getId() === $default,
      ];
    }
    $cacheability->addCacheTags(['config:configurable_language_list']);

    $data = [
      'api' => ['version' => self::API_VERSION],
      'mukurtu_version' => MukurtuVersion::get(),
      'name' => (string) $site->get('name'),
      'slogan' => (string) $site->get('slogan'),
      'logo' => $this->absoluteUrl($request, $this->themeSettings->getSetting('logo.url', $theme)),
      'palette' => $design->get('palette'),
      'colors' => $design->get('colors') ?? [],
      'languages' => $languages,
      'capabilities' => [
        'jsonapi' => $this->moduleHandler->moduleExists('jsonapi'),
      ],
    ];

    $response = new CacheableJsonResponse($data);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  /**
   * Turns a root-relative URL into an absolute one, so apps can fetch it.
   */
  protected function absoluteUrl(Request $request, mixed $url): ?string {
    if (!is_string($url) || $url === '') {
      return NULL;
    }
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
      return $request->getSchemeAndHttpHost() . $url;
    }
    return $url;
  }

}
