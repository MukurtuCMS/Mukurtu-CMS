<?php

namespace Drupal\better_exposed_filters;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewEntityInterface;

/**
 * Provides a BC layer for modules providing old configurations.
 *
 * @internal
 */
class BetterExposedFiltersConfigUpdater {

  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * Add sort_bef_combine to views.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view was updated.
   */
  public function updateCombineParam(ViewEntityInterface $view): bool {
    $changed = FALSE;
    // Go through each display on each view.
    $displays = $view->get('display');
    foreach ($displays as $display) {
      if (isset($display['display_options']['exposed_form']['type'])) {
        if ($display['display_options']['exposed_form']['type'] == 'bef') {
          $advanced_sort_options = $display['display_options']['exposed_form']['options']['bef']['sort']['advanced'] ?? NULL;
          if (isset($advanced_sort_options)) {
            if ($advanced_sort_options['combine'] === TRUE) {
              // Update the "combine_param" key if "combine" is true.
              $combine_param = 'sort_bef_combine';
              // Write the updated options back to the display.
              $display_id = $display['id'];
              $view->set("display.$display_id.display_options.exposed_form.options.bef.sort.advanced.combine_param", $combine_param);
              try {
                $view->save();
                $changed = TRUE;
              }
              catch (EntityStorageException) {
                $this->getLogger('better_exposed_filters')->error('Error saving @view_id', ['@view_id' => $view->id()]);
              }
            }
          }
        }
      }
    }
    return $changed;
  }

