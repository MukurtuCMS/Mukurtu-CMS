<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Form;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Response;

class ImportFieldDescriptionListForm extends ImportBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mukurtu_import_format_by_bundle';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $bundle = NULL): array {
    // 'other_taxonomies' is a virtual bundle representing all non-category
    // taxonomy vocabularies; resolve it to a real bundle for field lookups.
    $effective_bundle = ($entity_type === 'taxonomy_term' && $bundle === 'other_taxonomies') ? 'keywords' : $bundle;

    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $effective_bundle);

    // Per-entity-type overrides for the Field Description column.
    // Drupal base field descriptions are often missing or too terse for
    // end users; these replace them on import format pages only.
    $field_description_overrides = [];
    if ($entity_type === 'taxonomy_term') {
      $field_description_overrides = [
        'description' => $this->t('The description is not normally shown to end users. It may be used for internal documentation to clarify or define the content that should reference this term, or to provide additional details about the term.'),
        'langcode'    => $this->t('The encoded language. Used for translation and localization.'),
        'name'        => $this->t('The taxonomy term name.'),
        'tid'         => $this->t('Identifier number assigned by the system.'),
        'parent'      => $this->t('Not currently supported in Mukurtu. Used if a site supports hierarchical taxonomies.'),
        'uuid'        => $this->t('Unique identifier usually assigned by the system.'),
        'weight'      => $this->t('Not usually used as most sites will default to alphabetical ordering. The weight of this term in relation to other terms in the taxonomy.'),
      ];
    }
    elseif ($entity_type === 'user') {
      $field_description_overrides = [
        'roles' => $this->t('The roles this user should have. Every user automatically receives the "Authenticated user" role in addition to any roles listed here -- it does not need to be included.'),
      ];
    }

    $import_field_options = $this->buildTargetOptions($entity_type, $effective_bundle);
    unset($import_field_options[-1]);

    // Communities/protocols are virtual destination properties for the user
    // entity type (see ImportFormTrait::buildTargetOptions()), not real
    // fields, so they have no FieldDefinitionInterface to look up or hand to
    // the field process plugin manager. Describe their format directly.
    $virtual_target_descriptions = [
      'communities' => [
        'description' => $this->t('The communities this user is a member of, and their role(s) within each.'),
        'format' => $this->t('Format: CommunityName>role|role;AnotherCommunity>role. Separate multiple roles for the same community with |. Separate multiple communities with ; (or your configured multi-value delimiter). Roles may be entered as either their label (e.g. Community Manager) or machine name (e.g. community_manager).'),
      ],
      'protocols' => [
        'description' => $this->t('The protocols this user is a member of, and their role(s) within each.'),
        'format' => $this->t('Format: ProtocolName>role|role;AnotherProtocol>role. Separate multiple roles for the same protocol with |. Separate multiple protocols with ; (or your configured multi-value delimiter). Roles may be entered as either their label (e.g. Protocol Steward) or machine name (e.g. protocol_steward).'),
      ],
      'account_status' => [
        'description' => $this->t('Whether the account is active, blocked, or awaiting approval.'),
        'format' => $this->t('One of Active, Blocked, or Pending (case-insensitive). If left blank, new accounts default to Active; existing accounts being updated keep their current status.'),
      ],
    ];

    $required_options = [];
    $optional_options = [];
    foreach ($import_field_options as $field_target => $target_label) {
      // None of the virtual user targets (group membership, account status)
      // are ever required to import a user, so they always land in the
      // optional table.
      if ($entity_type === 'user' && isset($virtual_target_descriptions[$field_target])) {
        $optional_options[$field_target] = [
          'label' => $target_label,
          'description' => $virtual_target_descriptions[$field_target]['description'],
          'format' => $virtual_target_descriptions[$field_target]['format'],
        ];
        continue;
      }

      $field_components = explode('/', $field_target);
      $field_name = $field_components[0];
      $field_property = $field_components[1] ?? NULL;
      $process_plugin = $this->fieldProcessPluginManager->getInstance(['field_definition' => $fields[$field_name]]);
      $option = [
        'label' => $target_label,
        'description' => $field_description_overrides[$field_name] ?? ($fields[$field_name]->getDescription() ?? ''),
        'format' => $process_plugin->getFormatDescription($fields[$field_name], $field_property),
      ];
      if ($fields[$field_name]->isRequired()) {
        $required_options[$field_target] = $option;
      }
      else {
        $optional_options[$field_target] = $option;
      }
    }

    $form['entity_type_id'] = [
      '#type' => 'hidden',
      '#value' => $entity_type,
    ];
    $form['bundle'] = [
      '#type' => 'hidden',
      '#value' => $bundle,
    ];

    // Explain the identifier-column rule up front: ID/UUID are individually
    // optional (blank means "create new content"), but the importer needs
    // one of ID, UUID, or a unique field like the title mapped to identify
    // each row, so neither would otherwise show as "required" below.
    $form['identifier_note'] = [
      '#markup' => '<p>' . $this->t('At least one of the following must be mapped to uniquely identify each row: ID, UUID, or a unique field such as the title or name below. If none are mapped, every imported row will be treated as new content.') . '</p>',
    ];

    $table_header = [
      'label' => ['data' => $this->t('Field'), 'scope' => 'col'],
      'description' => ['data' => $this->t('Field Description'), 'scope' => 'col'],
      'format' => ['data' => $this->t('Import Format Description'), 'scope' => 'col'],
    ];

    if (!$required_options && !$optional_options) {
      $form['no_fields'] = [
        '#markup' => '<p>' . $this->t('No fields found') . '</p>',
      ];
    }

    // Both tables default to fully checked so the primary "Download CSV
    // Template" action produces a complete template without requiring the
    // user to manually check every row first; unchecking rows still allows
    // building a partial/custom template.
    if ($required_options) {
      $form['required_heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#attributes' => ['id' => 'mukurtu-import-required-fields-heading'],
        '#value' => $this->t('Required Fields'),
      ];
      $form['table_required'] = [
        '#type' => 'tableselect',
        '#header' => $table_header,
        '#options' => $required_options,
        '#default_value' => array_combine(array_keys($required_options), array_keys($required_options)),
        '#empty' => $this->t('No fields found'),
        '#attributes' => ['aria-labelledby' => 'mukurtu-import-required-fields-heading'],
      ];
    }

    if ($optional_options) {
      $form['optional_heading'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#attributes' => ['id' => 'mukurtu-import-optional-fields-heading'],
        '#value' => $this->t('Optional Fields'),
      ];
      $form['table_optional'] = [
        '#type' => 'tableselect',
        '#header' => $table_header,
        '#options' => $optional_options,
        '#default_value' => array_combine(array_keys($optional_options), array_keys($optional_options)),
        '#empty' => $this->t('No fields found'),
        '#attributes' => ['aria-labelledby' => 'mukurtu-import-optional-fields-heading'],
      ];
    }

    // Form actions.
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download CSV Template'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity_type_id = $form_state->getValue('entity_type_id');
    $bundle = $form_state->getValue('bundle');

    $entity_type_label = $this->entityTypeManager->getDefinition($entity_type_id)->getLabel();

    if ($entity_type_id === 'taxonomy_term' && $bundle === 'other_taxonomies') {
      $filename = 'Taxonomy term - Other Taxonomies.csv';
    }
    else {
      $bundle_info = $this->entityBundleInfo->getBundleInfo($entity_type_id);
      $bundle_label = $bundle && isset($bundle_info[$bundle]) ? $bundle_info[$bundle]['label'] : '';
      $filename = $bundle && $bundle != $entity_type_id ? "{$entity_type_label} - {$bundle_label}.csv" : "{$entity_type_label}.csv";
    }
    $selected_fields = array_filter($form_state->getValue('table_required') ?? [])
      + array_filter($form_state->getValue('table_optional') ?? []);
    $options = ($form['table_required']['#options'] ?? []) + ($form['table_optional']['#options'] ?? []);

    // Gather the selected field labels.
    $headers = [];
    foreach ($selected_fields as $field_name) {
      $headers[] = $options[$field_name]['label'];
    }

    // Convert to CSV format.
    $handle = fopen('php://memory', 'r+');
    fputcsv($handle, $headers);
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    // Trigger CSV download.
    $response = new Response($csv);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    $form_state->setResponse($response);
  }

}
