<?php

declare(strict_types=1);

namespace Drupal\mukurtu_submissions\Form;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_submissions\Entity\SubmissionSettingsInterface;
use Drupal\mukurtu_submissions\SubmissionFormDisplayManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding and editing Submission settings entities.
 */
class SubmissionSettingsForm extends EntityForm {

  /**
   * Media type bundles selectable for the public submission form.
   */
  const MEDIA_TYPES = ['image', 'video', 'audio', 'document', 'remote_video', 'external_embed', 'soundcloud'];

  /**
   * Constructs a SubmissionSettingsForm object.
   */
  public function __construct(
    protected EntityTypeBundleInfoInterface $entityBundleInfo,
    protected EntityTypeManagerInterface $entityTypeManagerService,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected SubmissionFormDisplayManager $formDisplayManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('entity_field.manager'),
      $container->get('mukurtu_submissions.form_display_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    assert($this->entity instanceof SubmissionSettingsInterface);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [$this, 'exists'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable the submission form for this content type'),
      '#default_value' => $this->entity->status(),
    ];

    $form['access_level'] = [
      '#type' => 'radios',
      '#title' => $this->t('Who can submit'),
      '#options' => [
        'anonymous' => $this->t('Visitors and authenticated users.'),
        'authenticated' => $this->t('Authenticated users only.'),
      ],
      '#default_value' => $this->entity->getAccessLevel(),
      '#required' => TRUE,
    ];

    $bundle_options = $this->getNodeBundleOptions();
    $form['target_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#options' => $bundle_options,
      '#default_value' => $this->entity->get('target_bundle'),
      '#required' => TRUE,
      '#disabled' => !$this->entity->isNew(),
      '#description' => $this->t('The content type this form submits.'),
    ];

    $form['target_entity_type_id'] = [
      '#type' => 'value',
      '#value' => 'node',
    ];

