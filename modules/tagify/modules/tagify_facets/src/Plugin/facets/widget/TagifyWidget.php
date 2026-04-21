<?php

namespace Drupal\tagify_facets\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * The Tagify widget.
 *
 * @FacetsWidget(
 *   id = "tagify",
 *   label = @Translation("Tagify"),
 *   description = @Translation("A configurable widget that shows a tagify component."),
 * )
 */
class TagifyWidget extends WidgetPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'match_operator' => 'CONTAINS',
      'max_items' => 10,
      'placeholder' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $form = parent::buildConfigurationForm($form, $form_state, $facet);
    $form['match_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Autocomplete matching'),
      '#default_value' => $this->getConfiguration()['match_operator'],
      '#options' => $this->getMatchOperatorOptions(),
      '#description' => $this->t('Select the method used to collect autocomplete suggestions. Note that <em>Contains</em> can cause performance issues on sites with thousands of entities.'),
      '#states' => [
        'visible' => [
          ':input[name$="widget_config[autocomplete]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['max_items'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of results'),
      '#default_value' => $this->getConfiguration()['max_items'],
      '#min' => 0,
      '#description' => $this->t('The number of suggestions that will be listed. Use <em>0</em> to remove the limit.'),
    ];
    $form['placeholder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#default_value' => $this->getConfiguration()['placeholder'],
      '#description' => $this->t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $build = parent::build($facet);
    $this->appendWidgetLibrary($build);
    return $build;
  }

  /**
   * Appends widget library and relevant information for it to build array.
   *
   * @param array $build
   *   Reference to build array.
   */
  protected function appendWidgetLibrary(array &$build) {
    $build['#attributes']['class'][] = 'js-facets-tagify';
    $build['#attributes']['class'][] = 'js-facets-widget';
    $build['#attributes']['class'][] = 'hidden';
    $build['#attached']['library'][] = 'tagify_facets/drupal.tagify_facets.tagify-widget';
    $build['#attached']['drupalSettings']['tagify']['tagify_facets_widget']['match_operator'] = $this->getConfiguration()['match_operator'];
    $build['#attached']['drupalSettings']['tagify']['tagify_facets_widget']['placeholder'] = $this->getConfiguration()['placeholder'];
    $build['#attached']['drupalSettings']['tagify']['tagify_facets_widget']['max_items'] = $this->getConfiguration()['max_items'];
  }

  /**
   * Returns the options for the match operator.
   *
   * @return array
   *   List of options.
   */
  protected function getMatchOperatorOptions(): array {
    return [
      'STARTS_WITH' => $this->t('Starts with'),
      'CONTAINS' => $this->t('Contains'),
    ];
  }

}
