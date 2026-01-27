<?php

declare(strict_types=1);

namespace Drupal\mukurtu_browse\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Views;

/**
 * Controller for mukurtu_browse.browse_page route.
 */
class MukurtuBrowseController extends ControllerBase {

  /**
   * Search backend config.
   *
   * @var string
   */
  protected string $backend;

  /**
   * Constructs a new MukurtuBrowseController object.
   */
  public function __construct() {
    $this->backend = $this->config('mukurtu_search.settings')->get('backend') ?? 'db';
  }

  /**
   * Return the machine name of the view to use based on the search backend config.
   *
   * @return string
   *   The machine name of the view.
   */
  protected function getViewName(): string {
    $views = [
      'db' => 'mukurtu_browse',
      'solr' => 'mukurtu_browse_solr',
    ];

    return $views[$this->backend];
  }

  /**
   * Render the browse page.
   *
   * @return array
   *   Render array for the browse page.
   */
  public function content(): array {
    // Render the browse view block. This is the list display.
    $list_view_block = [
      '#type' => 'view',
      '#name' => $this->getViewName(),
      '#display_id' => 'mukurtu_browse_block',
      '#embed' => TRUE,
    ];

    // Render the browse view block. This is the grid display.
    $grid_view_block = [
      '#type' => 'view',
      '#name' => $this->getViewName(),
      '#display_id' => 'mukurtu_browse_block_grid',
      '#embed' => TRUE,
    ];

    // Render the browse view block. This is the map display.
    $map_view_block = [
      '#type' => 'view',
      '#name' => $this->getViewName(),
      '#display_id' => 'mukurtu_browse_block_map',
      '#embed' => TRUE,
    ];

    return [
      '#theme' => 'mukurtu_browse',
      '#is_dh' => false,
      '#list_results' => $list_view_block,
      '#grid_results' => $grid_view_block,
      '#map_results' => $map_view_block,
      '#attached' => [
        'library' => [
          'mukurtu_browse/mukurtu-browse-view-switch',
        ],
      ],
    ];
  }

  /**
   * Check access for the browse DH route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $view = Views::getView($this->getViewName());
    if (!$view) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

}
