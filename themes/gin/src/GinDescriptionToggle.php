<?php

namespace Drupal\gin;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

include_once __DIR__ . '/../gin.theme';
_gin_include_theme_includes();

/**
 * Service to handle toggling form descriptions.
 */
class GinDescriptionToggle implements ContainerInjectionInterface {


  /**
   * The content form helper class.
   *
   * @var \Drupal\gin\GinContentFormHelper
   */
  protected $contentFormHelper;

  /**
   * The gin theme settings class.
   *
   * @var \Drupal\gin\GinSettings
   */
  protected $ginSettings;

  /**
   * GinDescriptionToggle constructor.
   *
   * @param \Drupal\gin\GinSettings $ginSettings
   *   The gin theme settings class.
   * @param \Drupal\gin\GinContentFormHelper $contentFormHelper
   *   The content form helper class.
   */
  public function __construct(GinSettings $ginSettings, GinContentFormHelper $contentFormHelper) {
    $this->ginSettings = $ginSettings;
    $this->contentFormHelper = $contentFormHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $classResolver = $container->get('class_resolver');

    return new static(
      $classResolver->getInstanceFromDefinition(GinSettings::class),
      $classResolver->getInstanceFromDefinition(GinContentFormHelper::class),
    );
  }

  /**
   * Generic preprocess enabling toggle.
   *
   * @param array $variables
   *   The variables array (modify in place).
   */
  public function preprocess(array &$variables) {
    if ($this->isEnabled() || (isset($variables['element']['#description_toggle']) && $variables['element']['#description_toggle'])) {
      if (!empty($variables['description'])) {
        $variables['description_display_toggle'] = $variables['description_display'] ?? 'after';
        $variables['description_display'] = 'invisible';
        $variables['description_toggle'] = TRUE;
      }
      // Add toggle for text_format, description is in wrapper.
      elseif (!empty($variables['element']['#description_toggle'])) {
        $variables['description_toggle'] = TRUE;
      }
    }
  }

  /**
   * Functionality is enabled via setting on content forms.
   *
   * @return bool
   *   Wether feature is enabled or not.
   */
  public function isEnabled() {
    return $this->ginSettings->get('show_description_toggle') && $this->contentFormHelper->isContentForm();
  }

}
