<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;

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

    // Read site-wide settings to enforce as ceiling on protocol options.
    $siteConfig = \Drupal::config('mukurtu_protocol.comment_settings');
    $siteCommentsEnabled = $siteConfig->get('site_comments_enabled') ?? TRUE;
    $anonymous = \Drupal::entityTypeManager()->getStorage('user_role')->load('anonymous');
    $siteAllowsAnonymousView = $anonymous && $anonymous->hasPermission('access comments');
    $siteAllowsAnonymousPost = $anonymous && $anonymous->hasPermission('post comments');
    $form_state->set('site_allows_anonymous_view', $siteAllowsAnonymousView);
    $form_state->set('site_allows_anonymous_post', $siteAllowsAnonymousPost);

    if (!$siteCommentsEnabled) {
      $settingsUrl = Url::fromRoute('mukurtu_protocol.comment_settings')->toString();
      $form['site_disabled_notice'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">' . $this->t('Commenting is currently disabled site-wide. Protocol comment settings will take effect once commenting is <a href=":url">re-enabled site-wide</a>.', [':url' => $settingsUrl]) . '</div>',
        '#weight' => -10,
      ];
    }

    if ($protocol->getCommentRequireApproval()) {
      $unapprovedUrl = Url::fromRoute('mukurtu_protocol.manage_protocol_unapproved_comments', ['group' => $protocol->id()]);
      $form['unapproved_link'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('<a href=":url">View comments awaiting approval</a> for this protocol.', [':url' => $unapprovedUrl->toString()]) . '</p>',
      ];
    }

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
      '#after_build' => [[$this, 'applySiteCeilingToViewAccess']],
    ];

    $form['comment_post_access'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Who can leave comments?'),
      '#description' => $this->t('Select which users can post comments on content using this protocol. For items with multiple cultural protocols, the most restrictive protocol wins.'),
      '#options' => $commentAccessOptions,
      '#default_value' => $protocol->getCommentPostAccess(),
      '#after_build' => [[$this, 'addPostAccessStates'], [$this, 'applySiteCeilingToPostAccess']],
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

    // Enforce site-wide visitor permissions as a ceiling — strip 'anonymous'
    // from protocol access lists when site-wide does not permit it.
    $anonymous = \Drupal::entityTypeManager()->getStorage('user_role')->load('anonymous');
    $siteAllowsAnonymousView = $anonymous && $anonymous->hasPermission('access comments');
    $siteAllowsAnonymousPost = $anonymous && $anonymous->hasPermission('post comments');

    $viewAccess = array_values(array_filter($form_state->getValue('comment_view_access')));
    if (!$siteAllowsAnonymousView) {
      $viewAccess = array_values(array_diff($viewAccess, ['anonymous']));
    }
    $protocol->setCommentViewAccess($viewAccess);

    $postAccess = array_values(array_filter($form_state->getValue('comment_post_access')));
    if (!$siteAllowsAnonymousPost) {
      $postAccess = array_values(array_diff($postAccess, ['anonymous']));
    }
    // Post access must be a subset of view access — you cannot post if you cannot view.
    $postAccess = array_values(array_intersect($postAccess, $viewAccess));
    $protocol->setCommentPostAccess($postAccess);

    // Save changes.
    $protocol->save();

    // Comment display is cached per node view.
    Cache::invalidateTags(['node_view']);

    $this->messenger()->addStatus($this->t('Protocol comment settings have been saved.'));
  }

  /**
   * After-build callback to disable the 'anonymous' view-access checkbox when
   * site-wide visitor viewing is not permitted.
   */
  public function applySiteCeilingToViewAccess(array $element, FormStateInterface $form_state): array {
    if (!$form_state->get('site_allows_anonymous_view')) {
      $element['anonymous']['#disabled'] = TRUE;
      $element['anonymous']['#description'] = $this->t('Visitors are not permitted to view comments site-wide.');
    }
    return $element;
  }

  /**
   * After-build callback to disable the 'anonymous' post-access checkbox when
   * site-wide visitor posting is not permitted.
   */
  public function applySiteCeilingToPostAccess(array $element, FormStateInterface $form_state): array {
    if (!$form_state->get('site_allows_anonymous_post')) {
      $element['anonymous']['#disabled'] = TRUE;
      $element['anonymous']['#description'] = $this->t('Visitors are not permitted to leave comments site-wide.');
    }
    return $element;
  }

  /**
   * After-build callback to disable the visitor post-access checkbox when
   * visitor view-access is unchecked.
   */
  public function addPostAccessStates(array $element, FormStateInterface $form_state): array {
    foreach (['anonymous', 'authenticated', 'protocol_member'] as $key) {
      $element[$key]['#states'] = [
        'disabled' => [
          ':input[name="comment_view_access[' . $key . ']"]' => ['unchecked' => TRUE],
        ],
      ];
    }
    return $element;
  }

}
