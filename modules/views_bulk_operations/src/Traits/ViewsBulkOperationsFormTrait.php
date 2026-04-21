<?php

declare(strict_types=1);

namespace Drupal\views_bulk_operations\Traits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines common methods for Views Bulk Operations forms.
 */
trait ViewsBulkOperationsFormTrait {
  use MessengerTrait;
  use StringTranslationTrait;
  use ViewsBulkOperationsBulkFormKeyTrait;
  use ViewsBulkOperationsTempstoreTrait;

  /**
   * Helper function to prepare data needed for proper form display.
   *
   * @param string $view_id
   *   The current view ID.
   * @param string $display_id
   *   The current view display ID.
   *
   * @return array
   *   Array containing data for the form builder.
   */
  protected function getFormData($view_id, $display_id): array {

    // Get tempstore data.
    $form_data = $this->getTempstoreData($view_id, $display_id);

    // Get data needed for selected entities list.
    $this->addListData($form_data);

    return $form_data;
  }

  /**
   * Add data needed for entity list rendering.
   */
  protected function addListData(array &$form_data): void {
    $form_data['entity_labels'] = [];
    if (\count($form_data['list']) !== 0) {
      $form_data['selected_count'] = \count($form_data['list']);
      if (\array_key_exists('exclude_mode', $form_data) && $form_data['exclude_mode'] === TRUE) {
        $form_data['selected_count'] = $form_data['total_results'] - $form_data['selected_count'];
      }

      // In case of exclude mode we still get excluded labels
      // so we temporarily switch off exclude mode.
      $modified_form_data = $form_data;
      $modified_form_data['exclude_mode'] = FALSE;
      $form_data['entity_labels'] = $this->actionProcessor->getLabels($modified_form_data);
    }
    else {
      $form_data['selected_count'] = $form_data['total_results'] ?? 0;
    }
  }

  /**
   * Get the selection info title.
   *
   * @param array $tempstore_data
   *   VBO tempstore data array.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup | null
   *   The selection info title.
   */
  protected function getSelectionInfoTitle(array $tempstore_data): ?TranslatableMarkup {
    if (\count($tempstore_data['list']) !== 0) {
      $exclude_mode = \array_key_exists('exclude_mode', $tempstore_data) && $tempstore_data['exclude_mode'] === TRUE;
      return $exclude_mode ? $this->t('Selected all items except:') : $this->t('Items selected:');
    }
    return NULL;
  }

  /**
   * Build the selection info element.
   *
   * @param array $tempstore_data
   *   VBO tempstore data array.
   *
   * @return array
   *   Renderable array of the item list.
   */
  protected function getMultipageList(array $tempstore_data): array {
    $this->addListData($tempstore_data);
    $list = $this->getListRenderable($tempstore_data);
    return $list;
  }

  /**
   * Build selected entities list renderable.
   *
   * @param array $form_data
   *   Data needed for this form.
   *
   * @return array
   *   Renderable list array.
   */
  protected function getListRenderable(array $form_data): array {
    $renderable = [
      '#theme' => 'item_list',
      '#items' => $form_data['entity_labels'],
      '#empty' => $this->t('No items'),
    ];
    if (\count($form_data['entity_labels']) !== 0) {
      $more = \count($form_data['list']) - \count($form_data['entity_labels']);
      if ($more > 0) {
        $renderable['#items'][] = [
          '#children' => $this->t('..plus @count more..', [
            '@count' => $more,
          ]),
          '#wrapper_attributes' => ['class' => ['more']],
        ];
      }
      $renderable['#title'] = $this->getSelectionInfoTitle($form_data);
    }
    elseif (\array_key_exists('exclude_mode', $form_data) && $form_data['exclude_mode'] === TRUE) {
      $renderable['#empty'] = $this->t('Action will be executed on all items in the view.');
    }

    $renderable['#wrapper_attributes'] = ['class' => ['vbo-info-list-wrapper']];

    return $renderable;
  }

  /**
   * Get an entity list item from a bulk form key.
   *
   * @param string $bulkFormKey
   *   A bulk form key.
   *
   * @return array
   *   Entity list item.
   */
  protected function getListItem($bulkFormKey): ?array {
    $decoded = \base64_decode($bulkFormKey, TRUE);
    if ($decoded === FALSE) {
      return NULL;
    }
    $item = \json_decode($decoded);
    if (!\is_array($item)) {
      return NULL;
    }
    return $item;
  }

  /**
   * Add a cancel button into a VBO form.
   *
   * @param array $form
   *   The form definition.
   */
  protected function addCancelButton(array &$form): void {
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => [
        [$this, 'cancelForm'],
      ],
      '#limit_validation_errors' => [],
    ];
  }

  /**
   * Submit callback to cancel an action and return to the view.
   *
   * @param array $form
   *   The form definition.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function cancelForm(array &$form, FormStateInterface $form_state): void {
    $form_data = $form_state->get('views_bulk_operations');
    $this->messenger()->addMessage($this->t('Canceled "%action".', ['%action' => $form_data['action_label']]));
    $form_state->setRedirectUrl($form_data['redirect_url']);
  }

}
