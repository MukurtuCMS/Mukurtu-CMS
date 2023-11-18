<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to manage the community level local contexts projects directory.
 */
class ManageCommunityProjectsDirectory extends FormBase {

  protected $communityId;

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'mukurtu_local_contexts_manage_community_projects_directory';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group = NULL)
  {
    // In this context, group is the id of the group (in string form).
    $community = $group;
    if (!$community) {
      return $form;
    }
    $this->communityId = $community;
    $element = 'community-projects-directory-' . $community;

    $description = $this->config('mukurtu_local_contexts.settings')->get('mukurtu_local_contexts_manage_community_' . $community . '_projects_directory_description') ?? '';
    $communityName = \Drupal::entityTypeManager()->getStorage('community')->load(intval($community))->getName();
    $form['description'] = [
      '#title' => $this->t('Description'),
      '#description' => $this->t("Enter the description for " . $communityName . "'s Local Contexts project directory page."),
      '#default_value' => $description,
      '#type' => 'textarea',
    ];

    $form[$element . '-submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $description = $form_state->getValue('description');
    $this->configFactory->getEditable('mukurtu_local_contexts.settings')->set('mukurtu_local_contexts_manage_community_' . $this->communityId . '_projects_directory_description', $description)->save();
  }
}
