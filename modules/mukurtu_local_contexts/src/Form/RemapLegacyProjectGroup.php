<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for remapping a community/protocol legacy Local Contexts
 * project.
 */
class RemapLegacyProjectGroup extends RemapLegacyProjectBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_remap_legacy_project_group';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ContentEntityInterface $group = NULL) {
    $form_state->set('group', $group);
    return parent::buildForm($form, $form_state);
  }

}
