<?php

namespace Drupal\facets_summary\Plugin\facets_summary\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\facets_summary\FacetsSummaryInterface;
use Drupal\facets_summary\Processor\BuildProcessorInterface;
use Drupal\facets_summary\Processor\ProcessorPluginBase;

/**
 * Provides a processor that adds a link to reset facet filters.
 *
 * @SummaryProcessor(
 *   id = "reset_facets",
 *   label = @Translation("Adds reset facets link."),
 *   description = @Translation("When checked, this facet will add a link to reset enabled facets."),
 *   stages = {
 *     "build" = 30
 *   }
 * )
 */
class ResetFacetsProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

  /**
   * Indicates that reset link should be positioned before facet links.
   */
  const POSITION_BEFORE = 'before';

  /**
   * Indicates that reset link should be positioned after facet links.
   */
  const POSITION_AFTER = 'after';

  /**
   * Indicates that reset link should replace facet links.
   */
  const POSITION_REPLACE = 'replace';

  /**
   * {@inheritdoc}
   */
  public function build(FacetsSummaryInterface $facets_summary, array $build, array $facets) {
    $configuration = $facets_summary->getProcessorConfigs()[$this->getPluginId()];
    $hasReset = FALSE;

    // Do nothing if there are no selected facets.
    if (empty($build['#items'])) {
      return $build;
    }

    $request_stack = \Drupal::requestStack();
    $request = $request_stack->getMainRequest();
    $query_params = $request->query->all();

    // Bypass all active facets and remove them from the query parameters array.
    foreach ($facets as $facet) {
      $url_alias = $facet->getUrlAlias();
      $filter_key = $facet->getFacetSourceConfig()->getFilterKey() ?: 'f';

      if ($facet->getActiveItems()) {
        // This removes query params when using the query url processor.
        if (isset($query_params[$filter_key])) {
          foreach ($query_params[$filter_key] as $delta => $param) {
            if (strpos($param, $url_alias . ':') !== FALSE) {
              unset($query_params[$filter_key][$delta]);
            }
          }

          if (!$query_params[$filter_key]) {
            unset($query_params[$filter_key]);
          }
        }

        $hasReset = TRUE;
      }
    }

    if (!$hasReset) {
      return $build;
    }

    $path = \Drupal::service('path.current')->getPath();
    /** @var \Drupal\path_alias\AliasManager $pathAliasManager */
    $pathAliasManager = \Drupal::service('path_alias.manager');
    $path = $pathAliasManager->getAliasByPath($path);
    try {
      $url = Url::fromUserInput($path);
    }
    catch (\InvalidArgumentException $e) {
      $url = Url::fromUri($path);
    }
    $url->setOptions(['query' => $query_params]);
    // Check if reset link text is not set or it contains only whitespaces.
    // Set text from settings or set default text.
    if (empty($configuration['settings']['link_text']) || strlen(trim($configuration['settings']['link_text'])) === 0) {
      $itemText = $this->t('Reset');
    }
    else {
      $itemText = $configuration['settings']['link_text'];
    }
    $item = (new Link($itemText, $url))->toRenderable();
    $item['#wrapper_attributes'] = [
      'class' => [
        'facet-summary-item--clear',
      ],
    ];

    // Place link at necessary position.
    if ($configuration['settings']['position'] == static::POSITION_BEFORE) {
      array_unshift($build['#items'], $item);
    }
    elseif ($configuration['settings']['position'] == static::POSITION_AFTER) {
      $build['#items'][] = $item;
    }
    else {
      $build['#items'] = [
        $item,
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetsSummaryInterface $facets_summary) {
    // By default, there should be no config form.
    $config = $this->getConfiguration();

    $build['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reset facets link text'),
      '#default_value' => $config['link_text'],
    ];

    $build['position'] = [
      '#type' => 'select',
      '#options' => [
        static::POSITION_BEFORE => $this->t('Show reset link before facets links'),
        static::POSITION_AFTER => $this->t('Show reset link after facets links'),
        static::POSITION_REPLACE => $this->t('Show only reset link'),
      ],
      '#title' => $this->t('Position'),
      '#description' => $this->t('Set position of the link to display it before, after or instead of facets links.'),
      '#default_value' => $config['position'],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'link_text' => '',
      'position' => static::POSITION_BEFORE,
    ];
  }

}
