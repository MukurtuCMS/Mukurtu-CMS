<?php

namespace Drupal\search_api_glossary\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;
use Drupal\facets\Widget\WidgetPluginInterface;
use Drupal\facets\Widget\WidgetPluginBase;

/**
 * The GlossaryAZ widget.
 *
 * @FacetsWidget(
 *   id = "glossaryaz",
 *   label = @Translation("Glossary AZ"),
 *   description = @Translation("A simple widget that shows a Glossary AZ"),
 * )
 */
class GlossaryAZWidget extends WidgetPluginBase implements WidgetPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    /** @var \Drupal\facets\Result\Result[] $results */
    $results = $facet->getResults();
    $items = [];

    foreach ($results as $result) {
      $items[] = $this->buildListItems($facet, $result);
    }

    $build = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#cache' => [
        'contexts' => [
          'url.path',
          'url.query_args',
        ],
      ],
      '#attributes' => [
        'class' => [],
      ],
    ];

    if ($facet->get('show_title') === TRUE) {
      $build['#title'] = $facet->label();
    }

    $configuration = $facet->getWidget()['config'];
    $enable_default_theme = empty($configuration['enable_default_theme']) ? FALSE : (bool) $configuration['enable_default_theme'];

    if ($enable_default_theme) {
      $build['#attached'] = [
        'library' => [
          'search_api_glossary/drupal.search_api_glossary.facet_css',
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildListItems(FacetInterface $facet, ResultInterface $result) {
    $classes = ['facet-item', 'glossaryaz'];
    // Not sure if glossary will have children.
    // Removed children processing for now.
    $items = $this->prepareLink($result);

    if ($result->isActive()) {
      $items['#attributes'] = ['class' => 'is-active'];
      $classes[] = 'is-active';
    }
    else {
      $items['#attributes'] = ['class' => 'is-inactive'];
    }

    // Add result, no result classes.
    if ($result->getCount() == 0) {
      $classes[] = 'no-results';
    }
    else {
      $classes[] = 'yes-results';
    }

    $items['#wrapper_attributes'] = ['class' => $classes];

    return $items;
  }

  /**
   * Returns the text or link for an item.
   *
   * @param \Drupal\facets\Result\ResultInterface $result
   *   A result item.
   *
   * @return array
   *   The item, as a renderable array.
   */
  protected function prepareLink(ResultInterface $result) {
    $configuration = $this->getConfiguration();
    $show_count = empty($configuration['show_count']) ? FALSE : (bool) $configuration['show_count'];

    $text = $result->getDisplayValue();

    // @todo revise this logic based on progress with
    // All items count is not correct when narrowing the results.
    // https://www.drupal.org/project/facets/issues/2692027
    // see https://git.drupalcode.org/project/facets/commit/21343a6
    if ($show_count && $result->getRawValue() != 'All') {
      $text .= ' (' . $result->getCount() . ')';
    }

    if (is_null($result->getUrl()) || $result->getCount() == 0) {
      $link = ['#markup' => $text];
    }
    else {
      $link = new Link($text, $result->getUrl());
      $link = $link->toRenderable();
    }

    return $link;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $form['show_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show count per Glossary item'),
    ];
    $form['enable_default_theme'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use default Glossary AZ Theme'),
    ];

    $config = $facet->getWidget()['config'];
    if (!is_null($config)) {
      if (isset($config['show_count'])) {
        $form['show_count']['#default_value'] = $config['show_count'];
      }
      if (isset($config['enable_default_theme'])) {
        $form['enable_default_theme']['#default_value'] = $config['enable_default_theme'];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'string';
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    // Are we dealing with Glossary field?
    // Load up the search index and processor.
    $index = $facet->getFacetSource()->getIndex();
    $processors = $index->getProcessors();

    // If the glossary field is not enabled for this index, just exit here.
    if (!isset($processors['glossary'])) {
      return FALSE;
    }
    else {
      $glossary_processor = $index->getProcessor('glossary');

      // Name of the field to check against.
      $glossary_field_id = $facet->getFieldIdentifier();

      // Check if chosen field is glossary or not.
      // checkFieldName will return TRUE or FALSE
      // see Glossary::checkFieldName()
      return $glossary_processor->checkFieldName($glossary_field_id);
    }
  }

}
