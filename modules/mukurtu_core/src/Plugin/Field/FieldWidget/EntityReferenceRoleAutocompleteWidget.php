<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;

/**
 * Autocomplete widget for a name plus an optional role on each row.
 */
#[FieldWidget(
  id: 'mukurtu_entity_reference_role_autocomplete',
  label: new TranslatableMarkup('Autocomplete with role'),
  field_types: ['mukurtu_entity_reference_role'],
)]
class EntityReferenceRoleAutocompleteWidget extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $parent = parent::formElement($items, $delta, $element, $form, $form_state);

    // In a multi-value table the field label is visually hidden on each row,
    // so give each input its own visible label.
    $name = $parent['target_id'];
    $name['#title'] = $this->t('Name');
    $name['#title_display'] = 'before';
    // Drupal sets the row's delta as the main input's weight, which would
    // sort Name after Role on every row but the first.
    $name['#weight'] = 0;
    $name['#description'] = '';

    $role_bundles = $this->getFieldSetting('role_target_bundles') ?: [];
    $role_id = $items[$delta]->role_target_id ?? NULL;
    $role_default = $role_id ? \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($role_id) : NULL;

    $role = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Role'),
      '#weight' => 1,
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => $role_bundles,
        'match_operator' => $this->getSetting('match_operator'),
        'match_limit' => $this->getSetting('match_limit'),
      ],
      '#validate_reference' => FALSE,
      '#maxlength' => 1024,
      '#default_value' => $role_default,
      '#size' => $this->getSetting('size'),
    ];
    if (count($role_bundles) === 1) {
      $entity = $items->getEntity();
      $role['#autocreate'] = [
        'bundle' => reset($role_bundles),
        'uid' => ($entity instanceof EntityOwnerInterface) ? $entity->getOwnerId() : \Drupal::currentUser()->id(),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['mukurtu-entity-reference-role', 'container-inline']],
      'target_id' => $name,
      'role_target_id' => $role,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state);
    foreach ($values as $key => $value) {
      $role = $value['role_target_id'] ?? NULL;
      // A role typed for the first time comes back as an unsaved term, which
      // the field item saves in preSave(), as core does for the name.
      if (is_array($role)) {
        $values[$key]['role_target_id'] = NULL;
        if (isset($role['entity'])) {
          $values[$key]['role_entity'] = $role['entity'];
        }
      }
      elseif ($role === '') {
        $values[$key]['role_target_id'] = NULL;
      }
    }
    return $values;
  }

}
