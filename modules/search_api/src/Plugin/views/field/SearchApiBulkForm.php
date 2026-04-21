<?php

namespace Drupal\search_api\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\search_api\SearchApiException;
use Drupal\system\ActionConfigEntityInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\BulkForm;
use Drupal\views\Plugin\views\style\Table;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Defines an actions-based bulk operation form element.
 */
#[ViewsField('search_api_bulk_form')]
class SearchApiBulkForm extends BulkForm {

  use SearchApiFieldTrait {
    preRender as traitPreRender;
    defineOptions as ignoreDefineOptions;
    buildOptionsForm as ignoreBuildOptionsForm;
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    $entity_type_ids = array_values($this->getIndex()->getEntityTypes());
    if (!$entity_type_ids) {
      $this->actions = [];
      return;
    }

    // Filter the actions to only include those that are supported by at least
    // one entity type contained in the index.
    $filter = function (ActionConfigEntityInterface $action) use ($entity_type_ids) {
      return in_array($action->getType(), $entity_type_ids, TRUE);
    };
    $actions = $this->actionStorage->loadMultiple();
    $this->actions = array_filter($actions, $filter);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    // The standard BulkForm only works with a single entity type, but results
    // returned by Search API might contain entities of many different entity
    // types, and even datasources that are not based on entities.
    // Override the parent method as BulkForm::init() will call this and will
    // complain that a valid entity type cannot be retrieved.
    // @see \Drupal\views\Plugin\views\field\BulkForm::init()
    // @see \Drupal\views\Plugin\views\HandlerBase::getEntityType()
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // We cannot use the parent's method as we don't know the entity type on a
    // per-view basis. Using this cache context covers us on multilingual sites.
    if ($this->languageManager->isMultilingual()) {
      return ['languages:' . LanguageInterface::TYPE_CONTENT];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(ResultRow $values) {
    /** @var \Drupal\search_api\Plugin\views\ResultRow $values */
    try {
      $value = $values->_item->getOriginalObject()->getValue();
    }
    catch (SearchApiException) {
      return NULL;
    }
    return $value instanceof EntityInterface ? $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    $this->traitPreRender($values);

    // If the view is using a table style, provide a placeholder for a "select
    // all" checkbox.
    if (($this->view->style_plugin ?? NULL) instanceof Table) {
      // Add the tableselect css classes.
      $this->options['element_label_class'] .= 'select-all';
      // Hide the actual label of the field on the table header.
      $this->options['label'] = '';
    }
  }

  // phpcs:disable Drupal.Commenting.FunctionComment.TypeHintMissing

  /**
   * Form constructor for the bulk form.
   *
   * Search API supports also non-entity datasources but, as actions
   * require an entity, we don't show the checkbox for such rows. Unfortunately
   * it's hard to extend this method, so we are forking the parent's method.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(&$form, FormStateInterface $form_state) {
    // phpcs:enable
    // Make sure we do not accidentally cache this form.
    // @todo Evaluate this again in https://www.drupal.org/node/2503009.
    $form['#cache']['max-age'] = 0;

    // Add the tableselect javascript.
    $form['#attached']['library'][] = 'core/drupal.tableselect';
    $use_revision = array_key_exists('revision', $this->view->getQuery()->getEntityTableInfo());

    // Only add the bulk form options and buttons if there are results.
    if (!empty($this->view->result)) {
      // Render checkboxes for all rows.
      $form[$this->options['id']]['#tree'] = TRUE;
      foreach ($this->view->result as $row_index => $row) {
        // Search API supports also non-entity datasources but, as actions
        // require an entity, we don't show the checkbox for such rows.
        if (!$this->getEntity($row)) {
          continue;
        }
        $entity = $this->getEntityTranslationByRelationship($this->getEntity($row), $row);

        $form[$this->options['id']][$row_index] = [
          '#type' => 'checkbox',
          // We are not able to determine a main "title" for each row, so we can
          // only output a generic label.
          '#title' => $this->t('Update this item'),
          '#title_display' => 'invisible',
          '#default_value' => !empty($form_state->getValue($this->options['id'])[$row_index]) ? 1 : NULL,
          '#return_value' => $this->calculateEntityBulkFormKey($entity, $use_revision),
        ];
      }

      // Replace the form submit button label.
      $form['actions']['submit']['#value'] = $this->t('Apply to selected items');

      // Ensure a consistent container for filters/operations in the view header.
      $form['header'] = [
        '#type' => 'container',
        '#weight' => -100,
      ];

      // Build the bulk operations action widget for the header.
      // Allow themes to apply .container-inline on this separate container.
      $form['header'][$this->options['id']] = [
        '#type' => 'container',
      ];
      $form['header'][$this->options['id']]['action'] = [
        '#type' => 'select',
        '#title' => $this->options['action_title'],
        '#options' => $this->getBulkOptions(),
      ];

      // Duplicate the form actions into the action container in the header.
      $form['header'][$this->options['id']]['actions'] = $form['actions'];
    }
    else {
      // Remove the default actions build array.
      unset($form['actions']);
    }
  }

  // phpcs:enable Drupal.Commenting.FunctionComment.TypeHintMissing

  /**
   * {@inheritdoc}
   */
  public function viewsFormValidate(&$form, FormStateInterface $form_state) {
    // As the view might contain rows from diverse entity types and an action
    // is designed to act only on a specific entity type, we remove the
    // incompatible selected rows from the selection and popup a warning.
    // @todo Use Javascript to already reflect this in the UI.
    $user_input = $form_state->getUserInput();
    $input_key = $this->options['id'];
    $selected = $form_state->getValue($input_key);
    $action = $this->actions[$form_state->getValue('action')];

    $removed_entities = [];
    foreach ($selected as $delta => $bulk_form_key) {
      if ($bulk_form_key) {
        try {
          $entity = $this->loadEntityFromBulkFormKey($bulk_form_key);
        }
        catch (InvalidPluginDefinitionException | PluginNotFoundException) {
          $entity = NULL;
        }
        if (!$entity || $entity->getEntityTypeId() !== $action->getType()) {
          $removed_entities[] = $entity->label();
          unset($selected[$delta]);
        }
      }
    }

    if ($removed_entities) {
      $form_state->setValue($input_key, $selected);
      $user_input[$input_key] = $selected;
      $form_state->setUserInput($user_input);
      $this->messenger()->addWarning($this->formatPlural(
        count($removed_entities),
        "Row %items removed from selection as it's not compatible with %action action.",
        'Rows %items removed from selection as they are not compatible with %action action.',
        [
          '%action' => $action->label(),
          '%items' => implode(', ', $removed_entities),
        ]
      ));
    }

    parent::viewsFormValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function calculateEntityBulkFormKey(EntityInterface $entity, $use_revision) {
    $parent_value = parent::calculateEntityBulkFormKey($entity, $use_revision);
    $bulk_form_key = json_decode(base64_decode($parent_value));
    // Rows of Search API views, based on entity datasources, might have
    // different entity types. We add the entity type ID to the bulk form key.
    array_unshift($bulk_form_key, $entity->getEntityTypeId());
    $key = json_encode($bulk_form_key);
    return base64_encode($key);
  }

  /**
   * Loads an entity based on a bulk form key.
   *
   * This is a slightly changed copy of the parent's method, except that the
   * entity type ID is not view based but is extracted from the bulk form key.
   *
   * @param string $bulk_form_key
   *   The bulk form key representing the entity's id, language and revision (if
   *   applicable) as one string.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity loaded in the state (language, optionally revision) specified
   *   as part of the bulk form key.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  protected function loadEntityFromBulkFormKey($bulk_form_key) {
    $key = base64_decode($bulk_form_key);
    $key_parts = json_decode($key);
    $revision_id = NULL;

    // If there are 4 items, the revision ID  will be last.
    if (count($key_parts) === 4) {
      $revision_id = array_pop($key_parts);
    }

    // The first three items will always be the entity type, langcode and ID.
    [$entity_type_id, $langcode, $id] = $key_parts;

    // Load the entity or a specific revision depending on the given key.
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if ($revision_id && $storage instanceof RevisionableStorageInterface) {
      $entity = $storage->loadRevision($revision_id);
    }
    else {
      $entity = $storage->load($id);
    }

    if ($entity instanceof TranslatableInterface) {
      $entity = $entity->getTranslation($langcode);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function isWorkspaceSafeForm(array $form, FormStateInterface $form_state): bool {
    // Only return TRUE if all the index's datasources return workspace-safe
    // entity types.
    foreach ($this->getIndex()->getDatasources() as $datasource) {
      $entity_type_id = $datasource->getEntityTypeId();
      if ($entity_type_id === NULL) {
        return FALSE;
      }
      try {
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      }
      catch (PluginNotFoundException) {
        return FALSE;
      }
      if (!$this->isWorkspaceSafeEntityType($entity_type)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