    $form['access_expectations_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ask submitters how they expect this item to be shared'),
      '#description' => $this->t('Adds a free-text hint field so submitters can describe their access expectations, to help reviewers choose an appropriate protocol.'),
      '#default_value' => $this->entity->accessExpectationsEnabled(),
    ];

    $intro_text = $this->entity->getIntroText();
    $form['intro_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Introductory text'),
      '#description' => $this->t('Optional text shown above the Title field on the submission form. Use this to explain what belongs here, any restrictions, or how submissions will be reviewed.'),
      '#default_value' => $intro_text['value'] ?? '',
      '#format' => $intro_text['format'] ?? NULL,
    ];

    $thank_you_text = $this->entity->getThankYouText();
    $form['thank_you_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Thank-you message'),
      '#description' => $this->t('Optional text shown on the confirmation page after a successful submission. If left blank, a generic message is shown instead.'),
      '#default_value' => $thank_you_text['value'] ?? '',
      '#format' => $thank_you_text['format'] ?? NULL,
    ];

    // Only shown once a bundle is actually assigned - a new, unsaved entity
    // has none yet, so there's nothing to build this list from until it's
    // saved (auto-provisioning seeds every field as included at that point;
    // see SubmissionFormDisplayManager::ensureSubmissionFormDisplay()).
    if (!$this->entity->isNew()) {
      $this->buildFieldGroupsElement($form, $form_state);
      $this->buildFieldsTableElement($form, $form_state);
    }

    $form['allowed_media_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed media types'),
      '#description' => $this->t('Restrict which kinds of media a visitor may attach on the submission form. Only applies to content types with a media field configured on their Submission form display.'),
      '#options' => $this->getMediaTypeOptions(),
      '#default_value' => $this->entity->getAllowedMediaTypes() ?: self::MEDIA_TYPES,
    ];

    return $form;
  }

  /**
   * Gets selectable node bundle options.
   */
  protected function getNodeBundleOptions(): array {
    $options = [];
    foreach ($this->entityBundleInfo->getBundleInfo('node') as $bundle => $info) {
      $options[$bundle] = $info['label'];
    }
    return $options;
  }

  /**
   * Gets selectable media type options.
   */
  protected function getMediaTypeOptions(): array {
    $options = [];
    foreach ($this->entityTypeManagerService->getStorage('media_type')->loadMultiple(self::MEDIA_TYPES) as $id => $media_type) {
      $options[$id] = $media_type->label();
    }
    return $options;
  }

  /**
   * Gets the target bundle's own fields, keyed by name, that a manager may
   * choose to include on the public submission form.
   *
   * Scoped to "field_"-prefixed fields only - the content type's own custom
   * fields - excluding PublicSubmissionForm::EXCLUDED_FIELDS (which stay
   * hidden regardless of this choice). Base/administrative fields like
   * title, uid, or moderation_state are never offered here: title can't
   * meaningfully be excluded (Drupal requires it), and the rest are
   * permanently excluded already.
   */
  protected function getIncludableFieldOptions(string $entity_type_id, string $bundle): array {
    $options = [];
    foreach ($this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle) as $field_name => $definition) {
      if (!str_starts_with($field_name, 'field_') || in_array($field_name, PublicSubmissionForm::EXCLUDED_FIELDS, TRUE)) {
        continue;
      }
      $options[$field_name] = $definition->getLabel();
    }
    return $options;
  }

  /**
   * Builds the "Field groups" element: an AJAX add/remove list of
   * collapsible-section definitions (label + collapsed-by-default), backed
   * by $form_state's 'field_group_rows' storage so added/removed rows
   * survive AJAX rebuilds without needing to be re-saved first.
   */
  protected function buildFieldGroupsElement(array &$form, FormStateInterface $form_state): void {
    $rows = $this->getFieldGroupRows($form_state);

    $form['field_groups_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field groups'),
      '#description' => $this->t('Optional collapsible sections for organizing fields below. A field left unassigned renders inline, outside any group.'),
      '#prefix' => '<div id="field-groups-wrapper">',
      '#suffix' => '</div>',
      '#attached' => ['library' => ['mukurtu_submissions/field_groups_admin']],
    ];

    $form['field_groups_wrapper']['field_groups'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $parent_options = ['' => $this->t('- Top level -')];
    foreach ($rows as $row) {
      $parent_options[$row['id']] = $row['label'] !== '' ? $row['label'] : $row['id'];
    }

    foreach ($rows as $delta => $row) {
      // A group can't be its own parent - drop it from its own options list.
      $own_parent_options = $parent_options;
      unset($own_parent_options[$row['id']]);

      $form['field_groups_wrapper']['field_groups'][$delta] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['container-inline']],
        'id' => [
          '#type' => 'value',
          '#value' => $row['id'],
        ],
        'label' => [
          '#type' => 'textfield',
          '#title' => $this->t('Group label'),
          '#title_display' => 'invisible',
          '#placeholder' => $this->t('Group label'),
          '#default_value' => $row['label'],
          '#size' => 30,
        ],
        'collapsed' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Collapsed by default'),
          '#default_value' => $row['collapsed'],
        ],
        'parent' => [
          '#type' => 'select',
          '#title' => $this->t('Parent group'),
          '#title_display' => 'invisible',
          '#options' => $own_parent_options,
          '#default_value' => $row['parent'] ?? '',
          '#attributes' => ['class' => ['mukurtu-submissions-field-group-parent']],
        ],
        'remove' => [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#group_delta' => $delta,
          '#submit' => [[$this, 'removeGroupSubmit']],
          '#ajax' => [
            'callback' => [$this, 'fieldGroupsAjax'],
            'wrapper' => 'field-groups-wrapper',
          ],
          '#limit_validation_errors' => [],
        ],
      ];
    }

    $form['field_groups_wrapper']['add_group'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add group'),
      '#submit' => [[$this, 'addGroupSubmit']],
      '#ajax' => [
        'callback' => [$this, 'fieldGroupsAjax'],
        'wrapper' => 'field-groups-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];
  }

  /**
   * Builds the tabledrag-enabled fields table: one row per includable
   * field, with an include checkbox, a group-assignment select, and a
   * weight column controlling this field's position on the public form.
   * Replaces the old flat "included_fields" checkboxes list.
   */
  protected function buildFieldsTableElement(array &$form, FormStateInterface $form_state): void {
    assert($this->entity instanceof SubmissionSettingsInterface);
    $entity_type_id = $this->entity->getTargetEntityTypeId();
    $bundle = $this->entity->getTargetBundle();
    $field_options = $this->getIncludableFieldOptions($entity_type_id, $bundle);
    $display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'submission');
    $assignments = $this->entity->getFieldGroupAssignments();

    // Title can't be excluded from the form - Drupal requires it - so it's
    // deliberately left out of getIncludableFieldOptions()'s in/exclusion
    // list. It can still be given a weight and (per user request) assigned
    // to a group, so it's added here instead, and its "Include" cell below
    // is locked on rather than a real checkbox.
    $title_definition = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)['title'] ?? NULL;
    if ($title_definition) {
      $field_options = ['title' => $title_definition->getLabel()] + $field_options;
    }

    $group_options = ['' => $this->t('- None -')];
    foreach ($this->getFieldGroupRows($form_state) as $row) {
      $group_options[$row['id']] = $row['label'] !== '' ? $row['label'] : $row['id'];
    }

    $form['fields_table_description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t("Choose which of this content type's fields appear on the submission form, drag to reorder them, and optionally assign each to a group above."),
      '#attributes' => ['class' => ['description']],
    ];

    $form['fields_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Field'),
        $this->t('Include'),
        $this->t('Group'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'submission-field-weight',
        ],
      ],
      '#attributes' => ['id' => 'submission-fields-table'],
      '#tree' => TRUE,
    ];

    // #type 'table' does NOT sort its rows by #weight the way a normal
    // render/form tree does - Table::preRenderTable() walks its children in
    // plain array order - so the row order here has to be sorted by each
    // field's current weight up front. Without this, the table would
    // display (and, on every reload, keep resetting to) $field_options'
    // fixed field-definition order regardless of what was actually saved,
    // making tabledrag reordering look like it silently failed to persist.
    $weights = [];
    foreach ($field_options as $field_name => $label) {
      $weights[$field_name] = $display->getComponent($field_name)['weight'] ?? 0;
    }
    uasort($weights, fn ($a, $b) => $a <=> $b);

    foreach (array_keys($weights) as $field_name) {
      $label = $field_options[$field_name];
      $component = $display->getComponent($field_name);
      $weight = $weights[$field_name];
      $form['fields_table'][$field_name] = [
        '#attributes' => ['class' => ['draggable']],
        '#weight' => $weight,
        'label' => ['#plain_text' => $label],
        'include' => $field_name === 'title'
          ? [
            '#type' => 'checkbox',
            '#default_value' => TRUE,
            '#disabled' => TRUE,
            '#title' => $this->t('Always included'),
            '#title_display' => 'invisible',
          ]
          : [
            '#type' => 'checkbox',
            '#default_value' => (bool) $component,
          ],
        'group' => [
          '#type' => 'select',
          '#title' => $this->t('Group for @field', ['@field' => $label]),
          '#title_display' => 'invisible',
          '#options' => $group_options,
          '#default_value' => $assignments[$field_name] ?? '',
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @field', ['@field' => $label]),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#delta' => 50,
          '#attributes' => ['class' => ['submission-field-weight']],
        ],
      ];
    }
  }

  /**
   * Gets the field group rows currently being edited, seeded from the
   * entity's saved groups on first build and preserved across AJAX
   * add/remove rebuilds thereafter.
   */
  protected function getFieldGroupRows(FormStateInterface $form_state): array {
    $rows = $form_state->get('field_group_rows');
    if ($rows === NULL) {
      assert($this->entity instanceof SubmissionSettingsInterface);
      $rows = $this->entity->getFieldGroups();
      $form_state->set('field_group_rows', $rows);
    }
    return $rows;
  }

  /**
   * Generates a machine name for a newly added group, guaranteed distinct
   * from every group ID seen so far this form session (including removed
   * ones, so a removed group's ID is never reissued to a later row and
   * potentially confused with stale field_group_assignments values).
   */
  protected function nextGroupMachineName(FormStateInterface $form_state): string {
    $counter = $form_state->get('field_group_counter');
    if ($counter === NULL) {
      $counter = 0;
      assert($this->entity instanceof SubmissionSettingsInterface);
      foreach ($this->entity->getFieldGroups() as $group) {
        if (preg_match('/^group_(\d+)$/', $group['id'], $matches)) {
          $counter = max($counter, (int) $matches[1]);
        }
      }
    }
    $counter++;
    $form_state->set('field_group_counter', $counter);
    return 'group_' . $counter;
  }

  /**
   * Submit handler for "Add group": appends a new, blank row.
   */
  public function addGroupSubmit(array &$form, FormStateInterface $form_state): void {
    $rows = $this->getFieldGroupRows($form_state);
    $rows[] = [
      'id' => $this->nextGroupMachineName($form_state),
      'label' => '',
      'collapsed' => FALSE,
      'parent' => '',
    ];
    $form_state->set('field_group_rows', $rows);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for a row's "Remove" button.
   */
  public function removeGroupSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $delta = $triggering_element['#group_delta'];
    $rows = $this->getFieldGroupRows($form_state);
    unset($rows[$delta]);
    $form_state->set('field_group_rows', array_values($rows));
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback for the field groups add/remove buttons.
   */
  public function fieldGroupsAjax(array &$form, FormStateInterface $form_state): array {
    return $form['field_groups_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $allowed_media_types = array_filter($form_state->getValue('allowed_media_types') ?? []);
    $form_state->setValue('allowed_media_types', array_values($allowed_media_types));

    if ($form_state->hasValue('field_groups')) {
      $groups = [];
      foreach ($form_state->getValue('field_groups') as $row) {
        $label = trim($row['label'] ?? '');
        if ($label === '') {
          // A group added but never labeled is dropped silently rather than
          // saved as a nameless section - matches how an empty "Add another
          // item" row is normally discarded elsewhere in Drupal core.
          continue;
        }
        $groups[] = [
          'id' => $row['id'],
          'label' => $label,
          'collapsed' => (bool) $row['collapsed'],
          'parent' => $row['parent'] ?? '',
        ];
      }
      $form_state->setValue('field_groups', $this->breakGroupParentCycles($groups));
    }
  }

  /**
   * Clears a group's "parent" whenever following its parent chain would
   * loop back on itself, so a same-request cycle (e.g. two groups set as
   * each other's parent) can never make it into saved config - PublicSubmissionForm::groupFields()
   * relies on this chain terminating.
   */
  protected function breakGroupParentCycles(array $groups): array {
    $parents = array_column($groups, 'parent', 'id');
    foreach ($groups as &$group) {
      $seen = [$group['id'] => TRUE];
      $ancestor = $group['parent'];
      while ($ancestor !== '' && $ancestor !== NULL) {
        if (isset($seen[$ancestor])) {
          $group['parent'] = '';
          break;
        }
        $seen[$ancestor] = TRUE;
        $ancestor = $parents[$ancestor] ?? '';
      }
    }
    return $groups;
  }

  /**
   * Determines if the entity already exists.
   */
  public function exists($id): bool {
    return (bool) $this->entityTypeManagerService->getStorage('mukurtu_submission_settings')->getQuery()
      ->condition('id', $id)
      ->accessCheck(FALSE)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    if ($result == SAVED_NEW) {
      assert($this->entity instanceof SubmissionSettingsInterface);
      $this->formDisplayManager->ensureSubmissionFormDisplay($this->entity);
    }
    elseif ($form_state->hasValue('fields_table')) {
      $this->syncIncludedFields($form_state);
    }

    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created submission settings for %label.', $message_args)
      : $this->t('Updated submission settings for %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * Applies the fields table to the target bundle's "submission" form
   * display and this entity's field_group_assignments: a checked field
   * becomes visible at its chosen weight (reusing its widget from the
   * bundle's "default" form display if it isn't already a component here)
   * and is recorded against whichever group (if any) was selected for it;
   * an unchecked field is hidden and its group assignment cleared. Title
   * is always treated as included (see buildFieldsTableElement()).
   */
  protected function syncIncludedFields(FormStateInterface $form_state): void {
    assert($this->entity instanceof SubmissionSettingsInterface);
    $entity_type_id = $this->entity->getTargetEntityTypeId();
    $bundle = $this->entity->getTargetBundle();
    $display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'submission');
    $default_display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, 'default');
    $rows = $form_state->getValue('fields_table') ?? [];
    $assignments = [];

    // Title is never excludable - Drupal requires it - so unlike every
    // other field here, a missing/unchecked row must never remove its
    // component; only its weight/group can change.
    $field_names = array_keys($this->getIncludableFieldOptions($entity_type_id, $bundle));
    array_unshift($field_names, 'title');

    foreach ($field_names as $field_name) {
      $row = $rows[$field_name] ?? NULL;
      $always_included = $field_name === 'title';
      if ($row && ($always_included || !empty($row['include']))) {
        $component = $display->getComponent($field_name) ?: ($default_display->getComponent($field_name) ?: []);
        $component['weight'] = (int) $row['weight'];
        $component = $this->formDisplayManager->applySimpleMediaUploadWidget($field_name, $component, $entity_type_id, $bundle);
        $display->setComponent($field_name, $component);
        if (!empty($row['group'])) {
          $assignments[$field_name] = $row['group'];
        }
      }
      elseif (!$always_included) {
        $display->removeComponent($field_name);
      }
    }
    $display->save();

    $this->entity->set('field_group_assignments', $assignments)->save();
  }

}
