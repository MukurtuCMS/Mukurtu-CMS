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
    /* $query = $this->entityTypeManager->getStorage('flagging')->getQuery();
    $result = $query->condition('flag_id', 'export_content')
      ->condition('entity_type', 'node')
      ->condition('uid', $this->currentUser()->id())
      ->execute(); */
    //dpm($result);

    $form['test'] = [
      '#type' => 'view',
      '#name' => 'mukurtu_export_cart',
      '#display_id' => 'node',
      '#embed' => TRUE,
    ];
    return $form;
  }


}
