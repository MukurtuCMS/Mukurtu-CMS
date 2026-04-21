<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

/**
 * Hook implementation.
 */
class Theme {

  /**
   * The list of overwritten.
   *
   * @var array|string[]
   */
  protected array $overwrittenThemes = [
    'file/file-managed-file',
    'file/file-widget-multiple',
    'form/checkboxes',
    'form/field-multiple-value-form',
    'form/form',
    'form/form-element',
    'form/form-element-label',
    'form/input--checkbox',
    'form/input--checkbox--toggle',
    'form/input',
    'form/radios',
    'form/select',
    'form/text-format-wrapper',
    'form/textarea',
    'image/image-widget',
    'media/media--media-library',
    'media_library/media-library-element',
    'media_library/media-library-item',
    'media_library/media-library-wrapper',
    'system/container',
    'system/container--media-library-content',
    'system/container--media-library-widget-selection',
    'system/details',
    'system/details--media-library-add-form-selected-media',
    'system/fieldset',
    'system/item-list',
    'system/item-list--media-library-add-form-media-list',
    'system/links',
    'system/links--media-library-menu',
    'system/pager',
    'system/status-messages',
    'system/table',
    'toolbar/toolbar',
    'views/views-mini-pager',
    'views/views-view--media-library',
    'views/views-view-fields',
    'views/views-view-table',
    'views/views-view-unformatted--media-library',
  ];

  /**
   * Hook implementation.
   *
   * @return array
   *   An associative array of information about theme implementations.
   */
  public function themes(): array {
    $themes = [];
    foreach ($this->overwrittenThemes as $overwritten_theme) {
      $overwritten_hook_array = \explode(
        '/',
        \str_replace('-', '_', $overwritten_theme)
      );
      $overwritten_hook = $overwritten_hook_array[\count($overwritten_hook_array) - 1];
      $overwritten_base_hook = \explode('--', $overwritten_hook)[0];

      $themes[$overwritten_hook . '__gin_lb'] = [
        'template' => $overwritten_theme . '--gin-lb',
        'base hook' => $overwritten_base_hook,
      ];
    }

    $themes['form__layout_builder_form__gin_lb'] = [
      'template' => 'form/form--layout-builder-form--gin-lb',
      'base hook' => 'form',
    ];

    $themes['gin_lb_form_actions'] = [
      'variables' => [
        'preview_region' => FALSE,
        'preview_content' => TRUE,
      ],
      'template' => 'top_bar/gin-lb-form-actions',
    ];

    return $themes;
  }

}
