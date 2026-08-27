<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions;

use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
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

  /**
   * Field-type-keyed widget overrides for the public submission form.
   *
   * The public form's audience includes keyboard-only and screen reader
   * visitors, for whom the Leaflet/Geoman map widget (the editorial
   * default for geofield-type fields) has no accessible equivalent - see
   * https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1913. Keyed by field
   * type so it applies to field_coverage (or any other geofield) on every
   * bundle, regardless of what the bundle's own "default" form display
   * uses.
   */
  const SUBMISSION_WIDGET_OVERRIDES = [
    'geofield' => [
      'type' => 'geofield_mukurtu_latlon',
      'settings' => [
        'instructions' => '',
        'show_descriptions' => TRUE,
      ],
    ],
  ];

  /**
   * Administrative/scaffolding content types that ship with the profile
   * for general site-building rather than as community-authored content -
   * visitor submission doesn't make sense for these, so createDefaultForms()
   * never generates a form for them.
   */
  const EXCLUDED_BUNDLES = ['article', 'page', 'landing_page'];

  /**
   * Overrides the generated settings-entity label for bundles whose own
   * content type name reads ambiguously as "submit a {label}" - "Submit a
   * Person" sounds like submitting an actual human, not a record about
   * one. Any bundle not listed here just uses its own label as-is.
   */
  const LABEL_OVERRIDES = [
    'person' => 'Person Record',
    'place' => 'Place Record',
  ];

  public function __construct(
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
  ) {}

  /**
   * Bulk-creates a baseline submission form - every field included,
   * grouped to mirror the content type's own regular edit form, disabled
   * - for every content type that doesn't already have one (excluding
   * EXCLUDED_BUNDLES). Also re-runnable as a backfill: any already-existing
   * settings entity that still has no field groups gets them seeded too,
   * except Digital Heritage's, which are hand-curated and never
   * overwritten. A site builder still reviews and enables each one
   * afterward.
   *
   * Called from mukurtu_submissions_install() (fresh sites),
   * mukurtu_submissions_update_40007() (existing sites), and
   * MukurtuSubmissionsCommands::createDefaultForms() (manual re-run/backfill)
   * so all three stay in sync on what "every content type gets a form"
   * means.
   *
   * @return array
   *   ['created' => string[], 'grouped' => string[]] - the bundles that got
   *   a brand-new settings entity, and the bundles (new or pre-existing)
   *   that got field groups seeded onto them this call.
   */
  public function createDefaultForms(): array {
    $storage = $this->entityTypeManager->getStorage('mukurtu_submission_settings');

    $existing = [];
    foreach ($storage->loadMultiple() as $settings) {
      $existing[$settings->getTargetBundle()] = $settings;
    }

    $created = [];
    $grouped = [];
    foreach ($this->entityBundleInfo->getBundleInfo('node') as $bundle => $info) {
      if (in_array($bundle, self::EXCLUDED_BUNDLES, TRUE)) {
        continue;
      }

      $settings = $existing[$bundle] ?? NULL;
      $is_new = !$settings;
      if ($is_new) {
        $label = self::LABEL_OVERRIDES[$bundle] ?? $info['label'];
        $settings = $storage->create([
          'id' => $bundle,
          'label' => sprintf('Submit a %s', $label),
          'target_entity_type_id' => 'node',
          'target_bundle' => $bundle,
          'status' => FALSE,
        ]);
      }

      $needs_groups = $bundle !== 'digital_heritage' && !$settings->getFieldGroups();
      if ($needs_groups) {
        $this->seedFieldGroupsFromDefaultForm($settings);
      }

      if ($is_new || $needs_groups) {
        $settings->save();
      }
      if ($is_new) {
        $this->ensureSubmissionFormDisplay($settings);
        $created[] = $bundle;
      }
      if ($needs_groups) {
        $grouped[] = $bundle;
      }
    }

    return ['created' => $created, 'grouped' => $grouped];
  }

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
    $this->ensureSubmissionFormDisplayForBundle($settings->getTargetEntityTypeId(), $settings->getTargetBundle());
  }

  /**
   * Does the actual work of ensureSubmissionFormDisplay(), factored out so
   * it can also be called recursively for any paragraph bundle reachable
   * through an entity_reference_revisions field on the bundle being
   * provisioned - a paragraph-embedded field (e.g. dictionary_word's
   * sample-sentence recording, nested inside the sample_sentence paragraph
   * type) otherwise never gets its own "submission" display at all, so its
   * inline subform keeps rendering via the paragraph's "default" display -
   * still the full Media Library picker, unusable by an anonymous visitor -
   * regardless of how the field looks at the top level.
   *
   * $visited (keyed by "$entity_type_id:$bundle") guards against infinite
   * recursion from a paragraph type that directly or transitively
   * references itself; also lets the same paragraph bundle be provisioned
   * once even when reachable from multiple parent bundles/fields.
   */
  protected function ensureSubmissionFormDisplayForBundle(string $entity_type_id, string $bundle, array &$visited = []): void {
    $visited_key = $entity_type_id . ':' . $bundle;
    if (isset($visited[$visited_key])) {
      return;
    }
    $visited[$visited_key] = TRUE;

    $this->ensureSubmissionFormModeExists($entity_type_id);

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
    // - see applySimpleMediaUploadWidget(). Any entity-reference-revisions
    // field targeting paragraphs gets its own referenced paragraph
    // bundle(s) recursively provisioned the same way, and is switched to
    // render them through that provisioned "submission" display - see
    // applyParagraphSubmissionMode().
    $default_display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'default');
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $definition) {
      if (in_array($field_name, PublicSubmissionForm::EXCLUDED_FIELDS, TRUE) || !$definition->isDisplayConfigurable('form')) {
        continue;
      }
      $component = $display->getComponent($field_name) ?: ($default_display->getComponent($field_name) ?: []);
      $component = $this->applySimpleMediaUploadWidget($field_name, $component, $entity_type_id, $bundle);
      $component = $this->applySubmissionWidgetOverride($field_name, $component, $entity_type_id, $bundle);
      $component = $this->applyParagraphSubmissionMode($field_name, $component, $definition, $visited);
      $display->setComponent($field_name, $component);
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

  /**
   * Applies SUBMISSION_WIDGET_OVERRIDES to a form display component, if the
   * field's type has an override and the component exists. Leaves the
   * component alone (does not create one) for a field that isn't yet
   * included on the display.
   */
  public function applySubmissionWidgetOverride(string $field_name, array $component, string $entity_type_id, string $bundle): array {
    if (!$component) {
      return $component;
    }
    $field_type = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)[$field_name]?->getType();
    if ($field_type !== NULL && isset(self::SUBMISSION_WIDGET_OVERRIDES[$field_type])) {
      return self::SUBMISSION_WIDGET_OVERRIDES[$field_type] + $component;
    }
    return $component;
  }

  /**
   * If $definition is an entity_reference_revisions field targeting
   * paragraphs, recursively ensures a "submission" display exists for each
   * paragraph bundle it can target, and points the field's own widget at
   * that display mode (the "form_display_mode" setting both of this
   * module's paragraph widgets - "paragraphs" and "entity_reference_
   * paragraphs" - read via EntityFormDisplay::collectRenderDisplay() when
   * rendering each item's inline subform), so nested fields get the exact
   * same widget substitution as a field living directly on the bundle.
   *
   * A field with no explicit target_bundles restriction is left alone:
   * every paragraph type on the site would be a valid target, so there's
   * no single bundle (or bounded set of bundles) to provision here, and no
   * evidence any such field currently exists in this profile.
   */
  protected function applyParagraphSubmissionMode(string $field_name, array $component, FieldDefinitionInterface $definition, array &$visited): array {
    if ($definition->getType() !== 'entity_reference_revisions' || $definition->getSetting('target_type') !== 'paragraph') {
      return $component;
    }

    $target_bundles = array_keys(array_filter($definition->getSetting('handler_settings')['target_bundles'] ?? []));
    if (!$target_bundles) {
      return $component;
    }

    foreach ($target_bundles as $paragraph_bundle) {
      $this->ensureSubmissionFormDisplayForBundle('paragraph', $paragraph_bundle, $visited);
    }

    $component['settings']['form_display_mode'] = 'submission';
    return $component;
  }

  /**
   * Ensures the "$entity_type_id.submission" entity form mode exists -
   * EntityDisplayBase::calculateDependencies() loads it by ID whenever a
   * non-"default"-mode display is saved (to record a config dependency on
   * it), and fatals on a missing one. For "node", this mode always exists
   * already - it ships as required config (core.entity_form_mode.node.
   * submission.yml) since node is a hard dependency of this module. For
   * "paragraph", nothing ships an equivalent: making paragraphs a hard
   * module dependency just to ship one more static config file isn't
   * worth it when this is just as correct and works regardless of module
   * install order relative to whatever module defines a given paragraph
   * bundle.
   */
  protected function ensureSubmissionFormModeExists(string $entity_type_id): void {
    $mode_id = $entity_type_id . '.submission';
    if (EntityFormMode::load($mode_id)) {
      return;
    }
    EntityFormMode::create([
      'id' => $mode_id,
      'label' => 'Submission',
      'targetEntityType' => $entity_type_id,
      'dependencies' => ['enforced' => ['module' => ['mukurtu_submissions']]],
    ])->save();
  }

  /**
   * Retrofits an EXISTING bundle's already-provisioned "submission"
   * display with the paragraph-nested-field handling
   * ensureSubmissionFormDisplayForBundle() now applies to freshly created
   * ones. That method is deliberately a no-op once a display exists (it
   * should never clobber an admin's hand-curated field arrangement), so an
   * already-provisioned display - digital_heritage's, or any bundle a site
   * enabled before this fix - would otherwise never pick up the paragraph
   * handling. mukurtu_submissions_update_40008() calls this for every
   * bundle a site already has a settings entity for.
   *
   * Only touches entity_reference_revisions/paragraph components the
   * display already has - never adds or removes fields, matching this
   * method's narrow "retrofit the new paragraph handling only" purpose.
   */
  public function retrofitParagraphSubmissionMode(string $entity_type_id, string $bundle): void {
    $display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'submission');
    if ($display->isNew()) {
      // Nothing to retrofit - a not-yet-provisioned bundle gets the
      // paragraph handling for free the first time
      // ensureSubmissionFormDisplayForBundle() runs for it.
      return;
    }

    $changed = FALSE;
    $visited = [];
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $definition) {
      $component = $display->getComponent($field_name);
      if (!$component) {
        continue;
      }
      $updated = $this->applyParagraphSubmissionMode($field_name, $component, $definition, $visited);
      if ($updated !== $component) {
        $display->setComponent($field_name, $updated);
        $changed = TRUE;
      }
    }

    if ($changed) {
      $display->save();
    }
  }

}
