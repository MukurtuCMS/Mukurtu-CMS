<?php

namespace Drupal\config_pages;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Config Pages entities.
 */
class ConfigPagesViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['config_pages']['table']['base'] = [
      'field' => 'id',
      'title' => $this->t('Config pages'),
      'help' => $this->t('The Config pages ID.'),
    ];

    return $data;
  }

}
