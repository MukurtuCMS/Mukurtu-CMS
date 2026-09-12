<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual;

use Drupal\config_translation\ConfigNamesMapper;

/**
 * Config translation mapper for the landing page's Layout Builder defaults.
 *
 * The landing_page display config is not reachable by Configuration
 * Translation's generic entity-type discovery, since entity_view_display has
 * no 'edit-form' link template. This mapper attaches translation routes to
 * Field UI's existing "Manage display" page instead
 * (entity.entity_view_display.node.default), which needs the {node_type}
 * route parameter supplied explicitly.
 */
class LandingPageLayoutConfigMapper extends ConfigNamesMapper {

  /**
   * {@inheritdoc}
   */
  public function getBaseRouteParameters() {
    return ['node_type' => 'landing_page'];
  }

}
