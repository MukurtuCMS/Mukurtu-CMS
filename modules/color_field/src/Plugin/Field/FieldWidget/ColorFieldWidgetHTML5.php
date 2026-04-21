<?php

declare(strict_types=1);

namespace Drupal\color_field\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the color_field spectrum widget.
 *
 * @FieldWidget(
 *   id = "color_field_widget_html5",
 *   module = "color_field",
 *   label = @Translation("Color HTML5"),
 *   field_types = {
 *     "color_field_type"
 *   }
 * )
 */
class ColorFieldWidgetHTML5 extends ColorFieldWidgetBase {

  /**
   * Drupal token service container.
   *
   * @var Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, Token $token) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show_extra' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['show_extra'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always show an extra, empty widget (Drupal default). Force the user to explicitly add a new widget if needed.'),
      '#default_value' => $this->getSetting('show_extra'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    if (!$this->getSetting('show_extra')) {
      $summary[] = $this->t('Suppress extra, empty widget.');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['color']['#type'] = 'color';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $elements = parent::formMultipleElements($items, $form, $form_state);
    if (!$this->getSetting('show_extra') && $elements['#max_delta'] > 0) {
      $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();
      if ($cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
        // Remove the last numerically keyed entry.
        $prev_key = NULL;
        foreach ($elements as $key => $element) {
          if (is_numeric($key)) {
            $prev_key = $key;
          }
          else {
            unset($elements[$prev_key]);
            break;
          }
        }
      }
    }
    return $elements;
  }

  /**
   * Ajax submit callback for the "Remove" button.
   *
   * This re-numbers form elements and removes an item.
   *
   * @param array $form
   *   The form array to remove elements from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function deleteSubmit(&$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $delta = (int) $button['#delta'];
    $array_parents = array_slice($button['#array_parents'], 0, -4);
    $parent_element = NestedArray::getValue($form, array_merge($array_parents, ['widget']));
    $field_name = $parent_element['#field_name'];
    $parents = $parent_element['#field_parents'];
    $field_state = static::getWidgetState($parents, $field_name, $form_state);
    $user_input = $form_state->getUserInput();
    $field_input = NestedArray::getValue($user_input, $parent_element['#parents'], $exists);
    if ($exists) {
      $field_values = [];
      foreach ($field_input as $key => $input) {
        if (is_numeric($key) && $key >= $delta) {
          if ((int) $key === $delta) {
            --$key;
            continue;
          }
        }
        $field_values[$key] = $input;
      }
      NestedArray::setValue($user_input, $parent_element['#parents'], $field_values);
      $form_state->setUserInput($user_input);
    }

    unset($parent_element[$delta]);
    NestedArray::setValue($form, $array_parents, $parent_element);

    if ($field_state['items_count'] > 0) {
      $field_state['items_count']--;
    }

    $user_input = $form_state->getUserInput();
    $input = NestedArray::getValue($user_input, $parent_element['#parents'], $exists);
    $weight = -1 * $field_state['items_count'];
    foreach ($input as $key => $item) {
      if ($item) {
        $input[$key]['_weight'] = $weight++;
      }
    }
    // Reset indices.
    $input = array_values($input);

    $user_input = $form_state->getUserInput();
    NestedArray::setValue($user_input, $parent_element['#parents'], $input);
    $form_state->setUserInput($user_input);
    static::setWidgetState($parents, $field_name, $form_state, $field_state);
    $form_state->setRebuild();
  }

  /**
   * Ajax refresh callback for the "Remove" button.
   *
   * This returns the new widget element content to replace
   * the previous content made obsolete by the form submission.
   *
   * @param array $form
   *   The form array to remove elements from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function deleteAjax(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    return NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -3));
  }

}
