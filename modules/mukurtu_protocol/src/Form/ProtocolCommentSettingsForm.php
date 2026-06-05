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
    $form['comments_enabled'] = [
      '#type' => 'radios',
      '#title' => $this->t('Commenting'),
      '#description' => $this->t('Enable or disable commenting for items in this cultural protocol. For items with multiple cultural protocols, any \'disable\' setting will disable commenting.'),
      '#default_value' => $commentsEnabled ? 1 : 0,
      '#options' => array(
        1 => $this->t('Enabled'),
        0 => $this->t('Disabled'),
      ),
    ];

    $commentsRequireApproval = $protocol->getCommentRequireApproval();
    $form['comments_require_approval'] = [
      '#type' => 'radios',
      '#title' => $this->t('Require Approval for Comments'),
      '#description' => $this->t('If disabled, items in this cultural protocol will allow comments to be immediately published without approval. For items with multiple cultural protocols, any \'enabled\' setting will require comment approval.'),
      '#default_value' => $commentsRequireApproval ? 1 : 0,
      '#options' => array(
        1 => $this->t('Enabled'),
        0 => $this->t('Disabled'),
      ),
    ];

    $commentAccessOptions = [
      'anonymous'       => $this->t('Visitors (anonymous users)'),
      'authenticated'   => $this->t('Any site user with access'),
      'protocol_member' => $this->t('Protocol members only (any protocol role)'),
    ];

    $form['comment_view_access'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Who can view comments?'),
      '#description' => $this->t('Select which users can see comments on content using this protocol. For items with multiple cultural protocols, the most restrictive protocol wins.'),
      '#options' => $commentAccessOptions,
      '#default_value' => $protocol->getCommentViewAccess(),
    ];

    $form['comment_post_access'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Who can leave comments?'),
      '#description' => $this->t('Select which users can post comments on content using this protocol. For items with multiple cultural protocols, the most restrictive protocol wins.'),
      '#options' => $commentAccessOptions,
      '#default_value' => $protocol->getCommentPostAccess(),
      '#after_build' => [[$this, 'addPostAccessStates']],
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
    }

    $newCommentApprovalStatus = $form_state->getValue('comments_require_approval');
    if ($newCommentApprovalStatus == TRUE || $newCommentApprovalStatus == FALSE) {
      $protocol->setCommentRequireApproval($newCommentApprovalStatus);
    }

    $viewAccess = array_values(array_filter($form_state->getValue('comment_view_access')));
    $protocol->setCommentViewAccess($viewAccess);

    $postAccess = array_values(array_filter($form_state->getValue('comment_post_access')));
    // Visitors cannot post if they cannot view comments.
    if (!in_array('anonymous', $viewAccess)) {
      $postAccess = array_values(array_diff($postAccess, ['anonymous']));
    }
    $protocol->setCommentPostAccess($postAccess);

    // Save changes.
    $protocol->save();

    // Comment display is cached per node view.
    Cache::invalidateTags(['node_view']);
  }

  /**
   * After-build callback to disable the visitor post-access checkbox when
   * visitor view-access is unchecked.
   */
  public function addPostAccessStates(array $element, FormStateInterface $form_state): array {
    $element['anonymous']['#states'] = [
      'disabled' => [
        ':input[name="comment_view_access[anonymous]"]' => ['unchecked' => TRUE],
      ],
    ];
    return $element;
  }

}
