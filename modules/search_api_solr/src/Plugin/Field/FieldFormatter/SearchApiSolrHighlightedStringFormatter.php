<?php

namespace Drupal\search_api_solr\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'solr_highlighted_string' formatter.
 *
 * @FieldFormatter(
 *   id = "solr_highlighted_string",
 *   label = @Translation("Highlighted plain text (Search API Solr)"),
 *   field_types = {
 *     "string",
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class SearchApiSolrHighlightedStringFormatter extends FormatterBase {
  use SearchApiSolrHighlightedFormatterSettingsTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter::viewValue()
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\Core\Template\TwigEnvironment $twig */
    $twig = \Drupal::service('twig');
    /** @var \Drupal\Core\Template\TwigExtension $twigExtension */
    $twigExtension = \Drupal::service('twig.extension');

    $elements = [];

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $delta => $item) {
      $cacheableMetadata = new CacheableMetadata();
      // The fulltext search keys are usually set via a GET parameter.
      $cacheableMetadata->addCacheContexts(['url.query_args']);

      $elements[$delta] = [
        '#markup' => nl2br($this->getHighlightedValue($item, $twigExtension->escapeFilter($twig, $item->value), $langcode, $cacheableMetadata)),
      ];

      $cacheableMetadata->applyTo($elements[$delta]);
    }

    return $elements;
  }

}
