<?php

namespace Drupal\mukurtu_community_records\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Exception;

/**
 * Configuration form for commmunity records.
 */
class MukurtuCommunityRecordSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity Type Bundle Info Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfo $entityTypeBundleInfo) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_community_records_settings_form';
  }

  /**
   * Get the option array for CR bundle types.
   */
  protected function getBundleOptions() {
    $bundles = $this->entityTypeBundleInfo->getBundleInfo('node');
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
    $options = [];
    foreach ($bundles as $type => $label) {
      $options[$type] = $label['label'];
    }
    return $options;
  }

  /**
   * Enable a node bundle for community records.
   *
   * @param string $bundle
   *   The bundle.
   *
   * @return bool
   *   True if successful.
   */
  protected function enableBundle($bundle) {
    // Check for the field storage. This should already exist.
    $originalRecordFieldStorage = FieldStorageConfig::loadByName('node', 'field_mukurtu_original_record');
    if (is_null($originalRecordFieldStorage)) {
      // Fail if the field storage does not exist. We will not attempt to
      // recreate it.
      return FALSE;
    }

    // Add the original record field if necessary.
    $originalRecordFieldConfig = FieldConfig::loadByName('node', $bundle, 'field_mukurtu_original_record');
    if (is_null($originalRecordFieldConfig)) {
      $fieldConfig = FieldConfig::create([
        'entity_type' => 'node',
        'bundle' => $bundle,
        'field_name' => 'field_mukurtu_original_record',
        'label' => 'Original Record',
        'settings' => [
          'handler' => 'default:node',
          'handler_settings' => [
            'target_bundles' => [$bundle],
            'auto_create' => FALSE,
          ],
        ],
      ]);

      try {
        $fieldConfig->save();
      }
      catch (Exception $e) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('mukurtu_community_records.settings');

    // Current values.
    $allowed_bundles = $config->get('allowed_community_record_bundles');

    // Build the checkboxes.
    $form['community_record_enabled_bundles'] = [
      '#type' => 'checkboxes',
      '#options' => $this->getBundleOptions(),
      '#title' => $this->t('Content enabled for Community Records'),
      '#default_value' => $allowed_bundles,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $valid_bundles = $this->getBundleOptions();
    $selected_bundles = $form_state->getValue('community_record_enabled_bundles');
    foreach ($selected_bundles as $bundle => $value) {
      if (!isset($valid_bundles[$bundle])) {
        $form_state->setErrorByName('community_record_enabled_bundles', $this->t('You have selected a bundle that does not support community records.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('mukurtu_community_records.settings');

    $allowed_bundles = [];
    $form_bundles = $form_state->getValue('community_record_enabled_bundles');
    foreach ($form_bundles as $bundle => $value) {
      if ($value) {
        if ($this->enableBundle($value)) {
          $allowed_bundles[] = $value;
        }
      }
    }

    // Save the new config.
    $config->set('allowed_community_record_bundles', $allowed_bundles);
    $config->save();

    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mukurtu_community_records.settings',
    ];
  }

}
