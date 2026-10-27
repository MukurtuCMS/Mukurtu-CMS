<?php

declare(strict_types=1);

namespace Drupal\mukurtu_protocol\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Labels the single-membership pages as removing a member, not deleting.
 *
 * The confirm form itself is MukurtuOgMembershipDeleteForm. Around it, OG
 * labels the page's tab "Delete", and Gin ends the breadcrumb with
 * "Delete Cultural Protocol" (or "Edit Cultural Protocol" on the roles page),
 * because Gin names the operation after the first entity in the route, which
 * is the group rather than the membership (#2009).
 *
 * @see \Drupal\mukurtu_protocol\Form\MukurtuOgMembershipDeleteForm
 */
class MembershipPageLabelHooks {

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(array &$local_tasks): void {
    if (isset($local_tasks['entity.og_membership.delete_form'])) {
      $local_tasks['entity.og_membership.delete_form']['title'] = new TranslatableMarkup('Remove');
    }
  }

  /**
   * Implements hook_theme_registry_alter().
   *
   * Module preprocess hooks run before theme ones, so a breadcrumb preprocess
   * hook here would run before Gin adds the item it needs to relabel. Adding
   * the callback to the end of the list runs it after Gin's instead.
   */
  #[Hook('theme_registry_alter')]
  public function themeRegistryAlter(array &$theme_registry): void {
    if (isset($theme_registry['breadcrumb'])) {
      $theme_registry['breadcrumb']['preprocess functions'][] = static::class . '::preprocessBreadcrumb';
    }
  }

  /**
   * Relabels the last breadcrumb item on the single-membership pages.
   *
   * @param array $variables
   *   The breadcrumb template variables.
   */
  public static function preprocessBreadcrumb(array &$variables): void {
    $labels = [
      'entity.og_membership.delete_form' => new TranslatableMarkup('Remove member'),
      'entity.og_membership.canonical' => new TranslatableMarkup('Manage roles'),
      'entity.og_membership.edit_form' => new TranslatableMarkup('Manage roles'),
    ];
    $route_name = \Drupal::routeMatch()->getRouteName();
    if (!isset($labels[$route_name]) || empty($variables['breadcrumb'])) {
      return;
    }

    // Gin adds the operation as an unlinked item at the end. Other themes do
    // not, and their last item is a real link that should be left alone.
    $last = array_key_last($variables['breadcrumb']);
    if (($variables['breadcrumb'][$last]['url'] ?? NULL) === '') {
      $variables['breadcrumb'][$last]['text'] = $labels[$route_name];
    }
  }

}
