<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Marks the search_api_field Views field's "rewrite results" text as
 * non-translatable (issue #1638).
 *
 * mukurtu_browse_by_map and its mukurtu_solr equivalent each rewrite two
 * fields (uuid, nid) to `<div class="uuid visually-hidden">{{ uuid }}
 * </div>` (and the nid equivalent) - hidden, machine-readable tokens for
 * JS to read node identity on the map, not visible text. Search API's own
 * schema (views.field.search_api_field) inherits this "rewrite text"
 * value from Views' generic views_field type as `type: text`, which core
 * marks translatable - so Drupal's locale tooling scans it and flags the
 * <div> tag as disallowed in a translatable string, even though
 * translating it would never change anything.
 *
 * Confirmed (2026-08-28) this is safe to apply profile-wide rather than
 * scoping to just those 2 views: swept every shipped views.view.*.yml for
 * a search_api_field-plugin field with alter_text enabled and a non-empty
 * value - these are the only two non-empty instances anywhere in the
 * profile, so no other view's use of this plugin's rewrite-text is
 * losing real translatability here.
 */
class ViewsSchemaHooks {

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(array &$definitions): void {
    if (isset($definitions['views.field.search_api_field']['mapping']['alter']['mapping']['text'])) {
      $definitions['views.field.search_api_field']['mapping']['alter']['mapping']['text']['type'] = 'string';
    }
  }

}
