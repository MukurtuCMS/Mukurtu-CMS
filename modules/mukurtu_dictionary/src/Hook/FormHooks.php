<?php

namespace Drupal\mukurtu_dictionary\Hook;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * Hook implementations for mukurtu_dictionary forms.
 */
class FormHooks
{
    /**
     * Implements hook_form_node_dictionary_word_form_alter().
     *
     * Warns editors when no Language terms exist yet, since the Language field
     * will be empty and dictionary words cannot be properly classified without them.
     */
    #[Hook("form_node_dictionary_word_form_alter")]
    public function formNodeDictionaryWordFormAlter(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        // Only show the warning on the add form, not on edits.
        $node = $form_state->getFormObject()->getEntity();
        if (!$node->isNew()) {
            return;
        }

        $term_count = \Drupal::entityQuery("taxonomy_term")
            ->condition("vid", "language")
            ->accessCheck(false)
            ->count()
            ->execute();

        if ($term_count > 0) {
            return;
        }

        $url = Url::fromRoute("entity.taxonomy_vocabulary.overview_form", [
            "taxonomy_vocabulary" => "language",
        ]);
        $link = \Drupal::service("link_generator")->generate(
            t("add languages"),
            $url,
        );
        $message = t(
            "There are no languages available, which is a requirement for dictionary words. Please @link before creating dictionary words.",
            ["@link" => $link],
        );

        $form["no_language_warning"] = [
            "#type" => "markup",
            "#markup" =>
                '<div class="messages messages--warning" role="alert">' .
                $message .
                "</div>",
            "#weight" => -100,
        ];
    }

    /**
     * Implements hook_form_mukurtu_dictionary_add_word_to_list_form_alter().
     *
     * Opts the "Add to Word List" form into an #ajax submit handler only
     * when opened in a dialog (the ?mukurtu_modal=1 query flag set on the
     * quick-action link in BrowseHooks::browseQuickActionsAlter(), which
     * persists across Drupal's ajax_form submission) - mirrors the same
     * pattern already used for media edit forms
     * (mukurtu_media_form_alter()/mukurtu_media_edit_dialog_ajax()) and for
     * the collection forms (Drupal\mukurtu_collection\Hook\CollectionFormHooks).
     * The existing full-page "Add to Word List" tab is unaffected.
     */
    #[Hook("form_mukurtu_dictionary_add_word_to_list_form_alter")]
    public function formMukurtuDictionaryAddWordToListFormAlter(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        if (!\Drupal::request()->query->get("mukurtu_modal")) {
            return;
        }
        if (isset($form["submit"])) {
            $form["submit"]["#ajax"] = [
                "callback" => [self::class, "addWordToListDialogAjax"],
                "progress" => ["type" => "throbber"],
            ];
        }
    }

    /**
     * AJAX callback for the Add to Word List form submitted from a
     * browse-card modal.
     */
    public static function addWordToListDialogAjax(array &$form, FormStateInterface $form_state): AjaxResponse
    {
        $response = new AjaxResponse();
        $node_id = $form_state->getValue("node");

        if ($form_state->isExecuted() && !$form_state->getErrors()) {
            $response->addCommand(new CloseModalDialogCommand());
            foreach (\Drupal::messenger()->all() as $type => $messages) {
                foreach ($messages as $message) {
                    $response->addCommand(new MessageCommand($message, null, ["type" => $type]));
                }
            }
            \Drupal::messenger()->deleteAll();
            // May match more than one element if the same node is rendered
            // more than once on the page; harmless, focus lands on one of
            // the valid triggering icons.
            $response->addCommand(new InvokeCommand('[data-quick-action-trigger="word-list-' . $node_id . '"]', "focus"));
            return $response;
        }

        foreach ($form_state->getErrors() as $error) {
            \Drupal::messenger()->addError($error);
        }
        $renderer = \Drupal::service("renderer");
        $status_messages = ["#type" => "status_messages"];
        $form["#prefix"] = ($form["#prefix"] ?? "") . $renderer->renderRoot($status_messages);
        $output = $renderer->renderRoot($form);
        $response->setAttachments($form["#attached"]);
        $response->addCommand(new ReplaceCommand(".ui-dialog-content", $output));
        return $response;
    }
}
