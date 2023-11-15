<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Url;

class CsvExporterDeleteForm extends EntityDeleteForm {
  /**
   * {@inheritDoc}
   */
  protected function getRedirectUrl() {
    return Url::fromRoute('mukurtu_export.export_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('mukurtu_export.export_settings');
  }

}
