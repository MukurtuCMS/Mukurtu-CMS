<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

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

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();
    $bundle = $entity->bundle();
    $account = User::load(\Drupal::currentUser()->id());

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

        // TODO: Filter out protocols that are already selected.
        $protocol_options[$title][$nid] = $protocol_node->title->value;
      }
    }

    $referenced_entities = $items->referencedEntities();

    $element += [
      '#type' => 'select',
      '#default_value' => isset($referenced_entities[$delta]) ? $referenced_entities[$delta]->id() : -1,
      '#options' => $protocol_options,
      '#element_validate' => [
        [static::class, 'validate'],
      ],
    ];

    return ['target_id' => $element];
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
