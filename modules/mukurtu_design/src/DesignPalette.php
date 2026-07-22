<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements callbacks for using a design palette.
 */
final class DesignPalette implements ContainerInjectionInterface {
  use StringTranslationTrait;

  const SETTINGS = 'mukurtu_design.settings';

  const CUSTOM_PALETTE_CSS_URI = 'public://mukurtu-design/custom-palette.css';

  /**
   * The available palette machine names, keyed to their labels.
   */
  const PALETTES = [
    'blue-gold' => 'Blue and gold',
    'red-bone' => 'Red and bone',
    'custom' => 'Custom',
  ];

  /**
   * Maps config "colors" keys to the CSS custom properties they populate.
   */
  const CSS_VAR_MAPPING = [
    'brand_primary' => '--brand-primary',
    'brand_primary_dark' => '--brand-primary-dark',
    'brand_primary_accent' => '--brand-primary-accent',
    'brand_secondary' => '--brand-secondary',
    'brand_secondary_dark' => '--brand-secondary-dark',
    'brand_secondary_accent' => '--brand-secondary-accent',
  ];

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The Admin Context service.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected AdminContext $adminContext;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $configFactory = $container->get('config.factory');
    $adminContext = $container->get('router.admin_context');

    assert($configFactory instanceof ConfigFactoryInterface);
    assert($adminContext instanceof AdminContext);
    return new self($configFactory, $adminContext);
  }

  /**
   * Constructor for DesignPalette.
   */
  public function __construct(ConfigFactoryInterface $configFactory, AdminContext $admin_context) {
    $this->configFactory = $configFactory;
    $this->adminContext = $admin_context;
  }

  /**
   * Set-ups the palette from the design form.
   *
   * @see hook_preprocess_html()
   * @see mukurtu_design_preprocess_html()
   */
  public function enablePalette(array &$attachments) {
    // We have tried several ways to load the css after the style.css.
    // First we were adding it on html.html.twig, but that implied caching
    // issues, and not a best practice.
    // Using attachments with html_head or html_head_link we couldn't include it
    // last. So the best way is actually ensuring the theme defines the
    // libraries, and then we can use weight on css to ensure it's loaded last.
    // @see mukurtu_v4_design_library_info_alter().
    // When https://www.drupal.org/project/drupal/issues/1945262 is solved it
    // will be even better with declarative dependencies.
    if ($this->adminContext->isAdminRoute()) {
      return;
    }

    $config = $this->configFactory->get(self::SETTINGS);
    $palette = $config->get('palette') ?? 'red-bone';
    $attachments['#attached']['library'][] = 'mukurtu_v4/palette.' . $palette;

    CacheableMetadata::createFromObject($config)->applyTo($attachments);
  }

  /**
   * Alter mukurtu_v4 theme libraries for adding one for each palette.
   */
  public function alterThemeLibraries(array &$libraries, $extension) {
    if ($extension !== 'mukurtu_v4') {
      return;
    }

    $libraries["palettes_demo"] = [
      'css' => [
        'theme' => [
          "css/00-base/palettes-demo/palettes-demo.css" => [
            'weight' => 1000000,
            'preprocess' => FALSE,
          ],
        ],
      ],
    ];
    foreach (array_keys(self::PALETTES) as $palette) {
      if ($palette === 'custom') {
        // Check if custom CSS file exists, if not generate it.
        $file_system = \Drupal::service('file_system');
        $css_real_path = $file_system->realpath(self::CUSTOM_PALETTE_CSS_URI);

        if (!$css_real_path || !file_exists($css_real_path)) {
          $colors = $this->configFactory->get(self::SETTINGS)->get('colors') ?? [];
          $this->generateCustomCss($colors);
        }

        // Custom palette uses generated CSS file from custom public directory.
        $libraries["palette.$palette"] = [
          'css' => [
            'theme' => [
              \Drupal::service('file_url_generator')->generateAbsoluteString(self::CUSTOM_PALETTE_CSS_URI) => [
                'weight' => 1000000,
                'preprocess' => FALSE,
              ],
            ],
          ],
        ];
      }
      else {
        // Standard palettes use theme CSS files.
        $libraries["palette.$palette"] = [
          'css' => [
            'theme' => [
              "css/00-base/palettes/$palette.css" => [
                'weight' => 1000000,
                'preprocess' => FALSE,
              ],
            ],
          ],
        ];
      }
    }
  }

  /**
   * Generates the custom palette CSS file from an array of hex colors.
   *
   * @param array $colors
   *   An array keyed by the self::CSS_VAR_MAPPING keys, with hex color
   *   string values.
   */
  public function generateCustomCss(array $colors): void {
    $css_vars = [];
    foreach (self::CSS_VAR_MAPPING as $key => $css_var) {
      if (!empty($colors[$key])) {
        $css_vars[$css_var] = $colors[$key];
      }
    }

    $css_content = ":root {\n";
    if (!empty($css_vars)) {
      foreach ($css_vars as $var_name => $color) {
        $css_content .= "  $var_name: $color;\n";
      }
    }
    else {
      $css_content .= "  /* No custom colors defined */\n";
    }
    $css_content .= "}\n";

    $file_system = \Drupal::service('file_system');
    $directory = dirname(self::CUSTOM_PALETTE_CSS_URI);
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY | $file_system::MODIFY_PERMISSIONS);
    file_put_contents(self::CUSTOM_PALETTE_CSS_URI, $css_content);
  }

}
