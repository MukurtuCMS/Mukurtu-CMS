<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_export\Form\ExportBaseForm;
use Drupal\file\Entity\File;
use \Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Export Plugin Configuration Form.
 */
class ExportResultsForm extends ExportBaseForm
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'mukurtu_export_results';
  }

  public function submitNewExport(array &$form, FormStateInterface $form_state)
  {
    $this->reset();
    $form_state->setRedirect('mukurtu_export.export_item_and_format_selection');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $fid = $_SESSION['mukurtu_export']['download_fid'];

    $form['exported'] = [
      '#type' => 'table',
      '#caption' => $this->t('Items Exported'),
      '#header' => array(
        $this->t('Type'),
        $this->t('Number Exported'),
      ),
    ];

    foreach ($_SESSION['mukurtu_export']['results']['exported_entities'] as $entity_type_id => $entity_ids) {
      $entityType = \Drupal::service('entity_type.manager')->getDefinition($entity_type_id);
      $entityLabel = $entityType->getLabel();
      $form['exported'][$entity_type_id]['type'] = [
        '#type' => 'processed_text',
        '#text' => $entityLabel,
      ];
      $form['exported'][$entity_type_id]['count'] = [
        '#type' => 'processed_text',
        '#text' => count($entity_ids),
      ];
    }

    if ($fid && ($file = File::load($fid))) {
      $url = $file->createFileUrl(FALSE);
      $form['link'] = Link::fromTextAndUrl(t('Download Export'), Url::fromUri($url, []))->toRenderable();
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['new_export'] = [
      '#type' => 'submit',
      '#value' => $this->t('New Export'),
      '#button_type' => 'primary',
      '#submit' => ['::submitNewExport'],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('TBD'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

}
