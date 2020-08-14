<?php

namespace Drupal\mukurtu_roundtrip\Controller;

/**
 * Class ImportBatchController
 * @package Drupal\mukurtu_roundtrip\Controller
 */
class ImportBatchController {

  /**
   * @return null|\Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function content() {
    $form = \Drupal::formBuilder()->getForm('Drupal\mukurtu_roundtrip\Form\ImportFromFileForm');
    return $form;
  }

}
