<?php

namespace Drupal\search_api_solr\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface;
use Drupal\search_api\Processor\FieldsProcessorPluginBase;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api_solr\SolrProcessorInterface;

/**
 * Replaces double quotes in field values and query.
 *
 * Workaround for a bug in Solr streaming expressions.
 *
 * (see https://issues.apache.org/jira/browse/SOLR-10894 and
 * https://mail-archives.apache.org/mod_mbox/lucene-solr-user/201805.mbox/%3cCAE4tqLPXMDA8y3hzXXkJUtTm6jvUX8XZ0H6P5itcFPgmr1bQZA@mail.gmail.com%3e)
 *
 * @SearchApiProcessor(
 *   id = "double_quote_workaround",
 *   label = @Translation("Double Quote Workaround"),
 *   description = @Translation("Replaces double quotes in field values and query to work around a bug in Solr streaming expressions."),
 *   stages = {
 *     "preprocess_index" = -15,
 *     "preprocess_query" = -15,
 *     "postprocess_query" = 0,
 *   },
 * )
 */
class DoubleQuoteWorkaround extends FieldsProcessorPluginBase implements SolrProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $configuration = parent::defaultConfiguration();

    $configuration += [
      // The replacement for a double quote in the input string.
      'replacement' => '|9999999998|',
    ];

    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['replacement'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Replacement'),
      '#description' => $this->t('The replacement for a double quote in the input string.'),
      '#default_value' => $this->configuration['replacement'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $replacement = trim($form_state->getValue('replacement'));
    if (mb_strlen($replacement) < 3) {
      $form_state->setErrorByName('replacement', $this->t('The replacement should at least consist of three characters.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function process(&$value) {
    // Do *not* turn a value of null into an empty string!
    if (is_string($value)) {
      $value = preg_replace('/"/u', $this->configuration['replacement'], $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results) {
    foreach ($results->getResultItems() as $resultItem) {
      foreach ($resultItem->getFields(FALSE) as $field) {
        $values = $field->getValues();
        foreach ($values as $key => $value) {
          if (is_string($value)) {
            $values[$key] = $this->decodeStreamingExpressionValue($value);
          }
          elseif ($value instanceof TextValueInterface) {
            $value->setText($this->decodeStreamingExpressionValue($value->getText()));
          }
        }
        $field->setValues($values);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function encodeStreamingExpressionValue(string $value) {
    return preg_replace('/"/u', $this->configuration['replacement'], $value);
  }

  /**
   * {@inheritdoc}
   */
  public function decodeStreamingExpressionValue(string $value) {
    return preg_replace('/' . preg_quote($this->configuration['replacement'], '/') . '/u', '"', $value);
  }

}
