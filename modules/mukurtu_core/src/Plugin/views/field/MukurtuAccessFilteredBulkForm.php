<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views_bulk_operations\Plugin\views\field\ViewsBulkOperationsBulkForm;

/**
 * Bulk-form field that only offers actions the current viewer can actually
 * perform.
 *
 * VBO's own getBulkOptions() builds the dropdown purely from the view's
 * static "selected_actions" config, filtered only by entity type -- it
 * never calls an action plugin's real access() before listing an option,
 * only later per selected row during execution. That's why an action like
 * "Archive" shows up for viewers who can't actually use it on anything.
 *
 * This samples the current page's already-loaded result rows (no extra
 * query) and drops any option none of them are accessible for. It's a
 * UI-quality fix scoped to the current page of results, not a security
 * boundary -- the per-row access() check VBO already runs at execution
 * time (ViewsBulkOperationsActionProcessor::process()) is unaffected and
 * remains the real gate.
 */
#[ViewsField("mukurtu_access_filtered_bulk_form")]
class MukurtuAccessFilteredBulkForm extends ViewsBulkOperationsBulkForm {

  /**
   * {@inheritdoc}
   */
  protected function getBulkOptions(): array {
    $options = parent::getBulkOptions();
    if (empty($options)) {
      return $options;
    }

    $entities = [];
    foreach ($this->view->result as $row) {
      $entity = $this->getEntity($row);
      if ($entity) {
        $entities[] = $entity;
      }
    }
    // Fail open on an empty/unresolvable result set -- nothing to sample
    // against, so don't spuriously hide every action.
    if (empty($entities)) {
      return $options;
    }

    foreach (array_keys($options) as $key) {
      $selected_action_data = $this->options['selected_actions'][$key] ?? NULL;
      if ($selected_action_data === NULL) {
        continue;
      }

      $configuration = ($selected_action_data['preconfiguration'] ?? []);
      try {
        $action = $this->actionManager->createInstance($selected_action_data['action_id'], $configuration);
      }
      catch (\Exception $e) {
        // Fail open: leave the option as-is rather than break the form
        // over a plugin that can't even be instantiated.
        continue;
      }

      $accessible = FALSE;
      foreach ($entities as $entity) {
        if ($action->access($entity, $this->currentUser)) {
          $accessible = TRUE;
          break;
        }
      }
      if (!$accessible) {
        unset($this->bulkOptions[$key]);
      }
    }

    return $this->bulkOptions;
  }

}
