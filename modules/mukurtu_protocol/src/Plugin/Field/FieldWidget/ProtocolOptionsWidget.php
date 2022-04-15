<?php

namespace Drupal\mukurtu_protocol\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'protocol_options' widget.
 *
 * @FieldWidget(
 *   id = "protocol_options",
 *   label = @Translation("Cultural Protocol Options Select"),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
class ProtocolOptionsWidget extends OptionsWidgetBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectedOptions(FieldItemListInterface $items) {
    // We need to check against a flat list of options.
    $options = $this->getOptions($items->getEntity());

    $flat_options = [];
    foreach ($options as $community) {
      foreach ($community['#options'] as $id => $protocol) {
        $flat_options[$id] = $id;
      }
    }

    $selected_options = [];
    foreach ($items as $item) {
      $value = $item->{$this->column};
      // Keep the value if it actually is in the list of options (needs to be
      // checked against the flat list).
      if (isset($flat_options[$value])) {
        $selected_options[] = $value;
      }
    }

    return $selected_options;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $options = $this->getOptions($items->getEntity());
    $communityCount = count($options);
    $selected = $this->getSelectedOptions($items);

    // If required and there is one single option, preselect it.
    if ($this->required && count($options) == 1) {
      reset($options);
      $selected = [key($options)];
    }

    // Build protocol selection.
    $communities = [];
    $communities['description'] = [
      '#type' => 'item',
      '#description' => $this->getFilteredDescription(),
    ];
    $delta = 0;
    $communityIDs = array_keys($options);
    foreach ($options as $communityID => $communityOption) {
      //$protocolKeys = array_keys($communityOption['#options']);
      $communities[$delta] = [
        '#type' => 'details',
        '#open' => $communityCount === 1,
        '#title' => $communityOption['#title'],
      ];

      foreach ($communityOption['#options'] as $id => $protocolOption) {
        // If a protocol is in multiple communities, show them the others
        // in the description.
        $description = "";
        $otherCommunities = [];
        if (count($protocolOption['#communities']) > 1) {
          $description = implode(', ', $protocolOption['#communities']);
        }

        $communities[$delta]['protocols'][$id] = [
          '#type' => 'checkbox',
          '#title' => $protocolOption['#title'],
          '#description' => $description,
          '#return_value' => $id,
          '#default_value' => in_array($id, $selected),
        ];

        /* if (count($protocolOption['#communities']) > 1) {
          $checked = [];
          $otherCommunities = $communityIDs;
          unset($otherCommunities[array_search($communityID, $otherCommunities)]);
          foreach ($otherCommunities as $otherCommunity) {
            $checked[] = [':input[name="field_protocol_control[0][field_protocols][communities]['. $otherCommunity . '][protocols][7]"]' => ['value' => 0]];
          }
          $communities[$delta]['protocols'][$id]['#states'] = [
            'disabled' => $checked,
          ];
          dpm($communities[$delta]['protocols'][$id]['#states']);
        } */
      }
      $delta++;
    }

    // field_protocol_control[0][field_protocols][communities][0][protocols][7]
    $communities[3]['protocols'][7]['#states'] = [
      'checked' => [
        //'#edit-field-protocol-control-0-field-protocols-communities-0-protocols-7' => ['checked' => TRUE],
        ':input[name="field_protocol_control[0][field_protocols][communities][0][protocols][7]"]' => ['checked' => TRUE],
      ],
    ];
    $communities[0]['protocols'][7]['#states'] = [
      'checked' => [
        //'#edit-field-protocol-control-0-field-protocols-communities-3-protocols-7' => ['checked' => TRUE],
        ':input[name="field_protocol_control[0][field_protocols][communities][3][protocols][7]"]' => ['checked' => TRUE],
      ],
    ];

    return $element += ['communities' => $communities];
  }

  /**
   * {@inheritDoc}
   */
  protected function getOptions(FieldableEntityInterface $entity) {
    if (!isset($this->options)) {
      // Limit the settable options for the current user account.
      $provider = $this->fieldDefinition
        ->getFieldStorageDefinition()
        ->getOptionsProvider($this->column, $entity);

      $values = $provider->getSettableValues();

      /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface[] $protocols */
      $protocols = $this->entityTypeManager->getStorage('protocol')->loadMultiple($values);

      foreach ($protocols as $protocol) {
        $communities = $protocol->getCommunities();
        $communityNames = array_map(fn($e) => $e->getName(), $communities);
        foreach ($communities as $community) {
          $options[$community->id()]['#title'] = $community->getName();
          $options[$community->id()]['#id'] = $community->id();
          $options[$community->id()]['#options'][$protocol->id()]['#title'] = $protocol->getName();
          $options[$community->id()]['#options'][$protocol->id()]['#communities'] = $communityNames;
        }
      }

      $module_handler = \Drupal::moduleHandler();
      $context = [
        'fieldDefinition' => $this->fieldDefinition,
        'entity' => $entity,
      ];
      $module_handler->alter('options_list', $options, $context);

      array_walk_recursive($options, [$this, 'sanitizeLabel']);

      $this->options = $options;
    }
    return $this->options;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {
    $values = [];
    $ids = [];
    foreach ($element['communities'] as $community) {
      foreach ($community['protocols'] as $id => $protocol) {
        if (is_numeric($id) && $protocol['#value'] && !isset($ids[$protocol['#value']])) {
          $values[]['target_id'] = $protocol['#value'];

          // Track IDs for dedup.
          $ids[$protocol['#value']] = $protocol['#value'];
        }
      }
    }

    if ($element['#required'] && empty($values)) {
      $form_state->setError($element, t('@name field is required.', ['@name' => $element['#title']]));
    }

    $form_state->setValueForElement($element, $values);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel() {
    if (!$this->required && !$this->multiple) {
      return t('N/A');
    }
  }

}
