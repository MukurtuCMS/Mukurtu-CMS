<?php

namespace Drupal\mukurtu_digital_heritage\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * Hook implementations for mukurtu_digital_heritage forms.
 */
class FormHooks
{
    /**
     * Implements hook_form_node_digital_heritage_form_alter().
     *
     * Warns editors when no Category terms exist yet, since the Categories field
     * will be empty and content cannot be properly categorized without them.
     */
    #[Hook("form_node_digital_heritage_form_alter")]
    public function formNodeDigitalHeritageFormAlter(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        // Only show the warning on the add form, not on edits.
        $node = $form_state->getFormObject()->getEntity();
        if (!$node->isNew()) {
            return;
        }

        $term_count = \Drupal::entityQuery("taxonomy_term")
            ->condition("vid", "category")
            ->accessCheck(false)
            ->count()
            ->execute();

        if ($term_count > 0) {
            return;
        }

        $url = Url::fromRoute("entity.taxonomy_vocabulary.overview_form", [
            "taxonomy_vocabulary" => "category",
        ]);
        $link = \Drupal::service("link_generator")->generate(
            t("add categories"),
            $url,
        );
        $message = t(
            "There are no categories available, which is a requirement for digital heritage items. Please @link before creating digital heritage items.",
            ["@link" => $link],
        );

        $form["no_categories_warning"] = [
            "#type" => "markup",
            "#markup" =>
                '<div class="messages messages--warning" role="alert">' .
                $message .
                "</div>",
            "#weight" => -100,
        ];
    }
}
