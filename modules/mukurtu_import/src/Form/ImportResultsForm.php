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
    $messages = $this->getMessages();

    $form['results_message'] = [
      '#type' => 'markup',
      '#markup' => "<div class=\"messages messages--status\">" . $this->t('All files imported successfully.') . "</div>",
    ];

    if (!empty($messages)) {
      $form['results_message']['#markup'] = "<div class=\"messages messages--error\">" . $this->t('Some files failed to import.') . "</div>";
      foreach ($messages as $message) {
        $filename = $this->getImportFilename($message['fid']) ?? '';
        $form["file_messages"][] = [
          '#type' => 'markup',
          '#markup' => "<div class=\"messages messages--error\">" . $filename . ": ". $message['message'] . "</div>",
        ];
      }
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
  }

}
