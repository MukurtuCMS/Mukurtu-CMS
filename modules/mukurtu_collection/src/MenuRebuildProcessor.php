<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;

/**
 * Service for rebuilding collection menus.
 */
final class MenuRebuildProcessor implements DestructableInterface {

  /**
   * TRUE if needs rebuild.
   *
   * @var bool
   */
  protected bool $needsRebuild = FALSE;

  /**
   * Constructs a new MenuRebuildProcessor.
   *
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menuLinkManager
   *   Menu link manager.
   */
  public function __construct(protected MenuLinkManagerInterface $menuLinkManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    if ($this->needsRebuild) {
      $this->menuLinkManager->rebuild();
      $this->needsRebuild = FALSE;
    }
  }

  /**
   * Marks rebuild as needed.
   *
   * @return $this
   */
  public function markRebuildNeeded(): self {
    $this->needsRebuild = TRUE;
    return $this;
  }

}
