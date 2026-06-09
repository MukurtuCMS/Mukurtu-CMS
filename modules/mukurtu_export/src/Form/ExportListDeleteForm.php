<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Url;

/**
 * Form for deleting an Export List.
 */
class ExportListDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.export_list.collection');
  }

  /**
   * {@inheritdoc}
   */
  protected function getRedirectUrl() {
    return Url::fromRoute('entity.export_list.collection');
  }

}
