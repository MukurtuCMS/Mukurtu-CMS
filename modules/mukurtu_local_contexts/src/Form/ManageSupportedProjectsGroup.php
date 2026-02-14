<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides a Local Contexts form for adding/removing projects to a group.
 */
class ManageSupportedProjectsGroup extends ManageSupportedProjectsBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_manage_group_projects';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ContentEntityInterface $group = NULL) {
    $form_state->set('group', $group);
    $bundle = $group->bundle();

    $form = parent::buildForm($form, $form_state);
    $form['projects']['#caption'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Select the Local Contexts projects you would like to add or remove for this group. Existing projects can be selected to update their content.'),
    ];

    $api_key = $form_state->get('api_key');
    if (!$api_key) {
      if ($bundle === 'community') {
        $form['api_key_wrapper']['#description'] .= '<br>' . $this->t('This API key is stored for this community only.');
      }
      elseif ($bundle === 'protocol') {
        $form['api_key_wrapper']['#description'] .= '<br>' . $this->t('This API key is stored for this protocol only.');
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Safety check that the group exists. Otherwise, operations would apply
    // to the site-wide projects.
    $group = $form_state->get('group');
    if (!$group) {
      return;
    }
    parent::submitForm($form, $form_state);
  }

}
