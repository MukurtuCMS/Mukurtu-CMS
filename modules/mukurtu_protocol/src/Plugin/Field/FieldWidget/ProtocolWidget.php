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
    $account = User::load(\Drupal::currentUser()->id());
    $value = isset($items[$delta]->value) ? $items[$delta]->value : '';

    // Get the list of protocols the user has access to.
    $protocol_manager = \Drupal::service('mukurtu_protocol.protocol_manager');
    $protocol_nodes = $protocol_manager->getUserProtocolMemberships($account);
    $protocol_options = [];

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

    $element += [
      '#type' => 'select',
      '#default_value' => $value,
      //'#title' => $this->t('Select Protocol'),
      '#options' => $protocol_options,
      '#element_validate' => [
        [static::class, 'validate'],
      ],
    ];

    return ['value' => $element];
  }

  /**
   * Validate the protocol field.
   */
  public static function validate($element, FormStateInterface $form_state) {
    $value = $element['#value'];
    dpm("Validate $value");
  }

}
