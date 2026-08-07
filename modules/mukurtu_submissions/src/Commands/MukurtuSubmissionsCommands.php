<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions\Commands;

use Drupal\mukurtu_submissions\SubmissionFormDisplayManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for the Mukurtu Submissions module.
 */
class MukurtuSubmissionsCommands extends DrushCommands {

  public function __construct(
    protected SubmissionFormDisplayManager $formDisplayManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('mukurtu_submissions.form_display_manager'),
    );
  }

  /**
   * Bulk-creates a baseline submission form - every field included,
   * grouped to mirror the content type's own regular edit form, disabled
   * - for every content type that doesn't already have one. Automates a
   * manual re-run/backfill of the same provisioning that
   * mukurtu_submissions_install() and mukurtu_submissions_update_40007()
   * already apply automatically on install/update - see
   * SubmissionFormDisplayManager::createDefaultForms(). A site builder
   * still reviews and enables each one afterward.
   */
  #[CLI\Command(name: 'mukurtu-submissions:create-default-forms')]
  #[CLI\Help(description: "Creates a disabled submission form for every content type that doesn't already have one (excluding Article, Basic page, and Landing page), with every field included and grouped to match the content type's regular edit form.")]
  public function createDefaultForms(): void {
    $result = $this->formDisplayManager->createDefaultForms();
    $created = $result['created'];
    $grouped = $result['grouped'];

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
