<?php

namespace Drupal\mukurtu_digital_heritage\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;

/**
 * Hook implementations for mukurtu_digital_heritage forms.
 */
class FormHooks
{
    #[Hook("form_node_digital_heritage_form_alter")]
    public function formNodeDigitalHeritageFormAlter(
        array &$form,
        FormStateInterface $form_state,
    ): void {
        if (isset($form['field_external_links'])) {
            foreach (Element::children($form['field_external_links']) as $delta) {
                if (isset($form['field_external_links'][$delta]['uri'])) {
                    $form['field_external_links'][$delta]['uri']['#description'] = t('This must be an external URL starting with <em>https://</em> or <em>http://</em>. For example: <em>https://mukurtu.org</em>.');
                }
            }
        }
    }
}
