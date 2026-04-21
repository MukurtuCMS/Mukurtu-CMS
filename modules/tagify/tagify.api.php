<?php

/**
 * @file
 * Hooks provided by the Tagify module.
 */

/**
 * Alter a tagify autocomplete match.
 *
 * @param string|null $label
 *   The matched label. Set to NULL to exclude the match.
 * @param string|null $info_label
 *   The extra information to be shown aside the entity label.
 * @param array $context
 *   An array of context data. The following keys are always available:
 *     - entity: The entity object.
 *     - info_label: The info label, but without token replacement.
 */
function hook_tagify_autocomplete_match_alter(?string &$label, ?string &$info_label, array $context): void {
}

/**
 * Alter the Tagify autocomplete matches.
 *
 * @param array $matches
 *   An array of autocomplete matches. The array keys are entity IDs and the
 *   values are string labels.
 * @param array $options
 *   The entity reference selection handler options used to generate the
 *   matches.
 *
 * @deprecated in tagify:8.x-1.0 and is removed from tagify:8.x-2.0.
 *   Use hook_tagify_autocomplete_match_alter() instead.
 * @see https://www.drupal.org/project/tagify/issues/3437617
 */
function hook_tagify_autocomplete_matches_alter(array &$matches, array $options): void {
}
