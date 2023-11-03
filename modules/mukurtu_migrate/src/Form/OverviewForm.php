<?php

namespace Drupal\mukurtu_migrate\Form;


use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Mukurtu migrate overview form.
 *
 * Heavily borrows from migrate_drupal_ui's OverviewForm.
 *
 * @internal
 */
class OverviewForm extends MukurtuMigrateFormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_migrate_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // If a migration has already been performed, redirect to the results page.
    if ($this->store->get('mukurtu_migrate.performed')) {
      return $this->redirect('mukurtu_migrate.results');
    }

    $form = parent::buildForm($form, $form_state);
    $form['#title'] = $this->t('Migrate from Mukurtu CMS version 3');

    $form['info_header'] = [
      '#markup' => '<p>' . $this->t('Migrate your content from your previous Mukurtu CMS version 3 site by following the following instructions.'),
    ];

    $info[] = $this->t('Make sure that <strong>access to the database</strong> for the previous site is available from this new site.');
    $info[] = $this->t('<strong>If the previous site has private files</strong>, a copy of its files directory must also be accessible on the host of this new site.');
    $info[] = $this->t('<strong>Do not add any content to the new site</strong> before upgrading. Any existing content is likely to be overwritten by the upgrade process.');
    $info[] = $this->t('Put this site into <a href=":url">maintenance mode</a>.', [
      ':url' => Url::fromRoute('system.site_maintenance_mode')
        ->toString(TRUE)
        ->getGeneratedUrl(),
    ]);

    $form['info'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Preparation steps'),
      '#list_type' => 'ol',
      '#items' => $info,
    ];

    $form['info_footer'] = [
      '#markup' => '<p>' . $this->t('The migration can take a long time. It is recommended to migrate from a local copy of your site instead of directly from your live site.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->store->set('step', 'credential');
    $form_state->setRedirect('mukurtu_migrate.credentials');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Continue');
  }

}
