<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_core\Plugin\Field\FieldType\EntityReferenceRoleItem;

/**
 * Lets managers choose which person fields record a role for each name.
 */
class PersonRoleSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_core_person_role_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [EntityReferenceRoleItem::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $enabled = $this->config(EntityReferenceRoleItem::SETTINGS)->get('enabled_fields') ?? [];

    $form['enabled_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Record roles for'),
      '#options' => [
        'field_creator' => $this->t('Creator'),
        'field_contributor' => $this->t('Contributor'),
        'field_people' => $this->t('People'),
      ],
      '#default_value' => $enabled,
      '#description' => $this->t('Selected fields show a role box beside each name on edit forms, and show the role after the name on content pages, for example "Eunice Kitto (Singer)". Turning a field off hides its roles but keeps them, so turning it back on restores them.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(EntityReferenceRoleItem::SETTINGS)
      ->set('enabled_fields', array_values(array_filter($form_state->getValue('enabled_fields'))))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
