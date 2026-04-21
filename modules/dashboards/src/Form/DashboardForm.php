<?php

namespace Drupal\dashboards\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Default config form.
 */
class DashboardForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /**
     * @var \Drupal\dashboards\Entity\Dashboard
     */
    $entity = $this->entity;
    $form['#tree'] = TRUE;
    $form['admin_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Administrative Label'),
      '#default_value' => $entity->label(),
      '#size' => 30,
      '#required' => TRUE,
      '#maxlength' => 64,
      '#description' => $this->t('The admin label for this dashboard.'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#required' => TRUE,
      '#disabled' => !$entity->isNew(),
      '#size' => 30,
      '#maxlength' => 64,
      '#machine_name' => [
        'exists' => ['\Drupal\dashboards\Entity\Dashboard', 'load'],
        'source' => ['admin_label'],
      ],
    ];

    $form['category'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Category'),
      '#default_value' => $entity->category,
    ];

    $form['frontend'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show always in frontend theme.'),
      '#default_value' => $entity->frontend,
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight.'),
      '#default_value' => $entity->weight,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $return = parent::save($form, $form_state);
    $form_state->setRedirect('entity.dashboard.canonical', ['dashboard' => $this->getEntity()->id()]);
    return $return;
  }

}
