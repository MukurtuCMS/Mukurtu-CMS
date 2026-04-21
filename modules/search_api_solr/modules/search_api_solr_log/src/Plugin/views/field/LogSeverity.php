<?php

namespace Drupal\search_api_solr_log\Plugin\views\field;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Provides a field handler that renders a log event with replaced variables.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("search_api_solr_log_severity")]
class LogSeverity extends FieldPluginBase {

  /**
   * List of log level mappings.
   */
  protected $logLevels = [
    RfcLogLevel::DEBUG => 'debug',
    RfcLogLevel::INFO => 'info',
    RfcLogLevel::NOTICE => 'notice',
    RfcLogLevel::WARNING => 'warning',
    RfcLogLevel::ERROR => 'error',
    RfcLogLevel::CRITICAL => 'critical',
    RfcLogLevel::ALERT => 'alert',
    RfcLogLevel::EMERGENCY => 'emergency',
  ];

  /**
   * Renders the log level value as a string.
   */
  public function render($values): string {
    // Retrieve the field value from Search API result.
    $value = $this->getValue($values);
    return $this->logLevels[$value[0] ?? 999] ?? '';
  }

}
