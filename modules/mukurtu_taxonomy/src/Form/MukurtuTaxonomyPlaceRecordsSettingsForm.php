<?php

namespace Drupal\mukurtu_taxonomy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;

/**
 * Configuration form for place records.
 */
class MukurtuTaxonomyPlaceRecordsSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entityTypeManager, TypedConfigManagerInterface $typedConfigManager) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->entityTypeManager = $entityTypeManager;
    $this->typedConfigManager = $typedConfigManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_taxonomy_place_records_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mukurtu_taxonomy.settings',
    ];
  }

  /**
   * Get the option array for taxonomy vocabularies.
   */
  protected function getVocabularyOptions() {
    $vocabs = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $options = [];
    foreach ($vocabs as $vocab) {
      $options[$vocab->id()] = $vocab->label();
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('mukurtu_taxonomy.settings');

    $defaults = $config->get('place_records_enabled_vocabularies') ?? [];
    if (empty($defaults)) {
      $defaults = [
        'location' => 'location',
      ];
    }

    $form['place_record_vocabularies'] = [
      '#type' => 'checkboxes',
      '#options' => $this->getVocabularyOptions(),
      '#title' => $this->t('Taxonomy Vocabularies Enabled for Place Records'),
      '#default_value' => $defaults,
      '#description' => $this->t("Place records aggregate content that references the location featured in the place record. This is done by naming that place in specific taxonomy fields in digital heritage items and other content types. Select the taxonomies where you identify and name places. These will be listed in the \"other names\" field when creating a place record. In most cases this will be the location field.")
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('mukurtu_taxonomy.settings');

    $enabled_vocabs = [];
    $vocabs = $form_state->getValue('place_record_vocabularies');
    $enabled_vocabs = array_filter($vocabs, fn($element) => $element !== 0);

    // Save the configured vocabularies for place records.
    $config->set('place_records_enabled_vocabularies', $enabled_vocabs);
    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
