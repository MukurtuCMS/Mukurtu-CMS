<?php

namespace Drupal\mukurtu_dictionary\Hook;

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
}
