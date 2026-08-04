<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions\Commands;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\mukurtu_submissions\SubmissionFormDisplayManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for the Mukurtu Submissions module.
 */
class MukurtuSubmissionsCommands extends DrushCommands {

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
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
    protected SubmissionFormDisplayManager $formDisplayManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('mukurtu_submissions.form_display_manager'),
    );
  }

  /**
   * Bulk-creates a baseline submission form - every field included,
   * grouped to mirror the content type's own regular edit form, disabled
   * - for every content type that doesn't already have one (excluding
   * EXCLUDED_BUNDLES). Automates exactly the manual steps the module's
   * README documents for enabling a single bundle, for every remaining
   * bundle at once. Also re-runnable as a backfill: any already-existing
   * settings entity that still has no field groups gets them seeded too,
   * except Digital Heritage's, which are hand-curated and never
   * overwritten. A site builder still reviews and enables each one
   * afterward.
   */
  #[CLI\Command(name: 'mukurtu-submissions:create-default-forms')]
  #[CLI\Help(description: "Creates a disabled submission form for every content type that doesn't already have one (excluding Article, Basic page, and Landing page), with every field included and grouped to match the content type's regular edit form.")]
  public function createDefaultForms(): void {
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
        $this->formDisplayManager->seedFieldGroupsFromDefaultForm($settings);
      }

      if ($is_new || $needs_groups) {
        $settings->save();
      }
      if ($is_new) {
        $this->formDisplayManager->ensureSubmissionFormDisplay($settings);
        $created[] = $bundle;
      }
      if ($needs_groups) {
        $grouped[] = $bundle;
      }
    }

    if (!$created && !$grouped) {
      $this->logger()->notice("Every content type already has a grouped submission form; nothing to do.");
      return;
    }

    if ($created) {
      $this->logger()->success(sprintf(
        "Created %d submission form(s): %s. Each is disabled by default - review and enable at /admin/config/mukurtu/submissions.",
        count($created),
        implode(', ', $created)
      ));
    }
    if ($grouped) {
      $this->logger()->success(sprintf(
        "Seeded field groups for %d existing submission form(s): %s.",
        count($grouped),
        implode(', ', $grouped)
      ));
    }
  }

}
