<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\file\FileInterface;
use Exception;
use League\Csv\Reader;
use Drupal\mukurtu_import\MukurtuImportStrategyInterface;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_export\Form\ExportBaseForm;

/**
 * Provides a Mukurtu Import form.
 */
class ExportItemAndFormatSelection extends ExportBaseForm {

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

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select Export Format: Not Implemented Yet'),
      '#button_type' => 'primary',
      //'#submit' => ['::submitBack'],
    ];

    return $form;
  }


}
