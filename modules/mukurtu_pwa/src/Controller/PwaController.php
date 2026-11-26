<?php

declare(strict_types=1);

namespace Drupal\mukurtu_pwa\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\mukurtu_api\Service\SiteManifest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves what makes the site installable: manifest, icons, service worker.
 */
final class PwaController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    protected SiteManifest $siteManifest,
    protected ConfigFactoryInterface $configFactory,
    protected ThemeSettingsProvider $themeSettings,
    protected ModuleExtensionList $moduleList,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(SiteManifest::class),
      $container->get('config.factory'),
      $container->get(ThemeSettingsProvider::class),
      $container->get('extension.list.module'),
    );
  }

  /**
   * Returns the web app manifest browsers read to install the site.
   */
  public function manifest(Request $request): CacheableResponse {
    [$site, $cacheability] = $this->siteManifest->build($request);
    $color = $site['colors']['brand_primary'] ?? '#ac143a';
    $default = array_values(array_filter($site['languages'], fn ($language) => $language['default']))[0] ?? NULL;
    $icon = fn (int $size, array $extra = []) => [
      'src' => Url::fromRoute('mukurtu_pwa.icon', ['size' => $size])->toString(),
      'sizes' => "{$size}x{$size}",
      'type' => 'image/png',
    ] + $extra;
    $data = [
      'id' => Url::fromRoute('<front>')->toString(),
      'name' => $site['name'],
      'short_name' => $site['name'],
      'description' => $site['slogan'],
      'start_url' => Url::fromRoute('<front>')->toString(),
      'scope' => base_path(),
      'display' => 'standalone',
      'theme_color' => $color,
      'background_color' => '#ffffff',
      'lang' => $default['id'] ?? 'en',
      'dir' => $default['direction'] ?? 'ltr',
      'icons' => [
        $icon(192),
        $icon(512),
        $icon(512, ['purpose' => 'maskable']),
      ],
    ];
    $response = new CacheableResponse(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, [
      'Content-Type' => 'application/manifest+json',
    ]);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  /**
   * Returns a square PNG icon made from the site logo on its brand color.
   *
   * Browsers can't use the logo directly: home-screen icons must be square
   * PNGs at fixed sizes, and most logos are wide.
   */
  public function icon(Request $request, string $size): CacheableResponse {
    $size = (int) $size;
    $design = $this->configFactory->get('mukurtu_design.settings');
    $theme = (string) $this->configFactory->get('system.theme')->get('default');
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($design)
      ->addCacheableDependency($this->configFactory->get('system.theme'))
      ->addCacheableDependency($this->configFactory->get("$theme.settings"));

    $canvas = imagecreatetruecolor($size, $size);
    $rgb = sscanf((string) ($design->get('colors.brand_primary') ?? ''), '#%02x%02x%02x');
    [$r, $g, $b] = is_array($rgb) && !in_array(NULL, $rgb, TRUE) ? $rgb : [172, 20, 58];
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, (int) $r, (int) $g, (int) $b));

    $logo = $this->loadLogo((string) $this->themeSettings->getSetting('logo.url', $theme));
    if ($logo) {
      // Leave a margin so the logo survives the round "maskable" crop.
      $box = (int) round($size * 0.6);
      $scale = min($box / imagesx($logo), $box / imagesy($logo));
      $w = (int) round(imagesx($logo) * $scale);
      $h = (int) round(imagesy($logo) * $scale);
      imagealphablending($canvas, TRUE);
      imagecopyresampled($canvas, $logo, intdiv($size - $w, 2), intdiv($size - $h, 2), 0, 0, $w, $h, imagesx($logo), imagesy($logo));
    }

    ob_start();
    imagepng($canvas);
    $png = (string) ob_get_clean();
    $response = new CacheableResponse($png, 200, [
      'Content-Type' => 'image/png',
      'Cache-Control' => 'public, max-age=86400',
    ]);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  /**
   * Loads the logo file behind a logo URL, if GD can read it.
   */
  protected function loadLogo(string $url): ?\GdImage {
    $path = (string) parse_url($url, PHP_URL_PATH);
    $basePath = base_path();
    if ($path === '' || !str_starts_with($path, $basePath)) {
      return NULL;
    }
    $file = DRUPAL_ROOT . '/' . rawurldecode(substr($path, strlen($basePath)));
    if (!is_file($file) || !is_readable($file)) {
      return NULL;
    }
    $image = @imagecreatefromstring((string) file_get_contents($file));
    return $image instanceof \GdImage ? $image : NULL;
  }

  /**
   * Returns the service worker script, with its version and settings filled in.
   *
   * A route rather than a static file, so the worker can control the whole
   * site (Service-Worker-Allowed) and change version whenever its code does.
   */
  public function serviceWorker(): Response {
    $script = (string) file_get_contents($this->moduleList->getPath('mukurtu_pwa') . '/js/sw.js');
    $settings = $this->configFactory->get('mukurtu_pwa.settings');
    $config = [
      'basePath' => base_path(),
      'offlineUrl' => Url::fromRoute('mukurtu_pwa.offline')->toString(),
      'uploadUrl' => Url::fromRoute('mukurtu_pwa.outbox_upload')->toString(),
      // Pages that open offline after one visit online.
      'offlinePages' => [
        Url::fromRoute('mukurtu_pwa.outbox')->toString(),
        Url::fromRoute('mukurtu_pwa.status')->toString(),
      ],
      'restrictedMaxAgeDays' => (int) ($settings->get('restricted_max_age_days') ?? 14),
    ];
    $script = strtr($script, [
      "'__VERSION__'" => json_encode(substr(hash('sha256', $script . json_encode($config)), 0, 12)),
      "'__CONFIG__'" => json_encode($config, JSON_UNESCAPED_SLASHES),
    ]);
    return new Response($script, 200, [
      'Content-Type' => 'text/javascript; charset=utf-8',
      'Service-Worker-Allowed' => base_path(),
      'Cache-Control' => 'no-cache',
    ]);
  }

  /**
   * The page shown offline when the requested page isn't saved.
   *
   * The service worker stores it when it installs. The list of saved items
   * is filled in on the device, from what that device has saved.
   */
  public function offline(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['mukurtu-pwa-offline']],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t("This page isn't saved on this device. You can still open the items below."),
      ],
      'empty' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t("You haven't saved any items to this device."),
        '#attributes' => ['class' => ['mukurtu-pwa-offline__empty']],
      ],
      'expired' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Some items need a connection again before you can open them.'),
        '#attributes' => ['class' => ['mukurtu-pwa-offline__expired'], 'hidden' => 'hidden'],
      ],
      'list' => [
        '#type' => 'html_tag',
        '#tag' => 'ul',
        '#value' => '',
        '#attributes' => ['class' => ['mukurtu-pwa-offline__list']],
      ],
      '#attached' => ['library' => ['mukurtu_pwa/offline']],
    ];
  }

}
