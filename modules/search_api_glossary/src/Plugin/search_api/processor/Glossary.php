<?php

namespace Drupal\search_api_glossary\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\Component\Utility\Html;

/**
 * Adds the item's AZ to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "glossary",
 *   label = @Translation("Glossary processor"),
 *   description = @Translation("Exposes glossary computed fields to Search API."),
 *   stages = {
 *     "add_properties" = 99,
 *     "pre_index_save" = 0,
 *     "preprocess_index" = -20,
 *   },
 *   locked = false,
 *   hidden = false,
 * )
 */
class Glossary extends ProcessorPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  protected $targetFieldPrefix = 'glossaryaz_';

  /**
   * The data type helper.
   *
   * @var \Drupal\search_api\Utility\DataTypeHelperInterface|null
   */
  protected $dataTypeHelper;

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      // Get glossary fields.
      $glossary_fields = $this->getConfig();

      // Get original fields from index.
      $fields = $this->index->getFields();

      // Loop through the saved config from
      // Search API field settings form.
      foreach ($glossary_fields as $name => $glossary_field) {
        // If glossary is enabled on this field.
        if ((isset($glossary_field['glossary']) && $glossary_field['glossary'] == 1) &&
            isset($fields[$name])) {

          $definition = [
            'label' => 'Glossary AZ - ' . Html::escape($fields[$name]->getPrefixedLabel()),
            'description' => 'Glossary AZ - ' . Html::escape($fields[$name]->getPrefixedLabel()),
            // ElasticSearch facets will need this field to be string.
            'type' => 'string',
            'processor_id' => $this->getPluginId(),
            // This will be a hidden field,
            // not something a user can add/remove manually.
            'hidden' => TRUE,
          ];
          $new_field_name = $this->makeFieldName($name);
          $properties[$new_field_name] = new ProcessorProperty($definition);
        }
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    // TODO Dependency Inject.
    $glossary_helper = \Drupal::service('search_api_glossary.helper');

    $item_fields = $item->getFields();

    // Get glossary fields.
    $glossary_fields_conf = $this->getConfig();

    // Loop through all fields.
    foreach ($item_fields as $name => $field) {
      // Filter out hidden fields
      // and fields that do not match
      // our required criteria.
      // Finally check if this field has glossary enabled.
      if ($field->isHidden() == FALSE && $this->testType($field->getType()) && $this->checkFieldName($name) == FALSE && isset($glossary_fields_conf[$name])) {
        $glossary_field_conf = $glossary_fields_conf[$name]['glossary'];

        // Check if source field exists
        // and if glossary is enabled on this field.
        if (isset($glossary_field_conf) && $glossary_field_conf == 1 && !empty($field->getValues())) {
          // Get the Parent field value.
          $source_field_value = $field->getValues()[0];

          // Get target field name.
          $glossary_field_name = $this->makeFieldName($name);

          // Calculated Glossary value.
          $glossary_value = $glossary_helper->glossaryGetter($source_field_value, $glossary_fields_conf[$name]['grouping']);

          // Get target field.
          $glossary_field = $item_fields[$glossary_field_name];

          // Set the Target Glossary value.
          if (empty($glossary_field->getValues())) {
            $glossary_field->addValue($glossary_value);
          }
        }
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'field_enabled' => 0,
      'grouping_defaults' => [
        'grouping_other' => 'grouping_other',
        'grouping_az' => 0,
        'grouping_09' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $fields = $this->index->getFields();

    $form['glossarytable'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Field'),
        $this->t('Glossary Grouping'),
      ],
    ];

    foreach ($fields as $name => $field) {
      // Filter out hidden fields
      // and fields that do not match
      // our required criteria.
      if ($field->isHidden() == FALSE &&
          $this->testType($field->getType()) &&
          $this->checkFieldName($name) == FALSE) {
        // Check the config if the field has been enabled?
        $glossary_fields = $this->getConfig();
        $field_enabled = isset($glossary_fields[$name]['glossary']) ? $glossary_fields[$name]['glossary'] : $this->configuration['field_enabled'];

        // Check the config if the field has been enabled?
        $field_grouping = isset($glossary_fields[$name]['grouping']) ? $glossary_fields[$name]['grouping'] : $this->configuration['grouping_defaults'];

        $form['glossarytable'][$name]['glossary'] = [
          '#type' => 'checkbox',
          '#title' => Html::escape($field->getPrefixedLabel()),
          '#default_value' => $field_enabled,
        ];

        // Finally add the glossary grouping options per field.
        $form['glossarytable'][$name]['grouping'] = [
          '#type' => 'checkboxes',
          '#description' => $this->t('When grouping is enabled, individual values such as 1, 2, 3 will get grouped like "0-9"'),
          '#options' => [
            'grouping_az' => 'Group Alphabetic (A-Z)',
            'grouping_09' => 'Group Numeric (0-9)',
            'grouping_other' => 'Group Non Alpha Numeric (#)',
          ],
          '#default_value' => $field_grouping,
          '#required' => FALSE,
          '#states' => [
            'visible' => [
                [':input[name="processors[glossary][settings][glossarytable][' . $name . '][glossary]"]' => ['checked' => TRUE]],
            ],
          ],
        ];

      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $glossarytable = $form_state->getValues('glossarytable');
    $this->setConfig($glossarytable);
  }

  /**
   * {@inheritdoc}
   */
  public function preIndexSave() {
    // Get glossary fields.
    $glossary_fields = $this->getConfig();

    // Get original fields from index.
    $fields = $this->index->getFields();

    // Loop through the saved config from
    // Search API field settings form.
    foreach ($glossary_fields as $name => $glossary_field) {
      // If glossary is enabled on this field.
      if ((isset($glossary_field['glossary']) && $glossary_field['glossary'] == 1) &&
          isset($fields[$name])) {
        // Automatically add field to index if processor is enabled.
        $new_field_name = $this->makeFieldName($name);
        // ElasticSearch facets will need this field to be string.
        $field = $this->ensureField(NULL, $new_field_name, 'string');

        // Hide the field.
        $field->setHidden();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function testType($type) {
    return $this->getDataTypeHelper()
      ->isTextType($type, ['text', 'string', 'integer']);
  }

  /**
   * Retrieves the data type helper.
   *
   * @return \Drupal\search_api\Utility\DataTypeHelperInterface
   *   The data type helper.
   */
  public function getDataTypeHelper() {
    return $this->dataTypeHelper ?: \Drupal::service('search_api.data_type_helper');
  }

  /**
   * Return the glossary name of source field.
   *
   * @param string $name
   *   Name of the original field.
   *
   * @return string
   *   Name of the glossary field.
   */
  protected function makeFieldName($name) {
    return $this->targetFieldPrefix . $name;
  }

  /**
   * Return the name of source field.
   *
   * @param string $name
   *   Name of the glossary field.
   *
   * @return string
   *   Name of the original field.
   */
  public function getFieldName($name) {
    return str_replace($this->targetFieldPrefix, '', $name);
  }

  /**
   * Check for Glossary field.
   *
   * Check if the supplied name matches the prefix
   * This is used to determine if a given field is a
   * glossary field or not.
   *
   * @param string $name
   *   Name of the field to check.
   *
   * @return bool
   *   Is the supplied field glossary or not?
   */
  public function checkFieldName($name) {
    if (substr($name, 0, strlen($this->targetFieldPrefix)) === $this->targetFieldPrefix) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Wrapper to get config.
   *
   * @return array
   *   Configuration array.
   */
  public function getConfig() {
    if (isset($this->configuration['glossarytable'])) {
      return unserialize($this->configuration['glossarytable'])['glossarytable'];
    }
  }

  /**
   * Wrapper to save config.
   *
   * @param array $configuration
   *   Configuration array.
   */
  protected function setConfig(array $configuration) {
    $this->setConfiguration(['glossarytable' => serialize($configuration)]);
  }

}
