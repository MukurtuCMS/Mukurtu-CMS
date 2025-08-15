<?php

declare(strict_types=1);

namespace Drupal\mukurtu_design;

use Drupal\config_pages\ConfigPagesLoaderServiceInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements callbacks for using a design palette.
 */
final class DesignPalette implements ContainerInjectionInterface {
  use StringTranslationTrait;

  const CONFIG_PAGE_NAME = 'design_settings';

  const CONFIG_PAGE_PALETTE_FIELD = 'field_design_settings__palette';

  const CUSTOM_PALETTE_CSS_URI = 'public://mukurtu-design/custom-palette.css';

  /**
   * The config pages service.
   */
  protected ConfigPagesLoaderServiceInterface $configPages;

  /**
   * The Admin Context service.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected AdminContext $adminContext;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $configPages = $container->get('config_pages.loader');
    $adminContext = $container->get('router.admin_context');
    $entityTypeManager = $container->get('entity_type.manager');
    $entityFieldManager = $container->get('entity_field.manager');

    assert($configPages instanceof ConfigPagesLoaderServiceInterface);
    assert($adminContext instanceof AdminContext);
    assert($entityTypeManager instanceof EntityTypeManagerInterface);
    assert($entityFieldManager instanceof EntityFieldManagerInterface);
    return new self($configPages, $adminContext, $entityTypeManager, $entityFieldManager);
  }

  /**
   * Constructor for DesignPalette.
   */
  public function __construct(ConfigPagesLoaderServiceInterface $configPages, AdminContext $admin_context, EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager) {
    $this->configPages = $configPages;
    $this->adminContext = $admin_context;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
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

    $configPage = $this->configPages->load(self::CONFIG_PAGE_NAME);
    $configPagesEntityType = $this->entityTypeManager->getDefinition('config_pages');

    $palette = 'red-bone';
    if ($configPage && $configPage->hasField(self::CONFIG_PAGE_PALETTE_FIELD)) {
      $palette = $this->configPages->getValue($configPage, self::CONFIG_PAGE_PALETTE_FIELD, 0, 'value');
    }
    $attachments['#attached']['library'][] = 'mukurtu_v4/palette.' . $palette;

    // Add the list cache tag in the case where no config page has ever been
    // saved. This ensures that after a fresh site install that when you save
    // the form caches are invalidated and the palette is applied.
    if ($configPage === NULL) {
      (new CacheableMetadata())
        ->addCacheTags($configPagesEntityType->getListCacheTags())
        ->applyTo($attachments);
    }
    else {
      CacheableMetadata::createFromObject($configPage)
        ->applyTo($attachments);
    }
  }

  /**
   * Alter mukurtu_v4 theme libraries for adding one for each palette.
   */
  public function alterThemeLibraries(array &$libraries, $extension) {
    if ($extension !== 'mukurtu_v4') {
      return;
    }
    // We load the palettes from CONFIG_PAGE_PALETTE_FIELD field storage.
    $fieldStorageDefinitions = $this->entityFieldManager->getFieldStorageDefinitions('config_pages');

    $palettes = options_allowed_values($fieldStorageDefinitions[self::CONFIG_PAGE_PALETTE_FIELD]);

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
    foreach (array_keys($palettes) as $palette) {
      if ($palette === 'custom') {
        // Check if custom CSS file exists, if not generate it.
        $file_system = \Drupal::service('file_system');
        $css_real_path = $file_system->realpath(self::CUSTOM_PALETTE_CSS_URI);
        
        if (!$css_real_path || !file_exists($css_real_path)) {
          // Load design_settings config page and generate CSS.
          $configPage = $this->configPages->load(self::CONFIG_PAGE_NAME);
          if ($configPage) {
            _mukurtu_design_generate_custom_css($configPage);
          }
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
      } else {
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

}
