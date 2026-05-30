<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Mukurtu comment settings for this site.
 */
class CommentSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_protocol.comment_settings';

  protected EntityTypeManagerInterface $entityTypeManager;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_site_comment_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $siteCommentsEnabled = $config->get('site_comments_enabled');

    $form['site_comments_enabled'] = [
      '#type' => 'radios',
      '#title' => $this->t('Site Wide Commenting'),
      '#default_value' => $siteCommentsEnabled ?? 1,
      '#options' => array(
        1 => $this->t('Enabled'),
        0 => $this->t('Disabled'),
      ),
    ];

    $commentsRequireApproval = $config->get('site_comments_require_approval');

    $form['site_comments_require_approval'] = [
      '#type' => 'radios',
      '#title' => $this->t('Require Approval for Comments'),
      '#description' => $this->t('If disabled, comments will be immediately published without approval. Protocol-level approval settings can override this - any protocol with approval required will require approval regardless of this setting.'),
      '#default_value' => $commentsRequireApproval ? 1 : 0,
      '#options' => [
        1 => $this->t('Enabled'),
        0 => $this->t('Disabled'),
      ],
    ];

    $anonymous = $this->entityTypeManager->getStorage('user_role')->load('anonymous');
    $anonymousCanAccessComments = $anonymous && $anonymous->hasPermission('access comments');

    $form['anonymous_can_access_comments'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow visitors to view comments'),
      '#description' => $this->t('When enabled, anonymous (not logged-in) users can read comments on content. This controls the <em>Access comments</em> permission for the Anonymous User role.'),
      '#default_value' => $anonymousCanAccessComments,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('site_comments_enabled', $form_state->getValue('site_comments_enabled'))
      ->set('site_comments_require_approval', (bool) $form_state->getValue('site_comments_require_approval'))
      ->save();

    $anonymous = $this->entityTypeManager->getStorage('user_role')->load('anonymous');
    if ($anonymous) {
      $allow = (bool) $form_state->getValue('anonymous_can_access_comments');
      if ($allow) {
        $anonymous->grantPermission('access comments');
      }
      else {
        $anonymous->revokePermission('access comments');
      }
      $anonymous->save();
    }

    // Comment display is cached per node view.
    Cache::invalidateTags(['node_view']);

    parent::submitForm($form, $form_state);
  }

}
