<?php

namespace Drupal\mukurtu_media\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'external_embed_widget' widget.
 */
#[FieldWidget(
  id: 'external_embed_widget',
  label: new TranslatableMarkup('External Embed Widget'),
  field_types: ['text_long'],
)]
class ExternalEmbedWidget extends StringTextareaWidget
{
  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state)
  {
    foreach ($values as $delta => $value) {
      $values[$delta]['format'] = 'full_html';
    }
    return $values;
  }
}
