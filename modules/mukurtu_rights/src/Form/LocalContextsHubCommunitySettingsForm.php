<?php

namespace Drupal\mukurtu_rights\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Exception;

/**
 * Configure Local Contexts Hub settings for this site.
 */
class LocalContextsHubCommunitySettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'mukurtu_rights.label_hub_community_projects';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_rights_hub_community_settings';
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
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $community = NULL) {
    $config = $this->config(static::SETTINGS);

    if (is_null($community)) {
      return [];
    }

    $form_state->set('community', $community->id());
    $project = $config->get($community->id());

    $form['community_project'] = [
      '#title' => 'Community Project ID',
      '#description' => $this->t('The project ID for the Community Local Contexts Hub project. The labels and notices in this project will be made available for members of the community.'),
      '#type'          => 'textfield',
      '#default_value' => $project,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $hub = \Drupal::service('mukurtu_rights.local_contexts_hub');
    $project = $hub->getProject($form_state->getValue('community_project'));

    $communityId = $form_state->get('community');

    // Set the community relationship.
    $project->setCommunity($communityId);

    if ($project) {
      try {
        // Save the project.
        $project->save();

        // Save the project ID to the community.
        $this->configFactory->getEditable(static::SETTINGS)
          ->set($communityId, $project->uuid())
          ->save();

        $this->messenger()->addMessage($this->t('Project %project is now available to use by members of the community.', ['%project' => $project->getTitle()]));
      }
      catch (Exception $e) {
        $this->messenger()->addError('Failed to fetch project @project from the Local Contexts Hub', ['@project' => $project->uuid()]);
      }
    }
  }

}
