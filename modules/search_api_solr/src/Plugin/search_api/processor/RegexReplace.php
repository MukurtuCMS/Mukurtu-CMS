<?php

namespace Drupal\search_api_solr\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;

/**
 * Perform replacements based on regular expressions.
 *
 * @SearchApiProcessor(
 *   id = "solr_regex_replace",
 *   label = @Translation("Regular expression based replacements"),
 *   description = @Translation("Regular expression based replacements."),
 *   stages = {
 *     "preprocess_index" = -16,
 *     "preprocess_query" = -16,
 *     "postprocess_query" = 0,
 *   },
 * )
 */
class RegexReplace extends FieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'regexes' => [],
      'replacements' => [],
      'preserve_original' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['regexes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Regular Expressions'),
      '#description' => $this->t('One regular expression per line.'),
      '#default_value' => implode("\n", $this->configuration['regexes'] ?? []),
    ];

    $form['replacements'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Replacements'),
      '#description' => $this->t('One replacement (pattern) per line.'),
      '#default_value' => implode("\n", $this->configuration['replacements'] ?? []),
    ];

    $form['preserve_original'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preserve original'),
      '#description' => $this->t('Index the original string in addition to the replacement.'),
      '#default_value' => $this->configuration['preserve_original'] ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $regex_replace = $form_state->getValue('regex_replace');

    $regexes = [];
    $value = rtrim(preg_replace('/\r\n|\r|\n/', "\n", $form_state->getValue('regexes')), "\n");
    if (!empty($value)) {
      foreach (explode("\n", $value) as $line => $regex) {
        $regexes[] = trim($regex);
        if (@preg_match($regex, NULL) === FALSE) {
          $form_state->setErrorByName('regexes', $this->t('The regular expression in line %line is invalid.', ['%line' => $line + 1]));
        }
      }
    }

    $replacements = [];
    $value = rtrim(preg_replace('/\r\n|\r|\n/', "\n", $form_state->getValue('replacements')), "\n");
    if (!empty($value)) {
      foreach (explode("\n", $value) as $replacement) {
        $replacements[] = trim($replacement);
      }
    }

    if (count($regexes) !== count($replacements)) {
      $form_state->setErrorByName('regexes', $this->t('The number of regular expressions and replacements must be the same.'));
    }

    $form_state->setValue('regexes', $regexes);
    $form_state->setValue('replacements', $replacements);
  }

  /**
   * {@inheritdoc}
   */
  protected function processFieldValue(&$value, $type) {
    // Do *not* turn a value of null into an empty string!
    if (is_string($value)) {
      foreach ($this->configuration['regexes'] as $key => $regex) {
        $replacement = $this->configuration['replacements'][$key];
        if ($this->configuration['preserve_original']) {
          $replacement .= ' $0';
        }
        $value = preg_replace($regex, $replacement, $value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function processKey(&$value) {
    // Do *not* turn a value of null into an empty string!
    if (is_string($value)) {
      foreach ($this->configuration['regexes'] as $key => $regex) {
        $value = preg_replace($regex, $this->configuration['replacements'][$key], $value);
      }
    }
  }

}
