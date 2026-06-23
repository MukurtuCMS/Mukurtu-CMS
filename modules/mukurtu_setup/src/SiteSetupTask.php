<?php

declare(strict_types=1);

namespace Drupal\mukurtu_setup;

/**
 * Represents a single site setup task.
 */
final class SiteSetupTask {

  /**
   * @param string $id
   *   Unique machine name for this task.
   * @param string $label
   *   Human-readable task label.
   * @param string $description
   *   Longer description explaining why the task matters.
   * @param string $group
   *   One of: 'required', 'recommended', 'optional'.
   * @param bool $canAutoDetect
   *   TRUE if completion can be detected automatically.
   * @param string|null $actionUrl
   *   Internal path or URL to the relevant admin page.
   * @param string|null $actionLabel
   *   Label for the action link.
   */
  public function __construct(
    private readonly string $id,
    private readonly string $label,
    private readonly string $description,
    private readonly string $group,
    private readonly bool $canAutoDetect,
    private readonly ?string $actionUrl = NULL,
    private readonly ?string $actionLabel = NULL,
  ) {}

  public function getId(): string {
    return $this->id;
  }

  public function getLabel(): string {
    return $this->label;
  }

  public function getDescription(): string {
    return $this->description;
  }

  public function getGroup(): string {
    return $this->group;
  }

  public function canAutoDetect(): bool {
    return $this->canAutoDetect;
  }

  public function getActionUrl(): ?string {
    return $this->actionUrl;
  }

  public function getActionLabel(): ?string {
    return $this->actionLabel;
  }

}
