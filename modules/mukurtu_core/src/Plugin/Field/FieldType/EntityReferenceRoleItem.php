<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Field\FieldType;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\taxonomy\TermInterface;

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
   * Config listing which shipped person fields record roles.
   */
  const SETTINGS = 'mukurtu_core.person_roles';

  /**
   * The shipped person fields whose roles managers can turn on and off.
   *
   * Any other field of this type always shows roles: a site builder who
   * chose this field type has already opted in.
   */
  const MANAGED_FIELDS = ['field_creator', 'field_contributor', 'field_people'];

  /**
   * Whether a field's roles are shown on forms and displays.
   *
   * When off, roles are hidden but kept: widgets carry each person's
   * existing role through a save, and turning roles back on shows them all.
   * Anything rendering by this answer should add SETTINGS' cache tag.
   */
  public static function rolesEnabled(string $field_name): bool {
    if (!in_array($field_name, self::MANAGED_FIELDS, TRUE)) {
      return TRUE;
    }
    $enabled = \Drupal::config(self::SETTINGS)->get('enabled_fields') ?? [];
    return in_array($field_name, $enabled, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'role_target_bundles' => ['role' => 'role'],
    ] + parent::defaultFieldSettings();
  }

  /**
   * Finds or creates the role term for some text typed or imported.
   *
   * Accepts a term ID or UUID (as an ID-based export writes), Drupal's
   * autocomplete format ("Singer (5)"), or a name, matched without regard to
   * case within the field's role vocabularies. An unknown name becomes a new,
   * unsaved term when exactly one role vocabulary is allowed; otherwise there
   * is nowhere to create it and NULL is returned.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The reference-with-role field.
   * @param string $input
   *   The role text. Blank means no role.
   * @param \Drupal\taxonomy\TermInterface[] $created
   *   Unsaved terms already created by this caller, keyed by lowercase name,
   *   so repeated new roles share one term. Updated in place.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The role term, possibly new and unsaved, or NULL for no role.
   */
  public static function resolveRole(FieldDefinitionInterface $field_definition, string $input, array &$created = []): ?TermInterface {
    $input = trim($input);
    if ($input === '') {
      return NULL;
    }
    $bundles = array_values($field_definition->getSetting('role_target_bundles') ?: []);
    if (!$bundles) {
      return NULL;
    }
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $allowed = fn ($term): bool => $term instanceof TermInterface && in_array($term->bundle(), $bundles, TRUE);

    if (Uuid::isValid($input)) {
      $matches = $storage->loadByProperties(['uuid' => $input]);
      if ($allowed($match = reset($matches))) {
        return $match;
      }
    }
    $id = ctype_digit($input) ? $input : EntityAutocomplete::extractEntityIdFromAutocompleteInput($input);
    if ($id && $allowed($term = $storage->load($id))) {
      return $term;
    }

    $name = preg_replace('/\s\(\d+\)$/', '', $input);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', $bundles, 'IN')
      ->condition('name', $name)
      ->range(0, 1)
      ->execute();
    if ($ids) {
      return $storage->load(reset($ids));
    }

    if (count($bundles) === 1) {
      return $created[mb_strtolower($name)] ??= $storage->create(['name' => $name, 'vid' => reset($bundles)]);
    }
    return NULL;
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
   * Setting an item to an entity on its own (an ID or an object, as code
   * and imports do when they assign a list of names) means a different
   * person may now be in this position, so the previous role is cleared
   * rather than silently moving onto them. Setting an array keeps whatever
   * role it includes.
   */
  public function setValue($values, $notify = TRUE) {
    if (isset($values) && !is_array($values)) {
      $this->writePropertyValue('role_target_id', NULL);
    }
    parent::setValue($values, $notify);
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
