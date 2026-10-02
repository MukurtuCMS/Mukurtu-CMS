<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * An entity reference with an optional role term for each value.
 *
 * Extends EntityReferenceItem, rather than wrapping it, so everything that
 * recognizes entity reference items by class keeps working unchanged: core's
 * taxonomy_index, Search API's "field:entity:name" property paths, and the
 * stock entity reference widgets and formatters (see
 * \Drupal\mukurtu_core\Hook\EntityReferenceRoleHooks).
 *
 * The role is stored as a bare term ID. There is deliberately no computed
 * role entity property yet; EntityReferenceItem keeps target_id and entity
 * in sync by hand, and the same work for the role isn't needed until the
 * role is indexed for search.
 */
#[FieldType(
  id: 'mukurtu_entity_reference_role',
  label: new TranslatableMarkup('Entity reference with role'),
  description: new TranslatableMarkup('References an entity and an optional role term for each value.'),
  category: 'reference',
  default_widget: 'mukurtu_entity_reference_role_autocomplete',
  default_formatter: 'mukurtu_entity_reference_role_label',
  list_class: EntityReferenceFieldItemList::class,
)]
class EntityReferenceRoleItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'role_target_bundles' => ['role' => 'role'],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::fieldSettingsForm($form, $form_state);

    $options = [];
    foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple() as $vid => $vocabulary) {
      $options[$vid] = $vocabulary->label();
    }
    $form['role_target_bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Role vocabularies'),
      '#description' => $this->t('Vocabularies that roles can be chosen from. New roles can be added from the form only when one vocabulary is selected.'),
      '#options' => $options,
      '#default_value' => $this->getSetting('role_target_bundles') ?: [],
      '#element_validate' => [[static::class, 'validateRoleTargetBundles']],
    ];
    return $form;
  }

  /**
   * Stores only the checked vocabularies, keyed and valued by ID.
   */
  public static function validateRoleTargetBundles(array $element, FormStateInterface $form_state): void {
    $selected = array_filter($element['#value'] ?? []);
    $form_state->setValueForElement($element, array_combine(array_keys($selected), array_keys($selected)));
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['role_target_id'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Role term ID'))
      ->setSetting('unsigned', TRUE);
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['role_target_id'] = [
      'description' => 'The ID of the role taxonomy term.',
      'type' => 'int',
      'unsigned' => TRUE,
    ];
    $schema['indexes']['role_target_id'] = ['role_target_id'];
    return $schema;
  }

  /**
   * {@inheritdoc}
   *
   * A role typed for the first time arrives from the widget as an unsaved
   * term under 'role_entity', mirroring how core passes a new target entity.
   */
  public function preSave() {
    parent::preSave();
    $role = $this->values['role_entity'] ?? NULL;
    if ($role instanceof EntityInterface) {
      if ($role->isNew()) {
        $role->save();
      }
      $this->role_target_id = $role->id();
      unset($this->values['role_entity']);
    }
  }

}
