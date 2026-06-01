<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_export\Form\ExportBaseForm;

/**
 * Export Plugin Configuration Form.
 */
class ExportSettingsForm extends ExportBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_export_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $storage = $this->entityTypeManager->getStorage('export_list');
    $query = $storage->getQuery()->accessCheck(TRUE);
    $or = $query->orConditionGroup()
      ->condition('uid', $uid)
      ->condition('site_wide', TRUE);
    $ids = $query->condition($or)->sort('label')->execute();
    $lists = $storage->loadMultiple($ids);

    $options = [];
    foreach ($lists as $list) {
      $options[$list->id()] = $list->label();
    }

    $form['export_list_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Export list'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select export list -'),
      '#empty_value' => '',
      '#default_value' => $this->getActiveExportListId() ?? '',
      '#description' => $this->t('Choose a saved export list.'),
      '#weight' => -10,
    ];

    $settings = $this->getExporterConfig()['settings'] ?? [];
    $form += $this->exporter->settingsForm($form, $form_state, $settings);

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#button_type' => 'primary',
      '#submit' => ['::submitBack'],
    ];
    $form['actions']['duplicate_settings'] = [
      '#type' => 'submit',
      '#value' => $this->t('Duplicate Settings'),
      '#button_type' => 'primary',
      '#submit' => ['::submitDuplicateSettings'],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Export'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * Submit handler for "Duplicate Settings".
   */
  public function submitDuplicateSettings(array &$form, FormStateInterface $form_state) {
    $this->saveListSelection($form_state);
    if ($id = $this->exporter->duplicateSettings($form, $form_state)) {
      $settings = $this->exporter->getSettings($form, $form_state);
      $this->exporter->setConfiguration(['settings' => $settings]);
      $this->setExporterConfig($this->exporter->getConfiguration());
      $form_state->setRedirect('entity.csv_exporter.edit_form', ['csv_exporter' => $id]);
    }
  }

  /**
   * Submit handler for the back button.
   */
  public function submitBack(array &$form, FormStateInterface $form_state) {
    $this->saveListSelection($form_state);
    $form_state->setRedirect('entity.export_list.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->saveListSelection($form_state);
    $settings = $this->exporter->getSettings($form, $form_state);
    $this->exporter->setConfiguration(['settings' => $settings]);
    $this->setExporterConfig($this->exporter->getConfiguration());

    $this->executable->export();

    $form_state->setRedirect('mukurtu_export.export_results');
  }

  /**
   * Provide an access check that ensures there is a result to report.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function saveListSelection(FormStateInterface $form_state): void {
    $listId = $form_state->getValue('export_list_id') ?: NULL;
    $this->setActiveExportListId($listId ? (int) $listId : NULL);
  }

  public function access(AccountInterface $account) {
    if (!$account->hasPermission('access mukurtu export')) {
      return AccessResult::forbidden();
    }
    // Default to CSV if no exporter has been selected yet.
    if (!$this->exporter) {
      $this->setExporterId('csv');
      $this->exporter = $this->exportPluginManager->getInstance(['id' => 'csv', 'configuration' => []]);
    }
    return AccessResult::allowed();
  }

}
