<?php

namespace Drupal\mukurtu_footer\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for mukurtu_footer forms.
 */
class FormHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * Core's LinkWidget always shows its own generic help text ("Start typing
   * the title of a piece of content...") on the URL input. When the field
   * also declares its own #description, core either merges it in awkwardly
   * (title-enabled fields get it appended after the "Link text" input,
   * outside the URL field's own description) or, for multi-value fields,
   * shows it only once below every row. Neither placement makes clear which
   * field the guidance is about, so replace the URL input's description
   * outright with field-specific guidance instead (#2159).
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    if (!isset($element['uri'])) {
      return;
    }

    $field_definition = $context['items']->getFieldDefinition();
    $key = implode('.', [
      $field_definition->getTargetEntityTypeId(),
      $field_definition->getTargetBundle(),
      $field_definition->getName(),
    ]);

    $descriptions = [
      'paragraph.footer_social_link.field_footer_social_url' => $this->t('Enter the organization&#8217;s page for the platform selected above, for example https://www.facebook.com/yourorganization.'),
      'block_content.mukurtu_footer.field_footer_other_links' => $this->t('Link to organizational pages, partners, privacy policy, etc., for example https://example.org/privacy-policy. The link title below is used as the display label.'),
      'paragraph.footer_logo.field_footer_logo_link' => $this->t('Wrap the logo in a link to this URL, for example your organization&#8217;s homepage.'),
    ];

    if (isset($descriptions[$key])) {
      $element['uri']['#description'] = $descriptions[$key];
    }
  }

}
