<?php

namespace Drupal\tagify\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation 'tagify_select_widget' widget.
 *
 * @FieldWidget(
 *   id = "tagify_select_widget",
 *   label = @Translation("Tagify Select"),
 *   description = @Translation("A select field with tagify support."),
 *   field_types = {
 *     "entity_reference",
 *     "list_integer",
 *     "list_float",
 *     "list_string"
 *   },
 *   multiple_values = TRUE
 * )
 */
class TagifySelectWidget extends OptionsWidgetBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a TagifyEntityReferenceAutocompleteWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Set the entity type manager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  protected function setEntityTypeManager(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'match_operator' => 'CONTAINS',
      'match_limit' => 0,
      'placeholder' => '',
      'show_entity_id' => 0,
      'parent_selection' => 1,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['match_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Autocomplete matching'),
      '#default_value' => $this->getSetting('match_operator'),
      '#options' => $this->getMatchOperatorOptions(),
      '#description' => $this->t('Select the method used to collect autocomplete suggestions. Note that <em>Contains</em> can cause performance issues on sites with thousands of entities.'),
    ];
    $element['match_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of results'),
      '#default_value' => $this->getSetting('match_limit'),
      '#min' => 0,
      '#description' => $this->t('The number of suggestions that will be listed. Use <em>0</em> to remove the limit.'),
    ];
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];
    $element['show_entity_id'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include entity id'),
      '#default_value' => $this->getSetting('show_entity_id'),
      '#description' => $this->t('Include the entity ID within the tag.'),
    ];
    $element['parent_selection'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Parent selection'),
      '#default_value' => $this->getSetting('parent_selection'),
      '#description' => $this->t('Allow parent selection from hierarchical entities.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $operators = $this->getMatchOperatorOptions();
    $summary[] = $this->t('Autocomplete matching: @match_operator', ['@match_operator' => $operators[$this->getSetting('match_operator')]]);
    $size = $this->getSetting('match_limit') ?: $this->t('unlimited');
    $summary[] = $this->t('Autocomplete suggestion list size: @size', ['@size' => $size]);
    $placeholder = $this->getSetting('placeholder');
    $show_entity_id = $this->getSetting('show_entity_id');
    $summary[] = $show_entity_id ? $this->t('Include the entity ID within the tag') : $this->t('Remove the entity ID from the tag');
    $parent_selection = $this->getSetting('parent_selection');
    $summary[] = $parent_selection ? $this->t('Parent selection allowed') : $this->t('Parent selection not allowed');
    if (!empty($placeholder)) {
      $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $placeholder]);
    }
    else {
      $summary[] = $this->t('No placeholder');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $target_type = $this->getFieldSetting('target_type');

    // User field definition doesn't have fieldStorage defined.
    $cardinality = $target_type !== 'user'
      ? $items->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()
      : '';
    $tags_identifier = $items->getName();

    // Concat element position to the Tagify identifier.
    if (!empty($element['#field_parents'])) {
      foreach ($element['#field_parents'] as $parent) {
        if (is_numeric($parent)) {
          $tags_identifier .= '_' . $parent;
        }
      }
    }

    // Append the match operation to the selection settings.
    $selection_settings = ($this->getFieldSetting('handler_settings') ?? []) + [
      'match_operator' => $this->getSetting('match_operator'),
      'match_limit' => $this->getSetting('match_limit'),
      'placeholder' => $this->getSetting('placeholder'),
      'cardinality' => $this->fieldDefinition
        ->getFieldStorageDefinition()
        ->getCardinality(),
      'show_entity_id' => (bool) $this->getSetting('show_entity_id'),
      'parent_selection' => (bool) $this->getSetting('parent_selection'),
    ];

    $element += [
      '#type' => 'select_tagify',
      '#options' => $this->getOptions($items->getEntity()),
      '#default_value' => $this->getSelectedOptions($items),
      '#selection_handler' => $this->getFieldSetting('handler'),
      '#selection_settings' => $selection_settings,
      '#mode' => !$cardinality ? 'select' : '',
      '#attributes' => [
        'class' => [$tags_identifier],
      ],
      // Do not display a 'multiple' select box if there is only one option.
      '#multiple' => ($this->multiple && count($this->options) > 1),
      '#cardinality' => $items->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getCardinality(),
      '#match_operator' => $this->getSetting('match_operator'),
      '#match_limit' => $this->getSetting('match_limit'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#identifier' => $tags_identifier,
      '#show_entity_id' => $this->getSetting('show_entity_id'),
      '#parent_selection' => $this->getSetting('parent_selection'),
    ];

    $empty_value = $element['#empty_value'] ?? NULL;

    if (!$element['#multiple'] && !isset($element['#options'][$empty_value])) {
      // Add an empty option to single select elements. Key 0 should be
      // reserved option to empty values.
      $element['#options'] = ['_none' => ''] + $element['#options'];
    }

    // Adding drag and sort message.
    if ($target_type) {
      $entity_definition = $this->entityTypeManager->getDefinition($target_type);
      $message = $this->t("Drag to re-order @entity_types.", ['@entity_types' => $entity_definition->getPluralLabel()]);

      if ($cardinality) {
        $element['#description'] = !empty($element['#description'])
          ? [
            '#theme' => 'item_list',
            '#items' => [
              $element['#description'],
              $message,
            ],
          ]
          : $message;
      }
    }

    return $element;
  }

  /**
   * Returns the options for the match operator.
   *
   * @return array
   *   List of options.
   */
  protected function getMatchOperatorOptions() {
    return [
      'STARTS_WITH' => $this->t('Starts with'),
      'CONTAINS' => $this->t('Contains'),
    ];
  }

}
