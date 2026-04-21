<?php

namespace Drupal\search_api_solr_log\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\search_api_solr_log\Logger\SolrLogger;

/**
 * Provides a confirmation form before clearing out the logs.
 *
 * @internal
 */
class ClearLogConfirmForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'search_api_solr_log_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the recent logs?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('view.search_api_solr_log.overview');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      SolrLogger::delete();
      $this->messenger()->addStatus($this->t('Solr log cleared.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
    } catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while clearing the log: %message', ['%message' => $e->getMessage()]));
    }
  }

}
