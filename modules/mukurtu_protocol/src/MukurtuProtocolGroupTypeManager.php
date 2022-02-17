<?php

namespace Drupal\mukurtu_protocol;

use Drupal\og\GroupTypeManager;

/**
 * Override OG group content mapping for Mukurtu.
 */
class MukurtuProtocolGroupTypeManager extends GroupTypeManager {

  /**
   * {@inheritdoc}
   */
  public function getGroupBundleIdsByGroupContentBundle($group_content_entity_type_id, $group_content_bundle_id) {
    $bundles = [];

    // Anything with a protocol control field is protocol content.
    if (in_array($group_content_entity_type_id, ['node', 'media'])) {
      if ($this->groupAudienceHelper->hasGroupAudienceField($group_content_entity_type_id, $group_content_bundle_id)) {
        $bundles['protocol'] = ['protocol'];
      }
    }

    // Protocols are community content.
    if ($group_content_entity_type_id == 'protocol' && $group_content_bundle_id == 'protocol') {
      $bundles['community'] = ['community'];
    }

    return $bundles;
  }

}
