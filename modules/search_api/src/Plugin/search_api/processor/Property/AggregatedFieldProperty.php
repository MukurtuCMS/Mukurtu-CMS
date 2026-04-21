<?php

namespace Drupal\search_api\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;
use Drupal\search_api\Processor\ConfigurablePropertyInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Defines an "aggregated field" property.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\AggregatedFields
 */
class AggregatedFieldProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'type' => 'union',
      'separator' => "\n\n",
      'fields' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state) {
    $index = $field->getIndex();
    $configuration = $field->getConfiguration() + $this->defaultConfiguration();

    $form['#attached']['library'][] = 'search_api/drupal.search_api.admin_css';
    $form['#tree'] = TRUE;

    $form['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Aggregation type'),
      '#description' => $this->t('Apart from the @union type, all types will result in just a single value.', ['@union' => $this->t('Union')]),
      '#options' => $this->getTypes(),
      '#default_value' => $configuration['type'],
      '#required' => TRUE,
    ];

    foreach ($this->getTypes('description') as $type => $description) {
      $form['type'][$type]['#description'] = $description;
    }

    $form['separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value separator'),
      '#description' => $this->t('The text to insert between multiple values when aggregating them with the "@type" aggregation type. Can contain escape sequences like "\n" for a newline or "\t" for a horizontal tab.', ['@type' => $this->t('Concatenation')]),
      '#size' => 30,
      '#maxlength' => 64,
      '#default_value' => addcslashes($configuration['separator'], "\0..\37\\"),
      '#states' => [
        'visible' => [
          'input[name="type"]' => ['value' => 'concat'],
        ],
      ],
    ];

    $form['fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Contained fields'),
      '#options' => [],
      '#attributes' => ['class' => ['search-api-checkboxes-list']],
      '#default_value' => $configuration['fields'],
      '#required' => TRUE,
    ];
    $datasource_labels = $this->getDatasourceLabelPrefixes($index);
    $properties = $this->getAvailableProperties($index);
    $field_options = [];
    foreach ($properties as $combined_id => $property) {
      [$datasource_id, $name] = Utility::splitCombinedId($combined_id);
      // Do not include the "aggregated field" property.
      if (!$datasource_id && $name == 'aggregated_field') {
        continue;
      }
      $label = $datasource_labels[$datasource_id] . $property->getLabel();
      $field_options[$combined_id] = Utility::escapeHtml($label);
      if ($property instanceof ConfigurablePropertyInterface) {
        $description = $property->getFieldDescription($field);
      }
      else {
        $description = $property->getDescription();
      }
      $form['fields'][$combined_id] = [
        '#attributes' => ['title' => $this->t('Machine name: @name', ['@name' => $name])],
        '#description' => $description,
      ];
    }
    // Set the field options in a way that sorts them first by whether they are
    // selected (to quickly see which one are included) and second by their
    // labels.
    asort($field_options, SORT_NATURAL);
    $selected = array_flip($configuration['fields']);
    $form['fields']['#options'] = array_intersect_key($field_options, $selected);
    $form['fields']['#options'] += array_diff_key($field_options, $selected);

    // Make sure we do not remove nested fields (which can be added via config
    // but won't be present in the UI).
    $missing_properties = array_diff($configuration['fields'], array_keys($properties));
    if ($missing_properties) {
      foreach ($missing_properties as $combined_id) {
        [, $property_path] = Utility::splitCombinedId($combined_id);
        if (strpos($property_path, ':')) {
          $form['fields'][$combined_id] = [
            '#type' => 'value',
            '#value' => $combined_id,
          ];
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(FieldInterface $field, array &$form, FormStateInterface $form_state) {
    $values = [
      'type' => $form_state->getValue('type'),
      'separator' => stripcslashes($form_state->getValue('separator')),
      'fields' => array_keys(array_filter($form_state->getValue('fields'))),
    ];
    // Do not store the default value for "separator" if it is not even used.
    // This avoids needlessly cluttering the config export.
    if ($values['type'] !== 'concat'
        && $values['separator'] === $this->defaultConfiguration()['separator']) {
      unset($values['separator']);
    }
    $field->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDescription(FieldInterface $field) {
    $index = $field->getIndex();
    $available_properties = $this->getAvailableProperties($index);
    $datasource_label_prefixes = $this->getDatasourceLabelPrefixes($index);
    $configuration = $field->getConfiguration();

    $fields = [];
    foreach ($configuration['fields'] as $combined_id) {
      [$datasource_id, $property_path] = Utility::splitCombinedId($combined_id);
      $label = $property_path;
      if (isset($available_properties[$combined_id])) {
        $label = $available_properties[$combined_id]->getLabel();
      }
      $fields[] = $datasource_label_prefixes[$datasource_id] . $label;
    }
    $type = $this->getTypes()[$configuration['type']];

    $arguments = ['@type' => $type, '@fields' => implode(', ', $fields)];

    return $this->t('A @type aggregation of the following fields: @fields.', $arguments);
  }

  /**
   * Retrieves information about available aggregation types.
   *
   * @param string $info
   *   (optional) One of "label" or "description", to indicate what values
   *   should be returned for the types.
   *
   * @return array
   *   An array of the identifiers of the available types mapped to, depending
   *   on $info, their labels, their data types or their descriptions.
   */
  protected function getTypes($info = 'label') {
    return match ($info) {
      'label' => [
        'union' => $this->t('Union'),
        'concat' => $this->t('Concatenation'),
        'sum' => $this->t('Sum'),
        'count' => $this->t('Count'),
        'max' => $this->t('Maximum'),
        'min' => $this->t('Minimum'),
        'first' => $this->t('First'),
        'last' => $this->t('Last'),
        'first_char' => $this->t('First letter'),
      ],
      'description' => [
        'union' => $this->t('The Union aggregation does an union operation of all the values of the field. 2 fields with 2 values each become 1 field with 4 values.'),
        'concat' => $this->t('The Concatenation aggregation concatenates the text data of all contained fields.'),
        'sum' => $this->t('The Sum aggregation adds the values of all contained fields numerically.'),
        'count' => $this->t('The Count aggregation takes the total number of contained field values as the aggregated field value.'),
        'max' => $this->t('The Maximum aggregation computes the numerically largest contained field value.'),
        'min' => $this->t('The Minimum aggregation computes the numerically smallest contained field value.'),
        'first' => $this->t('The First aggregation will simply keep the first encountered field value.'),
        'last' => $this->t('The Last aggregation will keep the last encountered field value.'),
        'first_char' => $this->t('The “First letter” aggregation uses just the first letter of the first encountered field value as the aggregated value. This can, for example, be used to build a Glossary view.'),
      ],
      default => [],
    };
  }

  /**
   * Retrieves label prefixes for an index's datasources.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return string[]
   *   An associative array mapping datasource IDs (and an empty string for
   *   datasource-independent properties) to their label prefixes.
   */
  protected function getDatasourceLabelPrefixes(IndexInterface $index) {
    $prefixes = [
      NULL => $this->t('General') . ' » ',
    ];

    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      $prefixes[$datasource_id] = $datasource->label() . ' » ';
    }

    return $prefixes;
  }

  /**
   * Retrieve all properties available on the index.
   *
   * The properties will be keyed by combined ID, which is a combination of the
   * datasource ID and the property path. This is used internally in this class
   * to easily identify any property on the index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   All the properties available on the index, keyed by combined ID.
   *
   * @see \Drupal\search_api\Utility::createCombinedId()
   */
  protected function getAvailableProperties(IndexInterface $index) {
    $properties = [];

    $datasource_ids = $index->getDatasourceIds();
    $datasource_ids[] = NULL;
    foreach ($datasource_ids as $datasource_id) {
      foreach ($index->getPropertyDefinitions($datasource_id) as $property_path => $property) {
        $properties[Utility::createCombinedId($datasource_id, $property_path)] = $property;
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isList(): bool {
    return ($this->configuration['type'] ?? 'union') === 'union';
  }

}
