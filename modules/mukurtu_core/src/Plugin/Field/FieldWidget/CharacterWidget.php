<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_core\Plugin\Field\FieldType\CharacterItem;

/**
 * A Character box and a Name box on each row (#2560).
 */
#[FieldWidget(
  id: 'mukurtu_character',
  label: new TranslatableMarkup('Character and name'),
  field_types: ['mukurtu_character'],
)]
class CharacterWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Every row repeats the same two labels, so the row number is added for
    // screen readers only. The visible words still start the accessible name
    // (WCAG 2.5.3).
    $number = $delta + 1;

    $element += [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline', 'mukurtu-character-widget']],
    ];
    $element['character'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Character<span class="visually-hidden"> @number</span>', ['@number' => $number]),
      '#default_value' => $items[$delta]->character ?? NULL,
      '#maxlength' => CharacterItem::CHARACTER_MAX_LENGTH,
      '#size' => 6,
    ];
    $element['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name<span class="visually-hidden"> for character @number</span>', ['@number' => $number]),
      '#default_value' => $items[$delta]->name ?? NULL,
      '#maxlength' => CharacterItem::NAME_MAX_LENGTH,
      '#size' => 30,
    ];
    return $element;
  }

}
