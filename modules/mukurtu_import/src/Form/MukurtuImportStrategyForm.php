<?php

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * mukurtu_import_strategy form.
 *
 * @property \Drupal\mukurtu_import\MukurtuImportStrategyInterface $entity
 */
class MukurtuImportStrategyForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the mukurtu_import_strategy.'),
      '#required' => TRUE,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->get('description'),
      '#description' => $this->t('Description.'),
    ];

    $form['target_entity_type_id'] = [
      '#type' => 'select',
      '#options' => ['node' => 'Content', 'media' => 'Media'],
      '#title' => $this->t('Type'),
      '#default_value' => $this->entity->get('target_entity_type_id') ?? 'node',
      '#description' => $this->t('Type of import.'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'entityTypeChangeAjaxCallback'],
        'event' => 'change',
      ],
    ];

    $target_entity_type_id = $form_state->getValue('target_entity_type_id') ?? $this->entity->get('target_entity_type_id');
    $form['target_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Sub-type'),
      '#options' => $this->getBundleOptions($target_entity_type_id),
      '#default_value' => $this->entity->get('target_bundle') ?? -1,
      '#description' => $this->t('Optional Sub-type.'),
      '#prefix' => "<div id=\"bundle-select\">",
      '#suffix' => "</div>",
    ];

    return $form;
  }

  protected function getBundleOptions($entity_type_id) {
    $bundleInfoService = \Drupal::service('entity_type.bundle.info');
    $bundleInfo = $bundleInfoService->getAllBundleInfo();

    if (!isset($bundleInfo[$entity_type_id])) {
      return [-1 => $this->t('No sub-types available')];
    }

    $options = [-1 => $this->t('No sub-type: Base Fields Only')];
    foreach ($bundleInfo[$entity_type_id] as $bundle => $info) {
      $options[$bundle] = $info['label'] ?? $bundle;
    }
    return $options;
  }

  /**
   * Get the field definitions for an entity type/bundle.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @return mixed
   *   The field definitions.
   */
  protected function getFieldDefinitions($entity_type_id, $bundle = NULL) {
    // Memoize the field defs.
    if (empty($this->fieldDefinitions[$entity_type_id][$bundle])) {
      $entityDefinition = $this->entityTypeManager->getDefinition($entity_type_id);
      $entityKeys = $entityDefinition->getKeys();
      $fieldDefs = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);

      // Remove computed fields/fields that can't be targeted for import.
      foreach ($fieldDefs as $field_name => $fieldDef) {
        // Don't remove ID/UUID fields.
        if ($field_name == $entityKeys['id'] || $field_name == $entityKeys['uuid']) {
          continue;
        }

        // Remove computed and read-only fields.
        if ($fieldDef->isComputed() || $fieldDef->isReadOnly()) {
          unset($fieldDefs[$field_name]);
        }
      }
      $this->fieldDefinitions[$entity_type_id][$bundle] = $fieldDefs;
    }

    return $this->fieldDefinitions[$entity_type_id][$bundle];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new mukurtu_import_strategy %label.', $message_args)
      : $this->t('Updated mukurtu_import_strategy %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  public function entityTypeChangeAjaxCallback(array &$form, FormStateInterface $form_state) {
    // Update the field mapping message.
    $response = new AjaxResponse();

    // Check how many fields for this file we have mapped with the selected process.
    $form['target_bundle']['#options'] = $this->getBundleOptions($form_state->getValue('target_entity_type_id'));
    $response->addCommand(new ReplaceCommand("#bundle-select", $form['target_bundle']));

    return $response;
  }

}
