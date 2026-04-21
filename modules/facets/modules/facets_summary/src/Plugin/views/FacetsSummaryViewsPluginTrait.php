<?php

namespace Drupal\facets_summary\Plugin\views;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Helper for the Views summary plugin.
 */
trait FacetsSummaryViewsPluginTrait {

  /**
   * Builds the options form.
   *
   * @param array $form
   *   The form array that is being added to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function facetsSummaryViewsBuildOptionsForm(array &$form, FormStateInterface $form_state) {
    $options = [];

    /** @var \Drupal\facets_summary\Entity\FacetsSummary[] $facets_summaries */
    $facets_summaries = $this->facetSummaryStorage->loadMultiple();

    $format = 'search_api:views_%s__%s__%s';
    $source = sprintf($format, $this->view->getDisplay()->getPluginId(), $this->view->id(), $this->view->current_display);
    foreach ($facets_summaries as $facets_summary) {
      if ($facets_summary->getFacetSourceId() === $source) {
        $options[$facets_summary->id()] = $facets_summary->label();
      }
    }

    $form['facet_summary'] = [
      '#title' => 'Facet summary',
      '#options' => $options,
      '#type' => 'radios',
      '#required' => TRUE,
      '#default_value' => $this->options['facet_summary'] ?? [],
    ];

    $form['label_display'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display block title'),
      '#default_value' => ($this->options['label_display'] === BlockPluginInterface::BLOCK_LABEL_VISIBLE),
      '#return_value' => BlockPluginInterface::BLOCK_LABEL_VISIBLE,
    ];
  }

  /**
   * Gets the facets summary to render.
   *
   * @return array
   *   A summary of the facets being used.
   */
  public function facetsViewsGetFacetSummary() {
    $build = [];

    /** @var \Drupal\facets_summary\Entity\FacetsSummary $summary */
    $summary = $this->facetSummaryStorage->load($this->options['facet_summary']);
    if ($summary) {
      $facet_summary = $this->facetSummaryManager->build($summary);
      if (!empty($facet_summary)) {
        $summary_build = [
          '#theme' => 'block',
          '#configuration' => [
            'provider' => 'facets_summary',
            'label' => $summary->label(),
            'label_display' => ($this->options['label_display'] === BlockPluginInterface::BLOCK_LABEL_VISIBLE),
          ],
          '#id' => $summary->id(),
          '#plugin_id' => 'facet_summary_block:' . $summary->id(),
          '#base_plugin_id' => 'facet_block',
          '#derivative_plugin_id' => $summary->id(),
          '#weight' => 0,
          '#cache' => [
            'contexts' => [],
            'tags' => [],
            'max-age' => 0,
          ],
          'content' => $facet_summary,
        ];
      }
    }

    if (!empty($summary_build)) {
      $build = [
        '#theme' => 'facets_views_plugin',
        '#content' => $summary_build,
      ];
    }

    return $build;
  }

}
