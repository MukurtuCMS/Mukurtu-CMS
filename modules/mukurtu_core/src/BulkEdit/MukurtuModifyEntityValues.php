<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\BulkEdit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\views_bulk_edit\Plugin\Action\ModifyEntityValues;

/**
 * Views Bulk Edit's "Modify field values" action, adjusted for Mukurtu.
 *
 * - Needs the "Use bulk editing" permission, on top of edit access to each
 *   item.
 * - Always saves a new revision, so every bulk change can be undone.
 * - Names each "Change method" choice after its field, and explains how
 *   translations are affected.
 * - Validates the changed fields of each item before saving it, which Views
 *   Bulk Edit doesn't, so a bulk edit can't empty a required field. Items
 *   that fail are skipped and reported; the rest still save.
 * - Never makes an item reference itself. The single-item browsers leave the
 *   item being edited out, but a bulk selection has no single such item.
 * - Stays safe across batches. A value that references a new entity, such as
 *   a keyword typed into an autocreate widget, is stored in the action
 *   configuration as an unsaved entity. VBO restores that configuration
 *   fresh for every batch, so each batch after the first tried to save the
 *   same entity again and failed on its UUID. Such references are resolved
 *   to an ID before applying them: the existing entity with that UUID if
 *   there is one, otherwise the entity saved once.
 *
 * Lives outside Plugin/Action because plugin discovery reflects every class
 * there, and this one's parent only loads when views_bulk_edit is enabled.
 * mukurtu_core swaps it in with hook_action_info_alter().
 *
 * @see \Drupal\mukurtu_core\Hook\BulkEditHooks::actionInfoAlter()
 */
class MukurtuModifyEntityValues extends ModifyEntityValues {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account ??= \Drupal::currentUser();
    if (!$account->hasPermission('use mukurtu bulk edit')) {
      $result = AccessResult::forbidden()->cachePerPermissions();
      return $return_as_object ? $result : FALSE;
    }
    return parent::access($object, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $this->resolveNewEntities();
    $configuration = $this->configuration;
    $this->dropSelfReferences($entity);
    try {
      return $this->applyValues($entity);
    }
    finally {
      $this->configuration = $configuration;
    }
  }

  /**
   * Applies the configured values to one item, validates and saves it.
   *
   * Mirrors BulkEditFormTrait::execute() from views_bulk_edit 3.0.1, adding
   * validation of the changed fields before the save.
   */
  protected function applyValues(?EntityInterface $entity): mixed {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    if (!isset($this->configuration[$type_id][$bundle])) {
      return $this->t('No values changed');
    }

    // Load the edit revision in the row's language, as Views Bulk Edit does.
    $entity = $this->entityRepository->getActive($type_id, $entity->id(), $this->getLanguageContexts($entity->language()->getId()));

    $settings = $this->configuration[$type_id][$bundle];
    $values = $settings['values'];
    foreach ($values as $field => $value) {
      switch ($settings['change_method'][$field] ?? 'replace') {
        case 'new':
          $value = array_unique(array_merge($entity->{$field}->getValue(), $value), SORT_REGULAR);
          break;

        case 'append':
          $current = $entity->{$field}->getValue();
          if ($current) {
            $value[0]['value'] = $current[0]['value'] . $value[0]['value'];
          }
          break;
      }
      $entity->{$field}->setValue($value);
    }

    // Only the changed fields: unrelated problems, such as a blocked owner,
    // aren't the editor's to fix here.
    foreach (array_keys($values) as $field) {
      $items = $entity->get($field);
      $violations = $items->validate();
      if ($violations->count() > 0) {
        $definition = $items->getFieldDefinition();
        // The validator's text for an empty required field ("This value
        // should not be null.") doesn't say which field or how to fix it.
        $message = $definition->isRequired() && $items->isEmpty()
          ? $this->t("Couldn't change @item: @field is required.", [
            '@item' => $entity->label(),
            '@field' => $definition->getLabel(),
          ])
          : $this->t("Couldn't change @field on @item: @message", [
            '@field' => $definition->getLabel(),
            '@item' => $entity->label(),
            '@message' => strip_tags((string) $violations[0]->getMessage()),
          ]);
        return ['message' => (string) $message, 'type' => 'error'];
      }
    }

    // A new revision every time; see getBundleForm().
    $entity->setNewRevision();
    $entity->setRevisionCreationTime($this->time->getCurrentTime());
    $entity->setRevisionUserId($this->currentUser->id());
    $log = $settings['revision_information']['revision_log'] ?? '';
    $entity->setRevisionLogMessage($log !== '' ? $log : (string) $this->formatPlural(count($values), 'Edited as a part of bulk operation. Field changed: @fields', 'Edited as a part of bulk operation. Fields changed: @fields', [
      '@fields' => implode(', ', array_keys($values)),
    ]));

    $entity->save();
    return $this->t('Modify field values');
  }

