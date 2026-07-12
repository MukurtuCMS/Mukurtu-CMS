<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Hook;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Wires the Add to Collection / Add to Personal Collection forms for AJAX
 * modal submission when opened as a browse-card quick action.
 *
 * Mirrors the existing modal-form pattern in mukurtu_media.module
 * (mukurtu_media_form_alter() + mukurtu_media_edit_dialog_ajax()): the
 * triggering link carries a ?mukurtu_modal=1 query flag (which persists
 * across Drupal's ajax_form submission), used here to opt these two forms
 * into an #ajax submit handler only when opened in a dialog - the existing
 * full-page "Add to Collection" / "Add to Personal Collection" tabs are
 * unaffected.
 */
final class CollectionFormHooks {

  protected const FORM_IDS = [
    'mukurtu_collection_add_item_to_collection_form',
    'mukurtu_collection_add_item_to_personal_collection_form',
  ];

  public function __construct(protected RendererInterface $renderer) {
  }

  /**
   * Called from mukurtu_collection_form_alter() in the .module file.
   */
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!in_array($form_id, self::FORM_IDS, TRUE) || !\Drupal::request()->query->get('mukurtu_modal')) {
      return;
    }
    if (isset($form['submit'])) {
      $form['submit']['#ajax'] = [
        'callback' => 'mukurtu_collection_add_item_dialog_ajax',
        'progress' => ['type' => 'throbber'],
      ];
    }
  }

  /**
   * Called from mukurtu_collection_add_item_dialog_ajax() in the .module
   * file.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $node_id = $form_state->getValue('node');

    if ($form_state->isExecuted() && !$form_state->getErrors()) {
      $response->addCommand(new CloseModalDialogCommand());
      foreach (\Drupal::messenger()->all() as $type => $messages) {
        foreach ($messages as $message) {
          $response->addCommand(new MessageCommand($message, NULL, ['type' => $type]));
        }
      }
      \Drupal::messenger()->deleteAll();
      // Prefix must match the data-quick-action-trigger value
      // CollectionPreprocessHooks sets on the triggering element for this
      // specific action - both add_to_collection and
      // add_to_personal_collection can be present for the same node, so a
      // node-ID-only selector would match both and could return focus to
      // the wrong control.
      $prefix = $form_state->getFormObject()->getFormId() === 'mukurtu_collection_add_item_to_personal_collection_form'
        ? 'personal-collection-'
        : 'collection-';
      // May still match more than one element if the same node is rendered
      // more than once on the page (e.g. also in a related-content block);
      // harmless, focus lands on one of the valid triggering icons for this
      // action.
      $response->addCommand(new InvokeCommand('[data-quick-action-trigger="' . $prefix . $node_id . '"]', 'focus'));
      return $response;
    }

    // Validation errors: re-render the form with messages inside the dialog.
    foreach ($form_state->getErrors() as $error) {
      \Drupal::messenger()->addError($error);
    }
    $status_messages = ['#type' => 'status_messages'];
    $form['#prefix'] = ($form['#prefix'] ?? '') . $this->renderer->renderRoot($status_messages);
    $output = $this->renderer->renderRoot($form);
    $response->setAttachments($form['#attached']);
    $response->addCommand(new ReplaceCommand('.ui-dialog-content', $output));
    return $response;
  }

}
