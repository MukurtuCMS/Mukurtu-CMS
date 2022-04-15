<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolControl;
use Exception;

/**
 * Mukurtu Protocol Control widget.
 *
 * @FieldWidget(
 *   id = "mukurtu_protocol_control_widget",
 *   label = @Translation("Mukurtu Protocol Control widget"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *   }
 * )
 */
class ProtocolControlWidget extends WidgetBase {

  /**
   * Helper function to put the values into the protocol control entity.
   */
  protected function syncProtocolControlValues($values, $pcEntity) {
    // Privacy Setting.
    $field_privacy_setting = $values[0]['field_sharing_setting'] ?? NULL;
    if ($field_privacy_setting) {
      $pcEntity->setPrivacySetting($field_privacy_setting);
    }

    // Protocols.
    $protocols = $values[0]['field_protocols'] ?? NULL;
    if ($protocols) {
      $protocols = array_column($protocols, 'target_id');
      $pcEntity->setProtocols($protocols);
    }

    // Inheritance.
    if (isset($values[0]['field_inheritance_target'])) {
      $inheritance = $values[0]['field_inheritance_target'];
      if ($inheritance) {
        $target = array_column($inheritance, 'target_id');
        $pcEntity->setInheritanceTargetId($target);
      }
    }

    return $pcEntity;
  }

  /**
   * Custom submit handler for the protocol control widget.
   */
  public static function customSubmit(array $form, FormStateInterface $form_state) {
    $needSave = FALSE;
    $protocolControlValues = $form_state->getValue('field_protocol_control')[0] ?? NULL;

    /**
     * @var \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface $entity
     */
    $pcEntity = $form_state->get('protocol_control_entity');
    $entity = $form_state->getFormObject()->getEntity();


    // If we have both a protocol control entity and some new user given values,
    // set the new values as needed and update the protocol control entity.
    if ($pcEntity && $protocolControlValues) {
      // Privacy Setting.
      $field_privacy_setting = $protocolControlValues['field_sharing_setting'] ?? NULL;
      if ($field_privacy_setting && $pcEntity->getPrivacySetting() != $field_privacy_setting) {
        $pcEntity->setPrivacySetting($field_privacy_setting);
        $needSave = TRUE;
      }

      // Protocols.
      $protocols = $protocolControlValues['field_protocols'] ?? NULL;
      $protocols = array_column($protocols, 'target_id');
      if (!empty(array_diff($protocols, $pcEntity->getProtocols()))) {
        $pcEntity->setProtocols($protocols);
        $needSave = TRUE;
      }

      // Inheritance.
      if (isset($protocolControlValues['field_inheritance_target'])) {
        $newInheritanceTargetId = $protocolControlValues['field_inheritance_target'][0]['target_id'] ?? NULL;
        $currentTarget = $pcEntity->getInheritanceTarget();
        $currentTargetId = $currentTarget ? $currentTarget->id() : NULL;
        if ($newInheritanceTargetId != $currentTargetId) {
          $pcEntity->setInheritanceTargetId($newInheritanceTargetId);
          $needSave = TRUE;
        }
      }

      // Save the protocol control entity if data was altered.
      if ($needSave) {
        try {
          $pcEntity->setControlledEntity($entity);
          $violations = $pcEntity->validate();
          if ($violations->count() == 0) {
            $pcEntity->save();
          }
          else {
            \Drupal::messenger()->addError(t("Failed to update protocols."));
          }
        }
        catch (Exception $e) {
          // Can't use DI because we're in a static method.
          \Drupal::messenger()->addError(t("Failed to update protocols."));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $item = $items->get($delta);
    $entity = $items->getEntity();

    $element = [
      '#type' => 'fieldset',
      '#field_title' => $this->fieldDefinition->getLabel(),
      '#open' => TRUE,
    ] + $element;

    /**
     * @var \Drupal\mukurtu_protocol\Entity\ProtocolControlInterface $pcEntity
     */
    $pcEntity = $item->entity;
    if (is_null($pcEntity)) {
      // Create a new PCE if we need one.
      $name = "{$entity->getEntityTypeId()}:{$entity->uuid()}";
      $pcEntity = ProtocolControl::create([
        'name' => $name,
        'field_sharing_setting' => 'all',
      ]);
      $pcEntity->setControlledEntity($entity);
    }

    if (is_null($pcEntity) || !$pcEntity->access('update')) {
      return [];
    }

    // Keep the protocol control entity for this item in the form.
    // if the user changes any of its fields in this widget we can
    // update it in ::customSubmit.
    $form_state->set('protocol_control_entity', $pcEntity);

    $element['target_id'] = [
      '#type' => 'hidden',
      '#element_validate' => [
        [static::class, 'validate'],
      ],
      '#default_value' => $pcEntity->id() ?? $pcEntity->uuid(),
    ];

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $form_display = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load('protocol_control.protocol_control.default');
    $fieldnames = [
      'field_protocols',
      'field_sharing_setting',
      //'field_inheritance_target',
    ];
    foreach ($fieldnames as $name) {
      /**
       * @var \Drupal\Core\Field\PluginSettingsInterface $widget
       */
      $widget = $form_display->getRenderer($name);

      /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $items */
      $items = $pcEntity->get($name);

      $items->filterEmptyItems();
      /** @var \Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget $widget */
      // UID 1 should be the only user to be able to throw an exception here.
      // It happens when a user can skip the createAccess check for protocol
      // controlled entities and they do not have any protocols they have
      // 'apply protocol' permission for. In this case, we don't render
      // the widget.
      try {
        $mockedWidget = $widget->form($items, $form, $form_state);
      }
      catch (Exception $e) {
        return [];
      }
      $element[$name]['#access'] = $items->access('update');
      $element[$name] = $mockedWidget['widget'];
      unset($element[$name]['#parents']);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $pcEntity = $form_state->get('protocol_control_entity');

    // Copy the widget values over to the PCE.
    $pcEntity = $this->syncProtocolControlValues($values, $pcEntity);

    // If the PCE is new, save it and change the reference to use the ID.
    if ($pcEntity->isNew()) {
      try {
        // Save the PCE.
        $pcEntity->save();
        $form_state->set('protocol_control_entity', $pcEntity);
      }
      catch (Exception $e) {
        // Blank.
      }
    }

    // Alter the entity reference to use the ID.
    $values[0]['target_id'] = $pcEntity->id();

    $form_state->set('protocol_control_entity', $pcEntity);

    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    if ($field_definition->getSetting('target_type') == 'protocol_control') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public static function validate($element, FormStateInterface $form_state) {
    // $form_state->input["field_protocol_control"][0]['field_inheritance_target'][0]['target_id']
    // PCEs are assigned at entity creation and the reference should not be
    // altered in the form ever.
    if (is_numeric($element['#value']) && is_numeric($element['#default_value'])) {
      if ($element['#value'] != $element['#default_value']) {
        $form_state->setError($element, t("The protocol control reference cannot be changed after creation."));
      }
    }

    // @todo Need to do proper widget validation here.
  }

}
