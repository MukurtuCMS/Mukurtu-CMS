<?php

namespace Drupal\config_pages\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a confirmation form for importing config page values.
 */
class ConfigPagesImportConfirmationForm extends ConfirmFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config page entity ID.
   *
   * @var string
   */
  protected $configPageId;

  /**
   * The imported entity ID.
   *
   * @var string
   */
  protected $importedEntityId;

  /**
   * Constructs a new ConfigPagesImportConfirmationForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_pages_import_confirmation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $config_page_id = NULL, $imported_entity_id = NULL) {
    $this->configPageId = $config_page_id;
    $this->importedEntityId = $imported_entity_id;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to import values into this config page?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action will overwrite the current field values with values from the selected context. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Import');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.config_pages.canonical', ['config_pages' => $this->configPageId]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entityStorage = $this->entityTypeManager->getStorage('config_pages');
    $entity = $entityStorage->load($this->configPageId);
    $imported_entity = $entityStorage->load($this->importedEntityId);

    if ($entity && $imported_entity) {
      foreach ($entity as $name => &$value) {
        if ($value->getFieldDefinition() instanceof FieldConfigInterface) {
          $entity->set($name, $imported_entity->get($name)->getValue());
        }
      }

      $entity->save();
      $this->messenger()->addStatus($this->t('Config page values have been successfully imported.'));
    }
    else {
      $this->messenger()->addError($this->t('Unable to import values. One or both entities could not be loaded.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
