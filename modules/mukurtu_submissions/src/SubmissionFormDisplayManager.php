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
