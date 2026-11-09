<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\mukurtu_export\Form\ExportBaseForm;

/**
 * Export Plugin Configuration Form.
 */
class ExportResultsForm extends ExportBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_export_results';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('mukurtu_export.export_settings');
  }

  /**
   * Submit handler for "New Export".
   */
  public function submitNewExport(array &$form, FormStateInterface $form_state) {
    $this->reset();
    $form_state->setRedirect('entity.export_list.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $fid = $_SESSION['mukurtu_export']['download_fid'];

    $overlong = $_SESSION['mukurtu_export']['results']['overlong'] ?? [];
    if ($overlong) {
      $form['overlong'] = $this->buildOverlongWarning($overlong);
    }

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
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back to Settings'),
      '#button_type' => 'primary',
    ];
    $form['actions']['new_export'] = [
      '#type' => 'submit',
      '#value' => $this->t('New Export'),
      '#button_type' => 'primary',
      '#submit' => ['::submitNewExport'],
    ];

    return $form;
  }

  /**
   * Builds the warning about values too long for an Excel cell.
   *
   * @param array $overlong
   *   Keyed by column header, each with a 'count' of items and the first
   *   few item 'labels', as recorded by CSV::recordOverlongValues().
   *
   * @return array
   *   A render array for a warning message.
   */
  protected function buildOverlongWarning(array $overlong): array {
    $lines = [];
    foreach ($overlong as $field => $info) {
      $labels = implode(', ', $info['labels'] ?? []);
      $more = ($info['count'] ?? 0) - count($info['labels'] ?? []);
      $lines[] = $more > 0
        ? $this->t('@field: @items, and @count more', ['@field' => $field, '@items' => $labels, '@count' => $more])
        : $this->t('@field: @items', ['@field' => $field, '@items' => $labels]);
    }

    return [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          [
            'intro' => [
              '#type' => 'html_tag',
              '#tag' => 'p',
              '#value' => $this->t('Some values are longer than the 32,767 characters an Excel cell can hold. If you open this spreadsheet in Excel, these fields will be truncated, and their data will be lost on save. See <a href=":url">File Formats and Spreadsheet Tools</a> for recommended spreadsheet tools.', [
                ':url' => 'https://docs.mukurtu.org/roundtrip/RoundtripFormatting/',
              ]),
            ],
            'fields' => [
              '#theme' => 'item_list',
              '#items' => $lines,
            ],
          ],
        ],
      ],
      '#status_headings' => [
        'warning' => $this->t('Warning message'),
      ],
      '#weight' => -100,
    ];
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
  public function access(AccountInterface $account) {
    if (!isset($_SESSION['mukurtu_export']) || !isset($_SESSION['mukurtu_export']['download_fid'])) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

}
