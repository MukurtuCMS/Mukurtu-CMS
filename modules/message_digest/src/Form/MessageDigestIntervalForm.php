<?php

namespace Drupal\message_digest\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Config form for message digest intervals.
 */
class MessageDigestIntervalForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\message_digest\Entity\MessageDigestIntervalInterface $interval */
    $interval = $this->entity;

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $interval->label(),
      '#description' => $this->t('Label for this interval.'),
      '#required' => TRUE,
      '#size' => 30,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Machine name'),
      '#default_value' => $interval->id(),
      '#disabled' => !$interval->isNew(),
      '#machine_name' => [
        'exists' => ['\Drupal\message_digest\Entity\MessageDigestInterval', 'load'],
        'source' => ['label'],
      ],
    ];
    $form['interval'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Interval'),
      '#required' => TRUE,
      '#default_value' => $interval->getInterval(),
      '#description' => $this->t('A <a href=":url">relative format</a>, such as <em>1 week</em> or <em>1 day</em>.', [
        ':url' => 'https://www.php.net/manual/en/datetime.formats.php#datetime.formats.relative',
      ]),
    ];
    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#default_value' => $interval->getDescription(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Verify interval is valid.
    try {
      // This will throw an exception if the submitted interval is invalid.
      new \DateTime($form_state->getValue('interval'));
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('interval', $this->t('The interval %interval is invalid. See <a href=":url">the relative time formats documentation</a> for more information.', [
        ':url' => 'http://php.net/manual/en/datetime.formats.relative.php',
        '%interval' => $form_state->getValue('interval'),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $placeholders = [
      '%label' => $this->entity->label(),
      '@action' => $this->entity->isNew() ? 'added' : 'updated',
    ];
    parent::save($form, $form_state);

    $this->messenger()->addMessage($this->t('Interval %label has been @action.', $placeholders));
    $this->logger('message_digest')->notice('Message digest interval %label has been @action', $placeholders);

    // Redirect back to the collection page.
    $form_state->setRedirect('entity.message_digest_interval.collection');
  }

}
