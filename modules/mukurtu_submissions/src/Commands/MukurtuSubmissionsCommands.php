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
   * ungrouped, disabled - for every content type that doesn't already
   * have one. Automates exactly the manual steps the module's README
   * documents for enabling a single bundle, for every remaining bundle at
   * once. A site builder still reviews, enables, and (optionally)
   * organizes each one into field groups afterward, the same way Digital
   * Heritage's own form was refined after it was first created.
   */
  #[CLI\Command(name: 'mukurtu-submissions:create-default-forms')]
  #[CLI\Help(description: "Creates a disabled submission form for every content type that doesn't already have one, with every field included by default.")]
  public function createDefaultForms(): void {
    $storage = $this->entityTypeManager->getStorage('mukurtu_submission_settings');

    $existing_bundles = [];
    foreach ($storage->loadMultiple() as $settings) {
      $existing_bundles[$settings->getTargetBundle()] = TRUE;
    }

    $created = [];
    foreach ($this->entityBundleInfo->getBundleInfo('node') as $bundle => $info) {
      if (isset($existing_bundles[$bundle])) {
        continue;
      }

      $settings = $storage->create([
        'id' => $bundle,
        'label' => sprintf('Submit a %s', $info['label']),
        'target_entity_type_id' => 'node',
        'target_bundle' => $bundle,
        'status' => FALSE,
      ]);
      $settings->save();
      $this->formDisplayManager->ensureSubmissionFormDisplay($settings);
      $created[] = $bundle;
    }

    if (!$created) {
      $this->logger()->notice("Every content type already has a submission form; nothing to do.");
      return;
    }

    $this->logger()->success(sprintf(
      "Created %d submission form(s): %s. Each is disabled by default - review and enable at /admin/config/mukurtu/submissions.",
      count($created),
      implode(', ', $created)
    ));
  }

}