  /**
   * Add soft_limit params to views.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view as updated.
   */
  public function updateSoftLimitParams(ViewEntityInterface $view): bool {
    $changed = FALSE;
    // Go through each display on each view.
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (isset($display['display_options']['exposed_form']['type'])) {
        if ($display['display_options']['exposed_form']['type'] == 'bef') {
          $exposed_form = $display['display_options']['exposed_form'];

          $bef_settings = $exposed_form['options']['bef'];
          if (isset($bef_settings['filter'])) {
            foreach ($bef_settings['filter'] as $filter_id => $settings) {
              if (!in_array($settings['plugin_id'], ['bef_links', 'bef'])) {
                // "soft_limit" is only supported for links and
                // checkboxes/radios.
                continue;
              }
              if (isset($settings['soft_limit'])) {
                // "soft_limit" option already configured.
                continue;
              }
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['soft_limit'] = 0;
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['soft_limit_label_less'] = $this->t('Show less');
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['soft_limit_label_more'] = $this->t('Show more');
              $changed = TRUE;
            }
          }
        }
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Add treat_as_false params to views.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view as updated.
   */
  public function updateSingleCheckboxFilters(ViewEntityInterface $view): bool {
    $changed = FALSE;
    // Go through each display on each view.
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (isset($display['display_options']['exposed_form']['type'])) {
        if ($display['display_options']['exposed_form']['type'] == 'bef') {
          $exposed_form = $display['display_options']['exposed_form'];

          $bef_settings = $exposed_form['options']['bef'];
          if (isset($bef_settings['filter'])) {
            foreach ($bef_settings['filter'] as $filter_id => $settings) {
              if ($settings['plugin_id'] != 'bef_single') {
                continue;
              }
              if (isset($settings['treat_as_false'])) {
                continue;
              }
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['treat_as_false'] = FALSE;
              $changed = TRUE;
            }
          }
        }
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Set default value for new "open_by_default" option.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view as updated.
   */
  public function updateAddOpenByDefaultKey(ViewEntityInterface $view): bool {
    $changed = FALSE;
    // Go through each display on each view.
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (isset($display['display_options']['exposed_form']['type'])) {
        if ($display['display_options']['exposed_form']['type'] == 'bef') {
          $exposed_form = $display['display_options']['exposed_form'];

          $bef_settings = $exposed_form['options']['bef'];
          if (isset($bef_settings['filter'])) {
            foreach ($bef_settings['filter'] as $filter_id => $settings) {
              if (isset($settings['advanced']['open_by_default'])) {
                continue;
              }
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['advanced']['open_by_default'] = FALSE;
              $changed = TRUE;
            }
          }
        }
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Set default value for new "field_classes" option.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view as updated.
   */
  public function updateAddFieldClassesKey(ViewEntityInterface $view): bool {
    $changed = FALSE;
    // Go through each display on each view.
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (isset($display['display_options']['exposed_form']['type'])) {
        if ($display['display_options']['exposed_form']['type'] == 'bef') {
          $exposed_form = $display['display_options']['exposed_form'];

          $bef_settings = $exposed_form['options']['bef'];
          if (isset($bef_settings['filter'])) {
            foreach ($bef_settings['filter'] as $filter_id => $settings) {
              if (isset($settings['advanced']['field_classes'])) {
                continue;
              }
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['advanced']['field_classes'] = '';
              $changed = TRUE;
            }
          }
        }
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Add new slider tooltip keys.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view as updated.
   */
  public function updateSliderTooltipKeys(ViewEntityInterface $view): bool {
    $changed = FALSE;
    // Go through each display on each view.
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (isset($display['display_options']['exposed_form']['type'])) {
        if ($display['display_options']['exposed_form']['type'] == 'bef') {
          $exposed_form = $display['display_options']['exposed_form'];

          $bef_settings = $exposed_form['options']['bef'];
          if (isset($bef_settings['filter'])) {
            foreach ($bef_settings['filter'] as $filter_id => $settings) {
              if ($settings['plugin_id'] != 'bef_sliders') {
                continue;
              }
              if (isset($settings['enable_tooltips'])) {
                continue;
              }
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['enable_tooltips'] = FALSE;
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['tooltips_value_prefix'] = '';
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['tooltips_value_suffix'] = '';
              $changed = TRUE;
            }
          }
        }
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Add new autosubmit_breakpoint keys.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view as updated.
   */
  public function updateAutoSubmitBreakpoint(ViewEntityInterface $view): bool {
    $changed = FALSE;
    // Go through each display on each view.
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (isset($display['display_options']['exposed_form']['type'])) {
        if ($display['display_options']['exposed_form']['type'] == 'bef') {
          $exposed_form = $display['display_options']['exposed_form'];

          $bef_settings = $exposed_form['options']['bef'];
          if (isset($bef_settings['general']['autosubmit_breakpoint'])) {
            // "autosubmit_breakpoint" option already configured.
            continue;
          }
          $display['display_options']['exposed_form']['options']['bef']['general']['autosubmit_breakpoint'] = '';
          $changed = TRUE;
        }
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Add new auto_submit_sort_only key.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view as updated.
   */
  public function updateAutoSubmitSortOnlyKey(ViewEntityInterface $view): bool {
    $changed = FALSE;
    // Go through each display on each view.
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (isset($display['display_options']['exposed_form']['type'])) {
        if ($display['display_options']['exposed_form']['type'] == 'bef') {
          $exposed_form = $display['display_options']['exposed_form'];

          $bef_settings = $exposed_form['options']['bef'];
          if (isset($bef_settings['general']['auto_submit_sort_only'])) {
            // "auto_submit_sort_only" option already configured.
            continue;
          }
          $display['display_options']['exposed_form']['options']['bef']['general']['auto_submit_sort_only'] = FALSE;
          $changed = TRUE;
        }
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Set default values for new sort options settings.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view as updated.
   */
  public function updateSortOptionsDefaults(ViewEntityInterface $view): bool {
    $changed = FALSE;
    // Go through each display on each view.
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (isset($display['display_options']['exposed_form']['type'])) {
        if ($display['display_options']['exposed_form']['type'] == 'bef') {
          $exposed_form = $display['display_options']['exposed_form'];

          $bef_settings = $exposed_form['options']['bef'];
          if (isset($bef_settings['filter'])) {
            foreach ($bef_settings['filter'] as $filter_id => $settings) {
              if (isset($settings['advanced']['sort_options_method'])) {
                // "sort_options_method" option already configured.
                continue;
              }
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['advanced']['sort_options_method'] = 'alphabetical_asc';
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['advanced']['sort_options_natural'] = TRUE;
              $changed = TRUE;
            }
          }
        }
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Add new slider placement_location keys.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view as updated.
   */
  public function updateSliderPlacementKey(ViewEntityInterface $view): bool {
    $changed = FALSE;
    // Go through each display on each view.
    $displays = $view->get('display');
    foreach ($displays as &$display) {
      if (isset($display['display_options']['exposed_form']['type'])) {
        if ($display['display_options']['exposed_form']['type'] == 'bef') {
          $exposed_form = $display['display_options']['exposed_form'];

          $bef_settings = $exposed_form['options']['bef'];
          if (isset($bef_settings['filter'])) {
            foreach ($bef_settings['filter'] as $filter_id => $settings) {
              if ($settings['plugin_id'] != 'bef_sliders') {
                continue;
              }
              if (isset($settings['placement_location'])) {
                continue;
              }
              $display['display_options']['exposed_form']['options']['bef']['filter'][$filter_id]['placement_location'] = 'end';
              $changed = TRUE;
            }
          }
        }
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

}
