<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Base form for ExportList add and edit operations.
 */
class ExportListFormBase extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\mukurtu_export\Entity\ExportList $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $entity->label(),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#weight' => -10,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->getDescription(),
      '#required' => FALSE,
      '#weight' => 0,
    ];

    $form['site_wide'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Share with all export users'),
      '#default_value' => $entity->isSiteWide(),
      '#weight' => 10,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('entity.export_list.collection'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // No extra validation needed beyond required fields.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\mukurtu_export\Entity\ExportList $entity */
    $entity = $this->entity;
    $entity->set('label', $form_state->getValue('label'));
    $entity->set('description', $form_state->getValue('description'));
    $entity->set('site_wide', (bool) $form_state->getValue('site_wide'));
    $entity->save();

    $this->messenger()->addStatus($this->t('Export list %name has been saved.', ['%name' => $entity->label()]));
    $form_state->setRedirectUrl(Url::fromRoute('entity.export_list.collection'));
  }

}
