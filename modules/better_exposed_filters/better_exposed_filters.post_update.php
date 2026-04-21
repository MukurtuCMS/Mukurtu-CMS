<?php

/**
 * @file
 * Contains better_exposed_filters.post_update.combine_param.
 */

use Drupal\better_exposed_filters\BetterExposedFiltersConfigUpdater;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\views\ViewEntityInterface;

/**
 * If using combined sort, set the combine_param config to 'sort_bef_combine'.
 */
function better_exposed_filters_post_update_combine_param(?array &$sandbox = NULL): void {
  /** @var \Drupal\better_exposed_filters\BetterExposedFiltersConfigUpdater $config_updater */
  $config_updater = \Drupal::classResolver(BetterExposedFiltersConfigUpdater::class);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($config_updater): bool {
    return $config_updater->updateCombineParam($view);
  });
}

/**
 * Add soft limit param keys.
 */
function better_exposed_filters_post_update_soft_limit(?array &$sandbox = NULL): void {
  /** @var \Drupal\better_exposed_filters\BetterExposedFiltersConfigUpdater $config_updater */
  $config_updater = \Drupal::classResolver(BetterExposedFiltersConfigUpdater::class);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($config_updater): bool {
    return $config_updater->updateSoftLimitParams($view);
  });
}

/**
 * Add treat_as_false param key.
 */
function better_exposed_filters_post_update_single_checkbox_param_key(?array &$sandbox = NULL): void {
  /** @var \Drupal\better_exposed_filters\BetterExposedFiltersConfigUpdater $config_updater */
  $config_updater = \Drupal::classResolver(BetterExposedFiltersConfigUpdater::class);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($config_updater): bool {
    return $config_updater->updateSingleCheckboxFilters($view);
  });
}

/**
 * Set default value for new "open_by_default" option.
 */
function better_exposed_filters_post_update_open_by_default_param_key(?array &$sandbox = NULL): void {
  /** @var \Drupal\better_exposed_filters\BetterExposedFiltersConfigUpdater $config_updater */
  $config_updater = \Drupal::classResolver(BetterExposedFiltersConfigUpdater::class);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($config_updater): bool {
    return $config_updater->updateAddOpenByDefaultKey($view);
  });
}

/**
 * Set default value for new "field_classes" option.
 */
function better_exposed_filters_post_update_field_classes_param_key(?array &$sandbox = NULL): void {
  /** @var \Drupal\better_exposed_filters\BetterExposedFiltersConfigUpdater $config_updater */
  $config_updater = \Drupal::classResolver(BetterExposedFiltersConfigUpdater::class);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($config_updater): bool {
    return $config_updater->updateAddFieldClassesKey($view);
  });
}

/**
 * Add new slider tooltip keys.
 */
function better_exposed_filters_post_update_slider_tooltip_keys(?array &$sandbox = NULL): void {
  /** @var \Drupal\better_exposed_filters\BetterExposedFiltersConfigUpdater $config_updater */
  $config_updater = \Drupal::classResolver(BetterExposedFiltersConfigUpdater::class);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($config_updater): bool {
    return $config_updater->updateSliderTooltipKeys($view);
  });
}

/**
 * Add new autosubmit_breakpoint keys.
 */
function better_exposed_filters_post_update_autosubmit_breakpoint_key(?array &$sandbox = NULL): void {
  /** @var \Drupal\better_exposed_filters\BetterExposedFiltersConfigUpdater $config_updater */
  $config_updater = \Drupal::classResolver(BetterExposedFiltersConfigUpdater::class);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($config_updater): bool {
    return $config_updater->updateAutoSubmitBreakpoint($view);
  });
}

/**
 * Add new auto_submit_sort_only keys.
 */
function better_exposed_filters_post_update_autosubmit_sort_only_key(?array &$sandbox = NULL): void {
  /** @var \Drupal\better_exposed_filters\BetterExposedFiltersConfigUpdater $config_updater */
  $config_updater = \Drupal::classResolver(BetterExposedFiltersConfigUpdater::class);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($config_updater): bool {
    return $config_updater->updateAutoSubmitSortOnlyKey($view);
  });
}

/**
 * Set default values for new sort options settings.
 */
function better_exposed_filters_post_update_sort_options_defaults(?array &$sandbox = NULL): void {
  /** @var \Drupal\better_exposed_filters\BetterExposedFiltersConfigUpdater $config_updater */
  $config_updater = \Drupal::classResolver(BetterExposedFiltersConfigUpdater::class);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($config_updater): bool {
    return $config_updater->updateSortOptionsDefaults($view);
  });
}

/**
 * Add new placement_location key.
 */
function better_exposed_filters_post_update_slider_placement_key(?array &$sandbox = NULL): void {
  /** @var \Drupal\better_exposed_filters\BetterExposedFiltersConfigUpdater $config_updater */
  $config_updater = \Drupal::classResolver(BetterExposedFiltersConfigUpdater::class);
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view) use ($config_updater): bool {
    return $config_updater->updateSliderPlacementKey($view);
  });
}
