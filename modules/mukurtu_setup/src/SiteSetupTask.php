<?php

declare(strict_types=1);

namespace Drupal\mukurtu_setup;

use Drupal\Component\Render\MarkupInterface;

/**
 * Represents a single site setup task.
 */
final class SiteSetupTask {

  /**
   * @param string $id
   *   Unique machine name for this task.
   * @param string $label
   *   Human-readable task label.
   * @param string|\Drupal\Component\Render\MarkupInterface $description
   *   Longer description explaining why the task matters. May contain markup.
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
    private readonly string|MarkupInterface $description,
    private readonly string $group,
    private readonly bool $canAutoDetect,
    private readonly ?string $actionUrl = NULL,
    private readonly ?string $actionLabel = NULL,
    private readonly bool $dismissible = FALSE,
  ) {}

  public function getId(): string {
    return $this->id;
  }

  public function getLabel(): string {
    return $this->label;
  }

  public function getDescription(): string|MarkupInterface {
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

  public function isDismissible(): bool {
    return $this->dismissible;
  }

}
