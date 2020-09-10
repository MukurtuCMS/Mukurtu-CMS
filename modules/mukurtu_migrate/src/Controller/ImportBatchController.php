<?php

namespace Drupal\mukurtu_migrate\Controller;

/**
 * Class ImportBatchController
 * @package Drupal\mukurtu_migrate\Controller
 */
class ImportBatchController {

  /**
   * @return null|\Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function content() {
    $form = \Drupal::formBuilder()->getForm('Drupal\mukurtu_migrate\Form\ImportFromRemoteSite');
    return $form;
  }

}
