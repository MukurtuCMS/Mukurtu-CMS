<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Drops dashboard menu links whose target module has been uninstalled.
 *
 * Some modules the profile installs by default (Klaro, for example) can be
 * uninstalled per site. A static menu link to one of their routes would then
 * point at a route that no longer exists, so remove it from discovery instead.
 * Uninstalling a module rebuilds the router, which rediscovers menu links.
 */
class OptionalModuleMenuLinkHooks {

  /**
   * Menu link plugin IDs keyed to the module that provides their route.
   */
  const OPTIONAL_LINKS = [
    'mukurtu_core.klaro_settings' => 'klaro',
  ];

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(array &$links): void {
    $module_handler = \Drupal::moduleHandler();
    foreach (self::OPTIONAL_LINKS as $link_id => $module) {
      if (!$module_handler->moduleExists($module)) {
        unset($links[$link_id]);
      }
    }
  }

}
