<?php

declare(strict_types=1);

namespace Drupal\views_bulk_operations\Traits;

use Drupal\Core\TempStore\PrivateTempStore;

/**
 * Tempstore operation methods.
 */
trait ViewsBulkOperationsTempstoreTrait {

  /**
   * The tempstore object associated with the current view.
   */
  protected ?PrivateTempStore $viewTempstore = NULL;

  /**
   * The tempstore name.
   */
  protected string $tempStoreName;

  /**
   * Initialize the current view tempstore object.
   */
  protected function getTempstore(?string $view_id = NULL, ?string $display_id = NULL): PrivateTempStore {
    if ($this->viewTempstore === NULL) {
      $this->tempStoreName = 'views_bulk_operations_' . $view_id . '_' . $display_id;
      $this->viewTempstore = $this->tempStoreFactory->get($this->tempStoreName);
    }
    return $this->viewTempstore;
  }

  /**
   * Gets the current view user tempstore data.
   *
   * @param string $view_id
   *   The current view ID.
   * @param string $display_id
   *   The display ID of the current view.
   */
  protected function getTempstoreData($view_id = NULL, $display_id = NULL): ?array {
    $data = $this->getTempstore($view_id, $display_id)->get((string) $this->currentUser()->id());

    return $data;
  }

  /**
   * Sets the current view user tempstore data.
   *
   * @param array $data
   *   The data to set.
   * @param string $view_id
   *   The current view ID.
   * @param string $display_id
   *   The display ID of the current view.
   */
  protected function setTempstoreData(array $data, $view_id = NULL, $display_id = NULL): void {
    $this->getTempstore($view_id, $display_id)->set((string) $this->currentUser()->id(), $data);
  }

  /**
   * Deletes the current view user tempstore data.
   *
   * @param string $view_id
   *   The current view ID.
   * @param string $display_id
   *   The display ID of the current view.
   */
  protected function deleteTempstoreData($view_id = NULL, $display_id = NULL): void {
    $this->getTempstore($view_id, $display_id)->delete((string) $this->currentUser()->id());
  }

}
