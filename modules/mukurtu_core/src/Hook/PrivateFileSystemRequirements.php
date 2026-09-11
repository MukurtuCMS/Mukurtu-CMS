<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Reports whether the private file system is configured.
 *
 * Every file-based media type in Mukurtu stores its source field under
 * private:// - audio, document, image and video - which is what lets cultural
 * protocols control who can download a file. Without
 * $settings['file_private_path'] the private:// stream is never registered, so
 * those upload destinations resolve to an invalid scheme and media uploads
 * fail.
 *
 * Drupal core does not report this. Its file system requirement iterates the
 * public, private and temporary directories but begins with
 * `if (!$directory) { continue; }`, so an unset private path is skipped
 * silently rather than flagged (see SystemRequirementsHooks::runtime()).
 *
 * hook_runtime_requirements() is used rather than hook_requirements() because
 * 'requirements' is on core's static deny list for attribute-based hooks while
 * 'runtime_requirements' is not. Runtime-only is also correct here: the private
 * path is configured after installation, so an install-phase check would fail
 * on every new site.
 */
class PrivateFileSystemRequirements {

  use StringTranslationTrait;

  public function __construct(
    protected readonly StreamWrapperManagerInterface $streamWrapperManager,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $path = PrivateStream::basePath();
    $registered = $this->streamWrapperManager->isValidScheme('private');

    if (!empty($path) && $registered) {
      return [
        'mukurtu_core_private_file_system' => [
          'title' => $this->t('Private file system'),
          'value' => $this->t('Configured'),
          'severity' => RequirementSeverity::OK,
        ],
      ];
    }

    return [
      'mukurtu_core_private_file_system' => [
        'title' => $this->t('Private file system'),
        'value' => $this->t('Not configured'),
        'description' => $this->t("Mukurtu stores uploaded audio, documents, images and video in the private file system, so that cultural protocols control who can download them. No private file path is set, the private:// stream is unavailable, and media uploads will fail. Set \$settings['file_private_path'] in settings.php to a directory outside the web root, then clear the site cache. Media uploads keep failing until the cache is cleared, even after the path is set."),
        'severity' => RequirementSeverity::Error,
      ],
    ];
  }

}