  /**
   * {@inheritdoc}
   */
  protected function getBundleForm($entity_type_id, $bundle, $bundle_label, array $form, FormStateInterface $form_state, $bundle_count) {
    $form = parent::getBundleForm($entity_type_id, $bundle, $bundle_label, $form, $form_state, $bundle_count);
    $section = &$form[$entity_type_id][$bundle];

    $section['mukurtu_translation_help'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Translated fields change only in the language of each selected row. Fields shared by all translations change for every translation.'),
      '#weight' => -60,
    ];

    // Each field gets its own "Change method" choice right below it. With
    // several fields open, identical titles leave no way to tell them apart.
    foreach (Element::children($section) as $key) {
      if (str_ends_with($key, '_change_method') && ($section[$key]['#type'] ?? '') === 'radios') {
        $field = substr($key, 0, -strlen('_change_method'));
        $label = $section['_field_selector'][$field]['#title'] ?? $field;
        $section[$key]['#title'] = $this->t('Change method for @field', ['@field' => $label]);
      }
    }

    // Always save a new revision; only the log message is up to the editor.
    if (isset($section['revision_information']['revision'])) {
      $section['revision_information']['revision']['#access'] = FALSE;
      $section['revision_information']['revision']['#value'] = TRUE;
      unset($section['revision_information']['revision_log']['#states']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    foreach ($this->configuration as $entity_type_id => $bundles) {
      if (!is_array($bundles)) {
        continue;
      }
      foreach (array_keys($bundles) as $bundle) {
        if (is_array($this->configuration[$entity_type_id][$bundle])) {
          $this->configuration[$entity_type_id][$bundle]['revision_information']['revision'] = TRUE;
        }
      }
    }
  }

  /**
   * Removes configured references from the entity to itself.
   */
  protected function dropSelfReferences(?EntityInterface $entity): void {
    if (!$entity || !isset($this->configuration[$entity->getEntityTypeId()][$entity->bundle()]['values'])) {
      return;
    }
    $values = &$this->configuration[$entity->getEntityTypeId()][$entity->bundle()]['values'];
    $definitions = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    foreach ($values as $field => $items) {
      $definition = $definitions[$field] ?? NULL;
      if (!$definition || $definition->getSetting('target_type') !== $entity->getEntityTypeId() || !is_array($items)) {
        continue;
      }
      $values[$field] = array_values(array_filter(
        $items,
        fn($item) => (string) ($item['target_id'] ?? '') !== (string) $entity->id(),
      ));
    }
  }

  /**
   * Replaces unsaved referenced entities in the configured values with IDs.
   */
  protected function resolveNewEntities(): void {
    foreach ($this->configuration as $entity_type_id => $bundles) {
      if (!is_array($bundles)) {
        continue;
      }
      foreach ($bundles as $bundle => $settings) {
        foreach ($settings['values'] ?? [] as $field => $items) {
          foreach ($items as $delta => $item) {
            $referenced = $item['entity'] ?? NULL;
            if ($referenced instanceof EntityInterface && $referenced->isNew()) {
              $this->configuration[$entity_type_id][$bundle]['values'][$field][$delta] = ['target_id' => $this->resolveId($referenced)];
            }
          }
        }
      }
    }
  }

  /**
   * Returns the ID of the saved entity with this entity's UUID.
   */
  protected function resolveId(EntityInterface $entity): int|string {
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $existing = $storage->loadByProperties(['uuid' => $entity->uuid()]);
    if ($existing) {
      return reset($existing)->id();
    }
    $entity->save();
    return $entity->id();
  }

}
