<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;

/**
 * Provides a Local Contexts form for adding/removing projects to the site.
 */
class ManageSupportedProjectsSite extends ManageSupportedProjectsBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_manage_site_projects';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['projects']['#caption'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Select the site-wide Local Contexts projects you would like to add or remove. Existing projects can be selected to update their content.'),
    ];
    return $form;
  }

}
