<?php

namespace Drupal\mukurtu_migrate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * The Existing Content form tells the user they have existing content
 * (communities, protocols, nodes, media) on their site and cannot proceed with
 * migration until it's deleted.
 *
 * @internal
 */
class ExistingContentForm extends MukurtuMigrateFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'mukurtu_migrate_existing_content_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['message'] = [
      '#type' => 'item',
      '#description' => $this->t("You have existing content on your site and may not proceed with migration. Delete all existing content and try again."),
    ];

    $form['dashboard_url'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to Dashboard'),
      '#url' => \Drupal\Core\Url::fromRoute('mukurtu_core.dashboard'),
    ];

    // Reset the migrate step to Overview.
    $this->store->set('step', 'overview');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {

  }
}
