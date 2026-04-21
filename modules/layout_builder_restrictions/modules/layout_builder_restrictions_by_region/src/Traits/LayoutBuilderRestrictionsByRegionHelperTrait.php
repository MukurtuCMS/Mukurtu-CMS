<?php

namespace Drupal\layout_builder_restrictions_by_region\Traits;

/**
 * Methods to help Layout Builder Restrictions By Region plugin.
 */
trait LayoutBuilderRestrictionsByRegionHelperTrait {

  /**
   * Checks if any restrictions are enabled for a given region.
   *
   * Either $static_id or $entity_view_display_id is required.
   *
   * @param string $layout_plugin
   *   The machine name of the layout plugin.
   * @param string $region_id
   *   The machine name of the region.
   * @param mixed $static_id
   *   (optional) A unique string representing a built form; optionally NULL.
   * @param mixed $entity_view_display_id
   *   (optional) The ID of the entity view display; optionally NULL.
   *
   * @return bool
   *   A boolean indicating whether or not a region has restrictions.
   */
  protected function regionRestrictionStatus(string $layout_plugin, string $region_id, $static_id = NULL, $entity_view_display_id = NULL) {
    if (is_null($static_id) && is_null($entity_view_display_id)) {
      throw new \Exception("Either a static ID or a entity view display ID must be provided.");
    }
    $region_categories = NULL;
    $region_restricted = FALSE;

    // Attempt to retrieve config from tempstore.
    $tempstore = $this->privateTempStoreFactory();
    $store = $tempstore->get('layout_builder_restrictions_by_region');
    // If tempstore return is null, then no record is found.
    // If tempstore returns something other than null, then a record is found.
    // If tempstore returns an empty array, then a record is found with
    // no restrictions.
    $region_categories = $store->get($static_id . ':' . $layout_plugin . ':' . $region_id);

    if (!is_null($region_categories)) {
      $region_restricted = (empty($region_categories)) ? FALSE : TRUE;
    }
    // If no record in tempstore, then check stored config.
    else {
      $display = $this->entityTypeManager()
        ->getStorage('entity_view_display')
        ->load($entity_view_display_id);

      $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction_by_region', []);
      if (isset($third_party_settings['restricted_categories'][$layout_plugin][$region_id])) {
        return TRUE;
      }
      if (isset($third_party_settings['allowlisted_blocks'][$layout_plugin][$region_id])) {
        return TRUE;
      }
      elseif (isset($third_party_settings['denylisted_blocks'][$layout_plugin][$region_id])) {
        if (!empty($third_party_settings['denylisted_blocks'][$layout_plugin][$region_id])) {
          return TRUE;
        }
        else {
          return FALSE;
        }
      }
      return FALSE;
    }

    return $region_restricted;
  }

  /**
   * Wrapper function for regionRestrictionStatus() that returns a string.
   *
   * Either $static_id or $entity_view_display_id is required.
   *
   * @param string $layout_plugin
   *   The machine name of the layout plugin.
   * @param string $region_id
   *   The machine name of the region.
   * @param mixed $static_id
   *   (optional) A unique string representing a built form; optionally NULL.
   * @param mixed $entity_view_display_id
   *   (optional) The ID of the entity view display; optionally NULL.
   *
   * @return string
   *   Either 'Restricted' or 'Unrestricted'.
   */
  protected function regionRestrictionStatusString(string $layout_plugin, string $region_id, $static_id = NULL, $entity_view_display_id = NULL) {
    $restriction = $this->regionRestrictionStatus($layout_plugin, $region_id, $static_id, $entity_view_display_id);
    if ($restriction == TRUE) {
      return 'Restricted';
    }
    elseif ($restriction == FALSE) {
      return 'Unrestricted';
    }
  }

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManager
   *   Manages entity type plugin definitions.
   */
  protected function entityTypeManager() {
    return $this->entityTypeManager ?? \Drupal::service('entity_type.manager');
  }

  /**
   * Gets the private tempStore.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStoreFactory
   *   Creates a private temporary storage for a collection.
   */
  protected function privateTempStoreFactory() {
    return $this->privateTempStoreFactory ?? \Drupal::service('tempstore.private');
  }

}
