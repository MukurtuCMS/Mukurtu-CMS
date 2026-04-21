<?php

namespace Drupal\search_api_test_no_ui\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Provides a test processor that should be hidden from the UI.
 */
#[SearchApiProcessor(
  id: 'search_api_test_no_ui',
  label: new TranslatableMarkup('No UI processor'),
  hidden: TRUE,
)]
class NoUi extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function supportsStage($stage_identifier) {
    return TRUE;
  }

}
