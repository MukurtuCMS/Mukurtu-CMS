<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\NodeInterface;
use Drupal\media\MediaInterface;
use Drupal\Core\Access\AccessResult;

/**
 * VBO for managing protocols.
 *
 * @Action(
 *   id = "mukurtu_set_protocol_action",
 *   label = @Translation("Set protocols"),
 *   type = "",
 *   confirm = TRUE,
 *   requirements = {
 *     "_custom_access" = FALSE,
 *   },
 * )
 */
class MukurtuSetProtocolAction extends ViewsBulkOperationsActionBase implements PluginFormInterface {

  use StringTranslationTrait;

  protected $protocolFields = [
    MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE,
    MUKURTU_PROTOCOL_FIELD_NAME_READ,
    MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE,
    MUKURTU_PROTOCOL_FIELD_NAME_WRITE,
  ];

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity) {
      return;
    }

    // Are we changing inheritance?
    $inheritance = $this->configuration[MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET];
    if ($inheritance) {
      $inheritance_target_id = str_replace('node:', '', $inheritance);
      if (is_numeric($inheritance_target_id)) {
        // Set the inheritance target and save.
        $entity->set(MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET, $inheritance_target_id);
        $entity->save();

        // We don't need to check the rest of the protocol fields as they
        // will be inherited.
        $target = \Drupal::entityTypeManager()->getStorage('node')->load($inheritance_target_id);
        return $this->t('Selected items now inherit protocols from @inherit', ['@inherit' => $target->getTitle()]);
      }
    }

    // Not using inheritance, set all the protocol fields to the new values.
    $entity->set(MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET, NULL);
    $entity->set(MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE, $this->configuration[MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE]);
    $entity->set(MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE, $this->configuration[MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE]);
    $entity->set(MUKURTU_PROTOCOL_FIELD_NAME_READ, $this->configuration[MUKURTU_PROTOCOL_FIELD_NAME_READ]);
    $entity->set(MUKURTU_PROTOCOL_FIELD_NAME_WRITE, $this->configuration[MUKURTU_PROTOCOL_FIELD_NAME_WRITE]);
    $entity->save();
    return $this->t('Set protocols for selected items');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof NodeInterface || $object instanceof MediaInterface) {
      // Access is simply update access.
      return $object->access('update', $account, $return_as_object);
    }

    return $return_as_object ? FALSE : AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public static function customAccess(AccountInterface $account = NULL, ViewExecutable $view) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $node = ['type' => 'digital_heritage'];
    $fakeNode = \Drupal::service('entity_type.manager')->getStorage('node')->create($node);
    $entityFormDisplay = \Drupal::service('entity_type.manager')->getStorage('entity_form_display')
      ->load('node.digital_heritage.default');

    $form['#parents'] = [];

    // Protocol Inheritance field first.
    if ($widget = $entityFormDisplay->getRenderer(MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET)) {
      $protocol = $fakeNode->get(MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET);
      $form[MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET] = $widget->form($protocol, $form, $form_state);
    }

    // Render the protocol fields.
    foreach ($this->protocolFields as $field) {
      if ($widget = $entityFormDisplay->getRenderer($field)) {
        $protocol = $fakeNode->get($field);
        $form[$field] = $widget->form($protocol, $form, $form_state);
        $form[$field]['#states']['visible'][] = [':input[name="field_mukurtu_protocol_inherit[target_id]"]' => ['value' => '']];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration[MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET] = $form_state->getValue(MUKURTU_PROTOCOL_FIELD_NAME_INHERITANCE_TARGET)['target_id'];

    // Scopes.
    $this->configuration[MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE] = $form_state->getValue(MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE)[0]['value'];
    $this->configuration[MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE] = $form_state->getValue(MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE)[0]['value'];

    // Protocol lists.
    foreach ([MUKURTU_PROTOCOL_FIELD_NAME_READ, MUKURTU_PROTOCOL_FIELD_NAME_WRITE] as $field) {
      $values = $form_state->getValue($field);
      $targets = [];
      foreach ($values as $value) {
        if (is_array($value) && isset($value['target_id']) && $value['target_id'] > 0) {
          $targets[] = $value['target_id'];
        }
      }

      $this->configuration[$field] = $targets;
    }
  }

}
