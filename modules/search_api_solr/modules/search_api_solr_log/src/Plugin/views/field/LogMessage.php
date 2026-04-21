<?php

namespace Drupal\search_api_solr_log\Plugin\views\field;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;

/**
 * Provides a field handler that renders a log event with replaced variables.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("search_api_solr_log_message")]
class LogMessage extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    if ($this->options['replace_variables']) {
      $this->additional_fields['variables'] = 'variables';
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['replace_variables'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['replace_variables'] = [
      '#title' => $this->t('Replace variables'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['replace_variables'] ?? TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string {
    $value = $this->getValue($values)[0] ?? '';

    if ($this->options['replace_variables']) {
      $variables_json = $values->{'solr_document/zs_variables'}[0] ?? '{}';
      $variables = json_decode($variables_json, TRUE);

      if ($variables === NULL) {
        return Xss::filterAdmin($value);
      }

      // Ensure backtrace strings are properly formatted.
      if (isset($variables['@backtrace_string'])) {
        $variables['@backtrace_string'] = new FormattableMarkup(
          '<pre class="backtrace">@backtrace_string</pre>', $variables
        );
        if (!str_contains($value, '@backtrace_string')) {
          $value .= ' @backtrace_string';
        }
      }

      return $this->t(Xss::filterAdmin($value), (array) $variables)->render();
    }

    return $this->sanitizeValue((string) $value);
  }

}
