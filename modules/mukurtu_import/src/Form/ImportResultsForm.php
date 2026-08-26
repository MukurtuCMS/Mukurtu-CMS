<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a Mukurtu Import Results form.
 */
class ImportResultsForm extends ImportBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_import_results';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $success = $this->store->get('batch_results_success') ?? FALSE;
    $noop = $this->store->get('batch_results_noop') ?? FALSE;
    $messages = $this->getMessages();
    $warnings = $this->getWarnings();

    $form['results_message'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--status" role="status" aria-live="polite">' . $this->t('All files imported successfully.') . '</div>',
    ];

    if (!empty($messages)) {
      $form['results_message']['#markup'] = '<div class="messages messages--error" role="alert" aria-live="assertive">' . $this->t('Some files failed to import.') . '</div>';
      $form['file_messages'] = $this->buildMessagesTable($messages);
    }

    if (!empty($warnings)) {
      foreach ($warnings as $warning) {
        $filename = $this->getImportFilename($warning['fid']) ?? $this->t('Unknown file');
        $form['file_warnings'][] = [
          '#type' => 'markup',
          '#markup' => '<div class="messages messages--warning" role="status" aria-live="polite">' . $this->t('@filename: @message', ['@filename' => $filename, '@message' => $warning['message']]) . '</div>',
        ];
      }
    }
    elseif ($noop) {
      $form['results_message']['#markup'] = "<div class=\"messages messages--warning\" role=\"status\" aria-live=\"polite\">" . $this->t('No content was imported.') . "</div>";
    }

    $this->buildTable($form, $form_state);

    $form['actions'] = [
      '#type' => 'actions',
    ];

    if (!empty($messages) || !$success) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Return to Uploaded Files'),
        '#submit' => ['::submitReturnToFiles'],
      ];
    }
    else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Start a new import'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->reset();
    $form_state->setRedirect('mukurtu_import.file_upload');
  }

  /**
   * Submit handler for the 'Return to Uploaded Files' button.
   *
   * @param array $form
   *    An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *    The current state of the form.
   */
  public function submitReturnToFiles(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('mukurtu_import.file_upload');
  }

  /**
   * Builds a File / Message table of failures, one row per failing file.
   *
   * Replaces stacking one raw alert div per message -- with many failures
   * that produced a wall of near-identical, redundant role="alert" banners
   * instead of a single scannable table.
   *
   * @param array $messages
   *   A list of ['fid' => ..., 'message' => ...] arrays, as returned by
   *   getMessages(). A message may itself contain multiple newline-joined
   *   lines (e.g. several validation violations on one failing row).
   */
  protected function buildMessagesTable(array $messages): array {
    $rows = [];
    foreach ($messages as $message) {
      $filename = $this->getImportFilename($message['fid']) ?? $this->t('(unknown file)');
      $lines = array_filter(explode("\n", (string) $message['message']));
      $rows[] = [
        $filename,
        [
          'data' => [
            '#theme' => 'item_list',
            '#items' => $lines,
          ],
        ],
      ];
    }
    return [
      '#type' => 'table',
      '#caption' => $this->t('Failed files'),
      '#header' => [
        ['data' => $this->t('File'), 'scope' => 'col'],
        ['data' => $this->t('Message'), 'scope' => 'col'],
      ],
      '#rows' => $rows,
      '#attributes' => ['class' => ['mukurtu-import-file-messages']],
    ];
  }

  /**
   * Builds the results table.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildTable(array &$form, FormStateInterface $form_state): void {
    $message = $this->getImportRevisionMessage();

    $communities_block = [
      '#type' => 'view',
      '#name' => 'mukurtu_import_results_communities',
      '#display_id' => 'results',
      '#embed' => TRUE,
      '#arguments' => [$message->render()],
    ];
    $form['community_results'] = $communities_block;

    $protocol_block = [
      '#type' => 'view',
      '#name' => 'mukurtu_import_results_cultural_protocols',
      '#display_id' => 'results',
      '#embed' => TRUE,
      '#arguments' => [$message->render()],
    ];
    $form['protocol_results'] = $protocol_block;

    $media_block = [
      '#type' => 'view',
      '#name' => 'mukurtu_import_results_media',
      '#display_id' => 'results',
      '#embed' => TRUE,
      '#arguments' => [$message->render()],
    ];
    $form['media_results'] = $media_block;


    $content_block = [
      '#type' => 'view',
      '#name' => 'mukurtu_import_results_content',
      '#display_id' => 'results',
      '#embed' => TRUE,
      '#arguments' => [$message->render()],
    ];
    $form['content_results'] = $content_block;

    $multipage_block = [
      '#type' => 'view',
      '#name' => 'mukurtu_import_results_multipage_items',
      '#display_id' => 'results',
      '#embed' => TRUE,
      '#arguments' => [$message->render()],
    ];
    $form['multipage_results'] = $multipage_block;

    $taxonomy_block = [
      '#type' => 'view',
      '#name' => 'mukurtu_import_results_taxonomy_terms',
      '#display_id' => 'results',
      '#embed' => TRUE,
      '#arguments' => [$message->render()],
    ];
    $form['taxonomy_results'] = $taxonomy_block;
  }

}
