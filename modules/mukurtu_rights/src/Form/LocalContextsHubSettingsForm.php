<?php

namespace Drupal\mukurtu_rights\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;

/**
 * Configure Local Contexts Hub settings for this site.
 */
class LocalContextsHubSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_rights.label_hub.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_rights_hub_settings';
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

    $endpoint = $config->get('hub_endpoint') ?? 'https://anth-ja77-lc-dev-42d5.uc.r.appspot.com/api/v1/';
    $project = $config->get('site_wide_project');

    $form['hub_endpoint'] = [
      '#title' => 'Local Contexts Hub API URL',
      '#description' => $this->t('The URL for the Local Contexts Hub.'),
      '#type'          => 'url',
      '#default_value' => $endpoint,
    ];

    $form['site_wide_project'] = [
      '#title' => 'Site Wide Local Contexts Hub Project ID',
      '#description' => $this->t('The project ID for the Local Contexts Hub project. This project will be used as the source for site wide labels and notices.'),
      '#type'          => 'textfield',
      '#default_value' => $project,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('hub_endpoint', $form_state->getValue('hub_endpoint'))
      ->set('site_wide_project', $form_state->getValue('site_wide_project'))
      ->save();

    $hub = \Drupal::service('mukurtu_rights.local_contexts_hub');
    $project = $hub->getProject($form_state->getValue('site_wide_project'));

    if ($project) {
      try {
        $project->save();
        $this->messenger()->addMessage($this->t('%project is the new site wide Local Contexts Hub project.', ['%project' => $project->getTitle()]));
      }
      catch (Exception $e) {
        $this->messenger()->addError('Failed to fetch project @project from the Local Contexts Hub', ['@project' => $form_state->getValue('site_wide_project')]);
      }
    }

    parent::submitForm($form, $form_state);
  }

}
