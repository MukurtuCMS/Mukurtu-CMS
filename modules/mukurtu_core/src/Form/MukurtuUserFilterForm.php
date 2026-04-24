<?php

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MukurtuUserFilterForm extends FormBase {

  public function getFormId() {
    return 'mukurtu_user_filter';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->getRequest();

    $form['#method'] = 'get';
    $form['#token'] = FALSE;
    $form['#action'] = Url::fromRoute('mukurtu_core.people')->toString();

    $form['row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
    ];

    $form['row']['user'] = [
      '#type' => 'search',
      '#title' => $this->t('Name or email contains'),
      '#size' => 30,
      '#default_value' => $request->query->get('user', ''),
    ];

    $form['row']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#name' => '',
    ];

    if ($request->query->get('user', '') !== '') {
      $form['row']['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('Reset'),
        '#url' => Url::fromRoute('mukurtu_core.people'),
        '#attributes' => ['class' => ['button', 'button--small']],
      ];
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
