<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_export\FlaggedExporterSource;

/**
 * Export Item Summary and Format Selection form.
 */
class ExportItemAndFormatSelectionForm extends ExportBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_export_item_and_format_selection';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $storage = $this->entityTypeManager->getStorage('export_list');
    $uid = \Drupal::currentUser()->id();

    // Build accessible saved list options.
    $query = $storage->getQuery()->accessCheck(TRUE);
    $orGroup = $query->orConditionGroup()
      ->condition('uid', $uid)
      ->condition('site_wide', TRUE);
    $ids = $query->condition($orGroup)->sort('label')->execute();
    $lists = $storage->loadMultiple($ids);

    $list_options = ['' => $this->t('- Current flag queue -')];
    foreach ($lists as $list) {
      $list_options[$list->id()] = $list->label();
    }

    $form['export_source'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Export Source'),
    ];

    $form['export_source']['export_list_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Select export list'),
      '#options' => $list_options,
      '#default_value' => $this->getActiveExportListId() ?? '',
      '#description' => $this->t('Choose a saved export list, or use the current flag queue.'),
    ];

    $form['export_source']['save_as_list'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Save current flag queue as a new list'),
      '#collapsible' => FALSE,
    ];

    $form['export_source']['save_as_list']['new_list_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('List name'),
      '#maxlength' => 255,
    ];

    $form['export_source']['save_as_list']['save_list_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save as New List'),
      '#submit' => ['::submitSaveAsNewList'],
      '#limit_validation_errors' => [['new_list_name']],
    ];

    // Check whether the active source has any items.
    $exportListIsEmpty = TRUE;
    foreach ($this->source->getEntities() as $entities) {
      if (!empty($entities)) {
        $exportListIsEmpty = FALSE;
        break;
      }
    }

    // Always show the flag queue views so users can add/remove items.
    $form['export_list']['content'] = [
      '#type' => 'view',
      '#name' => 'export_list_content',
      '#display_id' => 'export_content_list_block',
      '#embed' => TRUE,
    ];

    $form['export_list']['media'] = [
      '#type' => 'view',
      '#name' => 'export_list_media',
      '#display_id' => 'export_media_list_block',
      '#embed' => TRUE,
    ];

    if (!$exportListIsEmpty) {
      $form['export_list']['exporter'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Export Format'),
        '#default_value' => $this->getExporterId(),
        '#options' => $this->getExporterOptions(),
      ];

      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next - Configure Export'),
        '#button_type' => 'primary',
      ];
    }
    else {
      $form['message'] = [
        '#type' => 'processed_text',
        '#text' => $this->t('You currently have no items marked for export. You can visit <a href=":manageContent">Manage Content</a> and <a href=":browseContent">Browse Content</a> to add items for export.',
          [
            ':manageContent' => Url::fromRoute('view.mukurtu_manage_all_content.mukurtu_manage_content')->toString(),
            ':browseContent' => Url::fromRoute('mukurtu_browse.browse_page')->toString(),
          ]),
        '#format' => 'full_html',
      ];
    }

    return $form;
  }

  /**
   * Submit handler: save the current flag queue as a named export list.
   */
  public function submitSaveAsNewList(array &$form, FormStateInterface $form_state) {
    $label = trim($form_state->getValue('new_list_name') ?? '');
    if (empty($label)) {
      $this->messenger()->addError($this->t('Please enter a name for the new export list.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $items = (new FlaggedExporterSource())->getEntities();
    /** @var \Drupal\mukurtu_export\Entity\ExportList $list */
    $list = $this->entityTypeManager->getStorage('export_list')->create([
      'label' => $label,
      'uid' => \Drupal::currentUser()->id(),
      'site_wide' => FALSE,
    ]);
    $list->setItems($items);
    $list->save();

    $this->setActiveExportListId((int) $list->id());
    $this->messenger()->addStatus($this->t('Export list %name has been saved.', ['%name' => $label]));
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $listId = $form_state->getValue('export_list_id') ?: NULL;
    $this->setActiveExportListId($listId ? (int) $listId : NULL);
    $this->setExporterId($form_state->getValue('exporter'));
    $form_state->setRedirect('mukurtu_export.export_settings');
  }

}
