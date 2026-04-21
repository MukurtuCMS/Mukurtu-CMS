<?php

namespace Drupal\config_pages\Plugin\Condition;

use Drupal\config_pages\ConfigPagesLoaderServiceInterface;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Access by ConfigPage field value' condition.
 *
 * @Condition(
 *   id = "config_pages_values_access",
 *   label = @Translation("ConfigPage field value")
 * )
 */
#[Condition(
  id: "config_pages_values_access",
  label: new TranslatableMarkup("ConfigPage field value"),
)]
class ConfigPagesValueAccess extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\config_pages\ConfigPagesInterface.
   *
   * @var \Drupal\config_pages\ConfigPagesInterface
   */
  protected $configPagesLoader;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Allowed field types.
   *
   * @var array
   */
  protected $allowedFieldTypes;

  /**
   * ConfigPagesValueAccess constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\config_pages\ConfigPagesLoaderServiceInterface $configPagesLoader
   *   The ConfigPages loader service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigPagesLoaderServiceInterface $configPagesLoader,
    EntityFieldManagerInterface $entityFieldManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configPagesLoader = $configPagesLoader;
    $this->entityFieldManager = $entityFieldManager;
    $this->allowedFieldTypes = [
      'string',
      'boolean',
      'decimal',
      'datetime',
      'integer',
      'list_integer',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config_pages.loader'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Get all available ConfigPages types and prepare options list.
    $config = $this->getConfiguration();
    $config_pages_types = ConfigPagesType::loadMultiple();

    // Build select options from allowed fields.
    $field_options = [
      '_none' => $this->t('None'),
    ];
    foreach ($config_pages_types as $cp_type) {
      $id = $cp_type->id();
      $label = $cp_type->label();
      $cp_field = $this->getConfigPageFields($id);
      if (!empty($cp_field)) {
        $field_options[$label] = $this->getConfigPageFields($id);
      }
    }

    // Add form items.
    $form['negate']['#access'] = FALSE;
    $form['config_page_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Select ConfigPage field to check'),
      '#options' => $field_options,
      '#default_value' => $config['config_page_field'] ?? '',
      '#description' => $this->t('Applied for: @types', ['@types' => implode(', ', $this->allowedFieldTypes)]),
    ];
    $operandOptions = $this->getOperandOptions();
    $form['operator'] = [
      '#type' => 'select',
      '#title' => $this->t('Operator'),
      '#options' => $operandOptions,
      '#default_value' => $config['operator'] ?? array_keys($operandOptions)[0],
    ];
    $form['condition_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#default_value' => $config['condition_value'] ?? '',
      '#size' => 21,
      '#description' => $this->t("Use 0 / 1 for boolean fields."),
    ];

    return $form;
  }

  /**
   * Returns supported operators.
   *
   * @return array
   *   Array of operators with their descriptions.
   */
  public function getOperandOptions() {
    $operator = [
      '==' => $this->t('Is equal to'),
      '<' => $this->t('Is less than'),
      '<=' => $this->t('Is less than or equal to'),
      '!=' => $this->t('Is not equal to'),
      '>=' => $this->t('Is greater than or equal to'),
      '>' => $this->t('Is greater than'),
      'isset' => $this->t('Not empty'),
    ];
    return $operator;
  }

  /**
   * Returns list of fields for config page.
   *
   * @return array
   *   Array of operators with their descriptions.
   */
  public function getConfigPageFields($type) {
    $result = [];
    if (!empty($type)) {
      // Get custom fields from config page.
      $base_fields = $this->entityFieldManager->getBaseFieldDefinitions('config_pages');
      $fields = $this->entityFieldManager->getFieldDefinitions('config_pages', $type);
      $custom_fields = array_diff_key($fields, $base_fields);

      // Build select options.
      foreach ($custom_fields as $id => $field_config) {
        $field_type = $field_config->getType();
        if (in_array($field_type, $this->allowedFieldTypes)) {
          $result[$type . '|' . $id . '|' . $field_type] = $field_config->getLabel() . ' (' . $id . ')';
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $field = $form_state->getValue('config_page_field');
    if ($field == '_none') {
      $this->configuration = [];
    }
    else {
      $this->configuration['config_page_field'] = $field;
      $this->configuration['operator'] = $form_state->getValue('operator');
      $this->configuration['condition_value'] = $form_state->getValue('condition_value');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $config_page_field = $this->configuration['config_page_field'];
    $condition_value = $this->configuration['condition_value'];
    $operator = $this->configuration['operator'];
    $operators_list = $this->getOperandOptions();

    $field = '';
    if (!empty($config_page_field)) {
      [$cp_type, $field, $data_type] = explode('|', $config_page_field);
    }
    $summary = $this->t('Allow if field @field @op @value', [
      '@field' => $field,
      '@op' => strtolower($operators_list[$operator]),
      '@value' => $condition_value,
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $config = $this->getConfiguration();

    if (isset($config['config_page_field'], $config['operator'], $config['condition_value'])) {
      $config_page_field = $config['config_page_field'];
      if (empty($config_page_field)) {
        return TRUE;
      }
      $operator = $config['operator'];
      $condition_value = $config['condition_value'];
      [$cp_type, $field, $data_type] = explode('|', $config_page_field);

      // Get field value.
      $field_value = $this->configPagesLoader->getValue($cp_type, $field, 0, 'value');
      return $this->compareValues($condition_value, $field_value, $operator);
    }

    return TRUE;
  }

  /**
   * Compare values based on operator.
   *
   * @return bool
   *   TRUE if comprising match.
   */
  protected function compareValues($value, $field_value, $operator) {

    // Compare values according to operator.
    switch ($operator) {
      case '==':
        $result = $field_value == $value;
        break;

      case '<':
        $result = $field_value < $value;
        break;

      case '<=':
        $result = $field_value <= $value;
        break;

      case '!=':
        $result = $field_value != $value;
        break;

      case '>=':
        $result = $field_value >= $value;
        break;

      case '>':
        $result = $field_value > $value;
        break;

      case 'isset':
        $result = !empty($field_value) === !empty($value);
        break;

      default:
        $result = FALSE;
    }

    return $result;
  }

}
