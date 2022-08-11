<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;

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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('site_comments_enabled', $form_state->getValue('site_comments_enabled'))
      ->save();

    // Comment display is cached per node view.
    Cache::invalidateTags(['node_view']);

    parent::submitForm($form, $form_state);
  }

}
