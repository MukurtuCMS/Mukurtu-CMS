<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_core\Plugin\Field\FieldType\EntityReferenceRoleItem;
use Drupal\tagify\Plugin\Field\FieldWidget\TagifyEntityReferenceAutocompleteWidget;
use Drupal\taxonomy\TermInterface;

/**
 * Tagify for names, shown as one row per person with a role box beside it.
 *
 * The names input is the contrib Tagify element, unchanged. A script
 * (js/entity-reference-role-tagify.js) keeps one role box per chip, in chip
 * order, and writes the roles to a hidden field as a JSON array, the same way
 * Tagify submits the names. massageFormValues() pairs them back up one chip
 * at a time.
 *
 * Extends the contrib Tagify widget, so it is only available where tagify is
 * enabled; plugin discovery skips it otherwise. Every module that ships one
 * of the person fields already depends on tagify.
 */
#[FieldWidget(
  id: 'mukurtu_entity_reference_role_tagify',
  label: new TranslatableMarkup('Tagify with roles'),
  field_types: ['mukurtu_entity_reference_role'],
  multiple_values: TRUE,
)]
class EntityReferenceRoleTagifyWidget extends TagifyEntityReferenceAutocompleteWidget {

  /**
   * Unsaved role terms created while massaging one submission, by name.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $newRoles = [];

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $names = parent::formElement($items, $delta, $element, $form, $form_state);
    $roles_enabled = EntityReferenceRoleItem::rolesEnabled($this->fieldDefinition->getName());

    // Tagify's own input now sits below the rows, apart from the field
    // label, so give it a visible prompt unless the site set a placeholder.
    // The contrib script parses data-placeholder as an integer, so Tagify
    // falls back to the input's placeholder attribute.
    if ($roles_enabled && ($names['#placeholder'] ?? '') === '') {
      $names['#placeholder'] = $this->t('Add a name');
      $names['#attributes']['placeholder'] = $names['#placeholder'];
    }

    // Tagify's help line says only "Drag to re-order", and calls people
    // terms. Rows can also be moved with buttons.
    $drag_message = $this->t('Drag or use the arrow buttons to reorder.');
    if (!$roles_enabled) {
      // Names only: Tagify as usual, with its own help line.
    }
    elseif (($names['#description']['#theme'] ?? NULL) === 'item_list') {
      $names['#description']['#items'][array_key_last($names['#description']['#items'])] = $drag_message;
    }
    elseif (!empty($names['#description'])) {
      $names['#description'] = $drag_message;
    }

    // The Tagify element's default chips are $items->referencedEntities(),
    // which skips references to deleted entities, so the roles are built
    // from the same filtered list to keep them in step.
    $roles = [];
    $role_ids = [];
    foreach ($items as $item) {
      if ($item->entity) {
        $role_ids[] = $item->role_target_id;
      }
    }
    $role_terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple(array_filter($role_ids));
    foreach ($role_ids as $role_id) {
      // Plain names, as Tagify shows; resolveRole() matches them by name.
      $roles[] = isset($role_terms[$role_id]) ? \Drupal::service('entity.repository')->getTranslationFromContext($role_terms[$role_id])->label() : '';
    }

    $role_bundles = $this->getFieldSetting('role_target_bundles') ?: [];
    $entity = $items->getEntity();

    return [
      '#type' => 'container',
      // With roles off, the script shows plain Tagify chips but still
      // submits each person's existing role, so saving doesn't lose them.
      '#attributes' => [
        'class' => array_filter([
          'mukurtu-role-tagify',
          $roles_enabled ? NULL : 'mukurtu-role-tagify--names-only',
        ]),
      ],
      '#cache' => ['tags' => ['config:' . EntityReferenceRoleItem::SETTINGS]],
      '#attached' => ['library' => ['mukurtu_core/entity_reference_role_tagify']],
      'names' => $names,
      'roles' => [
        '#type' => 'hidden',
        '#default_value' => json_encode($roles),
        '#attributes' => ['class' => ['mukurtu-role-tagify__roles']],
      ],
      // The script fills this with one row per person (name chip, remove
      // and move buttons, role box) and moves it above the Tagify input,
      // whose own chips it hides.
      'people' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['mukurtu-role-tagify__people']],
        // The script clones this box for each chip; the clones have no name
        // attribute, so only the hidden roles field is submitted.
        'prototype' => [
          '#type' => 'entity_autocomplete',
          '#title' => $this->t('Role'),
          '#title_display' => 'invisible',
          '#target_type' => 'taxonomy_term',
          '#selection_handler' => 'default:taxonomy_term',
          '#selection_settings' => ['target_bundles' => $role_bundles],
          '#validate_reference' => FALSE,
          '#autocreate' => count($role_bundles) === 1 ? [
            'bundle' => reset($role_bundles),
            'uid' => method_exists($entity, 'getOwnerId') ? $entity->getOwnerId() : $this->currentUser->id(),
          ] : NULL,
          '#wrapper_attributes' => [
            'class' => ['mukurtu-role-tagify__prototype'],
            'hidden' => 'hidden',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues($values, array $form, FormStateInterface $form_state): array {
    $names = json_decode((string) ($values['names'] ?? ''), associative: TRUE);
    if (!is_array($names)) {
      return [];
    }
    $roles = json_decode((string) ($values['roles'] ?? ''), associative: TRUE);
    $roles = is_array($roles) ? array_values($roles) : [];

    $items = [];
    // New roles created during this submission, by name, so two people given
    // the same new role share one term instead of creating two.
    $this->newRoles = [];
    foreach (array_values($names) as $position => $tag) {
      // One chip at a time, so a name the parent can't resolve (and drops)
      // can't shift every later role onto the wrong name.
      $resolved = parent::massageFormValues(json_encode([$tag]), $form, $form_state);
      if (!$resolved) {
        continue;
      }
      $item = reset($resolved);
      $role_input = trim((string) ($roles[$position] ?? ''));
      $role = $this->resolveRole($role_input);
      // Errors can only be set while the form is validating; this also runs
      // from submit handlers that rebuild the entity.
      if ($role_input !== '' && !$role && !$form_state->isValidationComplete()) {
        // Only possible when several role vocabularies are allowed, so a new
        // role can't be created; don't drop it silently.
        $form_state->setErrorByName($this->fieldDefinition->getName(), $this->t('"@role" isn\'t an available role. Choose one from the suggestions.', ['@role' => $role_input]));
      }
      if ($role instanceof TermInterface && $role->isNew()) {
        $item['role_entity'] = $role;
      }
      elseif ($role) {
        $item['role_target_id'] = $role->id();
      }
      $items[] = $item;
    }
    return $items;
  }

  /**
   * Turns a role box's text into an existing or new role term.
   *
   * See EntityReferenceRoleItem::resolveRole(). Unsaved new terms are
   * shared through $newRoles, so two people given the same new role in one
   * submission get one term.
   */
  protected function resolveRole(string $input): ?TermInterface {
    return EntityReferenceRoleItem::resolveRole($this->fieldDefinition, $input, $this->newRoles);
  }

}
