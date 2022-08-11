<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;

/**
 * Configure protocol comment settings.
 */
class ProtocolCommentSettingsForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_protocol_comment_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol */
    $protocol = $form_state->get('protocol');
    $commentsEnabled = $protocol->getCommentStatus();
    $defaultValue = $commentsEnabled ? 1 : 0;
    $form['comments_enabled'] = [
      '#type' => 'radios',
      '#title' => $this->t('Commenting Status'),
      '#default_value' => $defaultValue ?? 1,
      '#options' => array(
        1 => $this->t('Enabled'),
        0 => $this->t('Disabled'),
      ),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface $protocol */
    $protocol = $form_state->get('protocol');
    $newCommentStatus = $form_state->getValue('comments_enabled');
    if ($newCommentStatus == TRUE || $newCommentStatus == FALSE) {
      $protocol->setCommentStatus($newCommentStatus);
      $protocol->save();

      // Comment display is cached per node view.
      Cache::invalidateTags(['node_view']);
    }
  }

}
