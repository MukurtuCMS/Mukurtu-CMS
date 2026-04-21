<?php

namespace Drupal\search_api_solr\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'solr_highlighted_text_default' formatter.
 *
 * @FieldFormatter(
 *   id = "solr_highlighted_text_default",
 *   label = @Translation("Highlighted text (Search API Solr)"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *   },
 *   quickedit = {
 *     "editor" = "form"
 *   }
 * )
 */
class SearchApiSolrHighlightedTextDefaultFormatter extends FormatterBase {
  use SearchApiSolrHighlightedFormatterSettingsTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   *
   * @see \Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter::viewValue()
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // The ProcessedText element already handles cache context & tag bubbling.
    // @see \Drupal\filter\Element\ProcessedText::preRenderText()
    foreach ($items as $delta => $item) {
      $cacheableMetadata = new CacheableMetadata();
      // The fulltext search keys are usually set via a GET parameter.
      $cacheableMetadata->addCacheContexts(['url.query_args']);

      $elements[$delta] = [
        '#type' => 'processed_text',
        '#text' => $this->getHighlightedValue($item, $item->value, $langcode, $cacheableMetadata),
        '#format' => $item->format,
        '#langcode' => $item->getLangcode(),
      ];

      $cacheableMetadata->applyTo($elements[$delta]);
    }

    return $elements;
  }

}
