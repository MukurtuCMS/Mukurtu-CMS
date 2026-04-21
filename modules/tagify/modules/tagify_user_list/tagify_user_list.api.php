<?php

/**
 * @file
 * Hooks provided by the Tagify User List module.
 */

/**
 * Alter the Tagify autocomplete matches for the user widget.
 *
 * @param array $matches
 *   An array of autocomplete matches. The array keys are entity IDs and the
 *    values are string labels.
 * @param array $options
 *   The entity reference selection handler options used to generate the
 *   matches.
 *
 * @deprecated in tagify:8.x-1.0 and is removed from tagify:8.x-2.0.
 *   Use hook_tagify_autocomplete_match_alter() instead.
 * @see https://www.drupal.org/project/tagify/issues/3437617
 */
function hook_tagify_user_list_autocomplete_matches_alter(array &$matches, array $options): void {
}
