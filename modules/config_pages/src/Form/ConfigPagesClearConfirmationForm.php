<?php

namespace Drupal\config_pages\Form;

use Drupal\config_pages\ConfigPagesStorage;
use Drupal\config_pages\Entity\ConfigPages;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for clearing ConfigPage field values.
 */
class ConfigPagesClearConfirmationForm extends ConfirmFormBase {

  /**
   * The config page entity.
   *
   * @var \Drupal\config_pages\Entity\ConfigPages|null
   */
  protected ?ConfigPages $entity = NULL;

  /**
   * The config pages storage.
   *
   * @var \Drupal\config_pages\ConfigPagesStorage
   */
  protected ConfigPagesStorage $configPagesStorage;

  /**
   * Constructs a ConfigPagesClearConfirmationForm.
   *
   * @param \Drupal\config_pages\ConfigPagesStorage $config_pages_storage
   *   The config pages storage.
   */
  public function __construct(ConfigPagesStorage $config_pages_storage) {
    $this->configPagesStorage = $config_pages_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager')->getStorage('config_pages')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'config_pages_clear_confirmation_form';
  }

  /**
   * Returns the config page entity.
   *
   * @return \Drupal\config_pages\Entity\ConfigPages|null
   *   The config page entity or NULL if not loaded.
   */
  public function getEntity(): ?ConfigPages {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $label = $this->entity ? $this->entity->label() : '';
    return $this->t('Do you want to clear %label?', ['%label' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    if ($this->entity) {
      return $this->entity->toUrl();
    }
    return Url::fromRoute('entity.config_pages_type.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will reset all field values to their defaults. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Clear');
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
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL): array {
    if ($id !== NULL) {
      $entity = $this->configPagesStorage->load($id);
      if ($entity instanceof ConfigPages) {
        $this->entity = $entity;
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->entity) {
      return;
    }

    $fields = $this->entity->getFieldDefinitions();
    foreach ($fields as $name => $field) {
      // Process only fields added from BO.
      if ($field instanceof FieldConfigInterface) {
        $this->entity->set($name, $field->getDefaultValue($this->entity));
      }
    }
    $this->entity->save();

    $this->messenger()->addStatus($this->t('The config page %label has been cleared.', [
      '%label' => $this->entity->label(),
    ]));

    $form_state->setRedirectUrl(Url::fromRoute('config_pages.' . $this->entity->bundle()));
  }

}
