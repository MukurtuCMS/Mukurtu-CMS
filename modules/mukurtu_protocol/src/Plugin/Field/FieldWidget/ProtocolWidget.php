<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Component\Utility\NestedArray;

/**
 * Plugin implementation of the 'mukurtu_protocol_widget' widget.
 *
 * @FieldWidget(
 *   id = "mukurtu_protocol_widget",
 *   module = "mukurtu_protocol",
 *   label = @Translation("Mukurtu Protocol Picker"),
 *   field_types = {
 *     "og_standard_reference"
 *   }
 * )
 */
class ProtocolWidget extends WidgetBase {

  protected function getOptions($entity_type, $bundle, $account) {
    // Get the list of protocols the user has access to.
    $protocol_manager = \Drupal::service('mukurtu_protocol.protocol_manager');
    $protocol_nodes = $protocol_manager->getValidProtocols('node', $bundle, $account);
    $protocol_options = [-1 => t('Select a Protocol')];

    // Build the options list.
    foreach ($protocol_nodes as $protocol_node) {
      $nid = $protocol_node->id();
      $protocol_community = $protocol_manager->getCommunity($protocol_node);

      // Don't show any orphaned protocols.
      if ($protocol_community) {
        $title = $protocol_community->getTitle();
        $protocol_options[$title][$nid] = $protocol_node->title->value;
      }
    }

    return $protocol_options;
  }

  protected function getElementOptions(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getFieldStorageDefinition()->getName();

    $entity = $items->getEntity();

    if (isset($form[$field_name . '_all_options']['#value'])) {
      $all_options = $form[$field_name . '_all_options']['#value'];
    } else {
      $account = User::load(\Drupal::currentUser()->id());
      $all_options = $this->getOptions($entity->getEntityTypeId(), $entity->bundle(), $account);

      // Add a hidden field with the original protocol options
      // for the AJAX callback.
      $form[$field_name . '_all_options'] = [
        '#type' => 'hidden',
        '#value' => $all_options,
      ];
    }

    // Currently selected protocols.
    $selected_protocols = [];
    $protocols = $form_state->getValue($field_name);
    if (empty($protocols)) {
      // No protocols in the form state, use the current values from the entity.
      $protocol_values = $entity->get($field_name)->referencedEntities();
      foreach ($protocol_values as $protocol) {
        $selected_protocols[] = $protocol->id();
      }
    } else {
      foreach ($protocols as $protocol) {
        if (is_array($protocol) && isset($protocol['target_id']) && $protocol['target_id'] > 0) {
          $selected_protocols[] = $protocol['target_id'];
        }
      }
    }

    // Start with all options.
    $options = $all_options;

    // Currently selected value for delta.
    $current_value = $selected_protocols[$delta] ?? 0;
    foreach ($options as $community => $community_protocols) {
      foreach ($community_protocols as $protocol_id => $protocol_name) {
        // Skip the placeholder options.
        if ($protocol_id < 0) {
          continue;
        }

        // Remove values that have already been selected.
        if ($current_value != $protocol_id && in_array($protocol_id, $selected_protocols)) {
          unset($options[$community][$protocol_id]);
        }
      }

      // If we've removed all options for a given community,
      // remove the community.
      if (empty($options[$community])) {
        unset($options[$community]);
      }
    }

    // Set the new options.
    return $options;

  }

  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {
    $widget_form = parent::form($items, $form, $form_state, $get_delta);
    $field_name = $this->fieldDefinition->getFieldStorageDefinition()->getName();
    $wrapper = 'edit-' . str_replace('_', '-', $field_name) . '-ajax-wrapper';

    // Wrap the entire protocol selector so we can target
    // it in our AJAX callback.
    $widget_form['#prefix'] = "<div id=\"$wrapper\">";
    $widget_form['#suffix'] = "</div>";

    return $widget_form;
  }


  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getFieldStorageDefinition()->getName();
    $wrapper = 'edit-' . str_replace('_', '-', $field_name) . '-ajax-wrapper';

    $referenced_entities = $items->referencedEntities();

    $element += [
      '#type' => 'select',
      '#default_value' => isset($referenced_entities[$delta]) ? $referenced_entities[$delta]->id() : -1,
      '#options' => $this->getElementOptions($items, $delta, $element, $form, $form_state),
      '#element_validate' => [
        [static::class, 'validate'],
      ],
      '#ajax' => [
        'event' => 'change',
        'callback' => [$this, 'protocolChangeCallback'],
        'wrapper' => $wrapper,
      ],
    ];

    return ['target_id' => $element];
  }

  public function protocolChangeCallback(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $element = $form[$triggering_element['#array_parents'][0]];
    $field_name = $this->fieldDefinition->getFieldStorageDefinition()->getName();
    $max_delta = $element['widget']['#max_delta'];

    // All protocol options.
    $all_options = $form_state->getValue($field_name . '_all_options');

    // Currently selected protocols.
    $protocol_values = $form_state->getValue($field_name);
    $selected_protocols = [];
    for ($delta = 0; $delta <= $max_delta; $delta++) {
      $selected_protocols[] = $protocol_values[$delta]['target_id'];
    }

    for ($delta = 0; $delta <= $max_delta; $delta++) {
      // Start with all options.
      $options = $all_options;

      // Currently selected value for delta.
      $current_value = $element['widget'][$delta]['target_id']['#value'];

      foreach ($options as $community => $community_protocols) {
        foreach ($community_protocols as $protocol_id => $protocol_name) {
          // Skip the placeholder options.
          if ($protocol_id < 0) {
            continue;
          }

          // Remove values that have already been selected.
          if ($current_value != $protocol_id && in_array($protocol_id, $selected_protocols)) {
            unset($options[$community][$protocol_id]);
          }
        }

        // If we've removed all options for a given community, remove the community.
        if (empty($options[$community])) {
          unset($options[$community]);
        }
      }

      // Set the new options.
      $element['widget'][$delta]['target_id']['#options'] = $options;
    }

    return $element;
  }

  /**
   * Massage the submitted values of the protocol field.
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $unique = [];
    // Remove any of our default "select a protocol" options.
    foreach ($values as $delta => $value) {
      if ($value['target_id'] == -1) {
        unset($values[$delta]);
      }

      // Remove any duplicates.
      if (!in_array($value['target_id'], $unique)) {
        $unique[] = $value['target_id'];
      } else {
        unset($values[$delta]);
      }
    }

    return $values;
  }

  /**
   * Validate the protocol field.
   */
  public static function validate($element, FormStateInterface $form_state) {
    // Get the protocol scope.
    $field_name = $element['#array_parents'][0];
    $protocol_manager = \Drupal::service('mukurtu_protocol.protocol_manager');
    $scope_field_name = $protocol_manager->getProtocolScopeFieldname($field_name);
    $scope_value = $form_state->getValue($scope_field_name);

    if (isset($scope_value[0]['value'])) {
      $scope = $scope_value[0]['value'];

      // Any or All means we need to have at least one valid protocol selected.
      if ($scope == 'any' || $scope == 'all') {
        $protocol_value = $form_state->getValue($field_name);

        $count = 0;
        foreach ($protocol_value as $delta => $protocol) {
          if (is_numeric($delta) && $protocol['target_id'] != -1) {
            $count += 1;
          }
        }

        if ($count < 1) {
          $form_state->setError($element, t('You must select at least one protocol.'));
        }
      }
    }
  }

}
