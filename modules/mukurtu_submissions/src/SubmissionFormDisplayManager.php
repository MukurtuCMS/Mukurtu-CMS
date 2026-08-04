<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\mukurtu_media\MediaTypeExtensions;
use Drupal\mukurtu_submissions\Entity\SubmissionSettingsInterface;
use Drupal\mukurtu_submissions\Form\PublicSubmissionForm;

/**
 * Provisions a target bundle's "submission" form display, shared by
 * SubmissionSettingsForm (when a settings entity is created through the
 * admin UI) and MukurtuSubmissionsCommands (when one is bulk-created via
 * Drush) so the two never drift out of sync on what "all fields, ready to
 * use" means for a freshly enabled bundle.
 */
class SubmissionFormDisplayManager {

  public function __construct(
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Creates a "submission" form display for the target bundle if none
   * exists yet, so a newly enabled content type is usable immediately
   * instead of being blocked by SubmissionAccessCheck's missing-display
   * safeguard. Seeded from whichever fields the bundle already exposes -
   * EntityDisplayRepository::getFormDisplay() auto-populates a fresh,
   * unsaved "submission" mode with every field, same as it would for any
   * other not-yet-configured form mode - minus the fields the public form
   * never shows regardless of bundle.
   *
   * Note: "revision_log" doesn't declare itself form-display-configurable
   * (unlike uid/created/status/path/moderation_state, which do), so Drupal
   * silently re-adds it as visible on every load regardless of what's
   * hidden here or in Field UI - PublicSubmissionForm::buildForm() strips
   * it again at render time, which is what actually keeps it off the
   * public form; the removeComponent() call here mainly keeps this
   * display's saved config consistent with what PublicSubmissionForm
   * enforces for every other excluded field.
   */
  public function ensureSubmissionFormDisplay(SubmissionSettingsInterface $settings): void {
    $entity_type_id = $settings->getTargetEntityTypeId();
    $bundle = $settings->getTargetBundle();
    $display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'submission');
    if (!$display->isNew()) {
      return;
    }

    foreach (PublicSubmissionForm::EXCLUDED_FIELDS as $excluded_field) {
      $display->removeComponent($excluded_field);
    }

    // A field_config-backed field's own getDisplayOptions() defaults to
    // ['region' => 'hidden'] - Drupal core only auto-populates a fresh,
    // unconfigured display with a visible component for fields that
    // explicitly declare their own default display options (chiefly base
    // fields like "title"). Left alone, every custom "field_"-prefixed
    // field would stay hidden here, defeating the "every field included by
    // default" promise this method exists to keep. So every display-
    // configurable, non-excluded field is forced visible below: reusing
    // whatever component it already has, falling back to the bundle's
    // "default" form mode's widget for it (the same fallback
    // syncIncludedFields() uses when an admin later re-includes a field
    // from the fields table), and finally to EntityDisplayBase::
    // setComponent()'s own defaults (the field type's default widget) if
    // neither display has ever configured one.
    //
    // Any entity-reference-to-media field (field_media_assets and its
    // like, on any bundle) gets our own simple upload widget instead of
    // whatever the "default" mode uses (normally the Media Library picker)
    // - see applySimpleMediaUploadWidget().
    $default_display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'default');
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $definition) {
      if (in_array($field_name, PublicSubmissionForm::EXCLUDED_FIELDS, TRUE) || !$definition->isDisplayConfigurable('form')) {
        continue;
      }
      $component = $display->getComponent($field_name) ?: ($default_display->getComponent($field_name) ?: []);
      $display->setComponent($field_name, $this->applySimpleMediaUploadWidget($field_name, $component, $entity_type_id, $bundle));
    }

    $display->save();
  }

  /**
   * Seeds a settings entity's field_groups/field_group_assignments from
   * the target bundle's regular "default" form display's own field_group
   * (contrib module) arrangement, so a bundle's submission form starts
   * organized the same way its full content-edit form already is, rather
   * than as one long flat list of fields. Does nothing if the entity has
   * no such arrangement to copy (mutates nothing, caller decides whether
   * to save).
   *
   * field_group stores its tree as `third_party_settings.field_group` on
   * the display itself (nested groups, arbitrary depth, each entry a tab,
   * tab-strip, or a collapsible "details" section) - a richer model than
   * this module's own field_groups schema needs, since PublicSubmissionForm::
   * groupFields() only ever renders plain nested <details> (no tabs
   * concept at all). This method:
   * - Drops every "tabs" (plural - the tab-strip container itself, as
   *   opposed to "tab", one tab within it) group at any nesting depth and
   *   reparents its children to its own nearest non-"tabs" ancestor (or
   *   top-level, if none) - matches how Digital Heritage's own
   *   hand-curated groups are structured (top-level "Mukurtu Essentials"
   *   etc., no outer tab-strip wrapper), rather than nesting everything
   *   one level deeper than necessary for a UI that can't render tabs
   *   anyway.
   * - Maps each group's collapsed state from whichever settings key its
   *   own format plugin actually uses: the "details" formatter stores a
   *   boolean `open`, while "tab"/"tabs"/"accordion_item" store a string
   *   `formatter` ("open"/"closed") instead - two different schemas, not
   *   one convention, confirmed against field_group's own config schema.
   * - Skips any EXCLUDED_FIELDS field entirely, even if the default form
   *   groups it (e.g. field_cultural_protocols) - the public form would
   *   never render it regardless.
   * - Prunes any group left with nothing in its subtree afterward (a
   *   group whose only children were excluded fields), so the settings
   *   form's "Field groups" list never shows an empty, pointless section.
   */
  public function seedFieldGroupsFromDefaultForm(SubmissionSettingsInterface $settings): void {
    $entity_type_id = $settings->getTargetEntityTypeId();
    $bundle = $settings->getTargetBundle();
    $default_display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'default');
    $source_groups = $default_display->getThirdPartySettings('field_group');
    if (!$source_groups) {
      return;
    }

    $is_wrapper = fn(string $group_id): bool => isset($source_groups[$group_id]) && ($source_groups[$group_id]['format_type'] ?? '') === 'tabs';
    // Guards against a cyclic parent_name chain in the source data (should
    // never happen in practice - field_group's own UI doesn't allow a
    // group to be its own ancestor - but SubmissionSettingsForm::
    // breakGroupParentCycles() takes the same precaution for user-entered
    // groups, so this data, sourced from a different config entirely,
    // gets the same defense-in-depth rather than risking infinite
    // recursion on a malformed or hand-edited default form display).
    $resolve_parent = function (string $parent_name, array $visited = []) use (&$resolve_parent, $source_groups, $is_wrapper): string {
      if ($parent_name === '' || !isset($source_groups[$parent_name]) || isset($visited[$parent_name])) {
        return '';
      }
      if (!$is_wrapper($parent_name)) {
        return $parent_name;
      }
      $visited[$parent_name] = TRUE;
      return $resolve_parent($source_groups[$parent_name]['parent_name'] ?? '', $visited);
    };

    $field_names = array_flip(array_keys($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)));

    $field_groups = [];
    $assignments = [];
    foreach ($source_groups as $group_id => $group) {
      if ($is_wrapper($group_id)) {
        continue;
      }
      $field_groups[$group_id] = [
        'id' => $group_id,
        'label' => $group['label'] ?? $group_id,
        'collapsed' => $this->isFieldGroupCollapsed($group),
        'parent' => $resolve_parent($group['parent_name'] ?? ''),
      ];

      foreach ($group['children'] ?? [] as $child) {
        // Child groups get their own field_groups entry above (or were
        // dropped and reparented, if they're themselves a "tabs" wrapper)
        // - only actual fields get assigned here.
        if (isset($source_groups[$child])) {
          continue;
        }
        if (in_array($child, PublicSubmissionForm::EXCLUDED_FIELDS, TRUE) || !isset($field_names[$child])) {
          continue;
        }
        $assignments[$child] = $group_id;
      }
    }

    $field_groups = $this->pruneEmptyGroups($field_groups, $assignments);

    $settings->set('field_groups', array_values($field_groups));
    $settings->set('field_group_assignments', $assignments);
  }

  /**
   * Removes any currently-EXCLUDED_FIELDS entry from a settings entity's
   * field_group_assignments, and prunes any group left with nothing in its
   * subtree as a result - for when a field that was previously assignable
   * (and already saved into a settings entity's config) gets added to
   * EXCLUDED_FIELDS later. seedFieldGroupsFromDefaultForm() already keeps
   * a *freshly generated* arrangement clean of excluded fields; this is
   * the equivalent cleanup for arrangements that already existed before an
   * exclusion was added.
   *
   * Group pruning always runs, independent of whether an assignment was
   * actually removed this call - a group can be left with nothing in it
   * from an earlier partial cleanup (e.g. its one assigned field was
   * already gone) with no live excluded assignment left to trigger on.
   * Saves nothing itself - returns whether anything changed, so a caller
   * only re-saves the entities that actually needed it.
   */
  public function pruneExcludedFields(SubmissionSettingsInterface $settings): bool {
    $assignments = $settings->getFieldGroupAssignments();
    $changed = FALSE;
    foreach (array_keys($assignments) as $field_name) {
      if (in_array($field_name, PublicSubmissionForm::EXCLUDED_FIELDS, TRUE)) {
        unset($assignments[$field_name]);
        $changed = TRUE;
      }
    }

    $field_groups = [];
    foreach ($settings->getFieldGroups() as $group) {
      $field_groups[$group['id']] = $group;
    }
    $pruned = $this->pruneEmptyGroups($field_groups, $assignments);
    if (count($pruned) !== count($field_groups)) {
      $changed = TRUE;
    }
    if (!$changed) {
      return FALSE;
    }

    $settings->set('field_group_assignments', $assignments);
    $settings->set('field_groups', array_values($pruned));
    return TRUE;
  }

  /**
   * Drops any group (keyed by id) with nothing in its subtree - no directly
   * assigned field (per $assignments) and no surviving child group.
   * Repeated passes (rather than a single walk) so a group kept only
   * because a DEEPER descendant (not just a direct child) survives is
   * still correctly recognized, regardless of iteration order - same
   * approach PublicSubmissionForm::groupFields() itself uses for arbitrary
   * nesting depth.
   */
  protected function pruneEmptyGroups(array $field_groups, array $assignments): array {
    $has_fields = array_fill_keys(array_values($assignments), TRUE);
    $changed = TRUE;
    while ($changed) {
      $changed = FALSE;
      foreach ($field_groups as $group_id => $group) {
        if (isset($has_fields[$group_id])) {
          continue;
        }
        foreach ($field_groups as $candidate) {
          if ($candidate['parent'] === $group_id) {
            $has_fields[$group_id] = TRUE;
            break;
          }
        }
        if (!isset($has_fields[$group_id])) {
          unset($field_groups[$group_id]);
          $changed = TRUE;
        }
      }
    }
    return $field_groups;
  }

  /**
   * Reads a field_group entry's own collapsed/open state, using whichever
   * settings key its format plugin actually defines (see field_group's own
   * config schema, field_group.field_group_formatter_plugin.*): "details"
   * stores a boolean `open`; every other plugin this codebase uses
   * ("tab", "tabs", "accordion_item") stores a string `formatter`
   * ("open"/"closed") instead.
   */
  protected function isFieldGroupCollapsed(array $group): bool {
    $format_settings = $group['format_settings'] ?? [];
    if (($group['format_type'] ?? '') === 'details') {
      return empty($format_settings['open']);
    }
    return ($format_settings['formatter'] ?? 'open') === 'closed';
  }

  /**
   * If $field_name is an entity-reference field targeting media, overrides
   * its widget with the simple upload widget, restricted to whichever
   * media bundles the field already targets (or every supported type if
   * the field doesn't restrict target bundles).
   */
  public function applySimpleMediaUploadWidget(string $field_name, array $component, string $entity_type_id, string $bundle): array {
    $definition = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)[$field_name] ?? NULL;
    if (!$definition || $definition->getType() !== 'entity_reference' || $definition->getSetting('target_type') !== 'media') {
      return $component;
    }

    $target_bundles = array_keys(array_filter($definition->getSetting('handler_settings')['target_bundles'] ?? []));
    $supported = array_keys(MediaTypeExtensions::SUPPORTED_TYPES);
    $allowed_bundles = $target_bundles ? array_values(array_intersect($supported, $target_bundles)) : $supported;

    $component['type'] = 'mukurtu_simple_media_upload';
    $component['settings'] = ['allowed_bundles' => $allowed_bundles];
    return $component;
  }

}
