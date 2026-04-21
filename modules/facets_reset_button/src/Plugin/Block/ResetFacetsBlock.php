<?php

namespace Drupal\facets_reset_button\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Reset Facets Button' Block.
 *
 * @Block(
 *   id = "facets_reset_button",
 *   admin_label = @Translation("Facets Reset Button block"),
 *   category = @Translation("Facets"),
 * )
 */
class ResetFacetsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $url = \Drupal::request();

    $path = $url->getPathInfo();
    $query_string = $url->getQueryString();
    if ($query_string != null) {
      $path = str_replace($query_string, "", $path);
    }

    return [
      '#theme' => 'facets_reset_button',
      '#link' => $path,
      '#attributes' => [
        // Add classes needed for ajax.
        'class' => [
          'facets-reset-button',
          'js-facet-block-id-' . $this->pluginId,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // A facet block cannot be cached, because it must always match the current
    // search results, and Search API gets those search results from a data
    // source that can be external to Drupal. Therefore it is impossible to
    // guarantee that the search results are in sync with the data managed by
    // Drupal. Consequently, it is not possible to cache the search results at
    // all. If the search results cannot be cached, then neither can the facets,
    // because they must always match.
    // Fortunately, facet blocks are rendered using a lazy builder (like all
    // blocks in Drupal), which means their rendering can be deferred (unlike
    // the search results, which are the main content of the page, and deferring
    // their rendering would mean sending an empty page to the user). This means
    // that facet blocks can be rendered and sent *after* the initial page was
    // loaded, by installing the BigPipe (big_pipe) module.
    //
    // When BigPipe is enabled, the search results will appear first, and then
    // each facet block will appear one-by-one, in DOM order.
    // See https://www.drupal.org/project/big_pipe.
    //
    // In a future version of Facet API, this could be refined, but due to the
    // reliance on external data sources, it will be very difficult if not
    // impossible to improve this significantly.
    //
    // Note: when using Drupal core's Search module instead of the contributed
    // Search API module, the above limitations do not apply, but for now it is
    // not considered worth the effort to optimize this just for Drupal core's
    // Search.
    return 0;
  }

}
