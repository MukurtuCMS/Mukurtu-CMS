<?php

namespace Drupal\leaflet_views\Plugin\views\display;

use Drupal\views\Plugin\views\display\Attachment;
use Drupal\views\ViewExecutable;

/**
 * Plugin attachment of additional leaflet features to leaflet map views.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "leaflet_attachment",
 *   title = @Translation("Leaflet Attachment"),
 *   help = @Translation("Add additional markers to a leaflet map."),
 *   no_ui = TRUE
 * )
 *
 * @todo We only use very few features from the parent class Attachment, so this
 *       should probably just extend DisplayPluginBase to simplify things.
 */
class LeafletAttachment extends Attachment {

  /**
   * Whether the display allows the use of a pager or not.
   *
   * @var bool
   */
  protected $usesPager = FALSE;

  /**
   * Whether the display allows the use of a 'more' link or not.
   *
   * @var bool
   */
  protected $usesMore = FALSE;

  /**
   * Whether the display allows area plugins.
   *
   * @var bool
   */
  protected $usesAreas = FALSE;

  /**
   * {@inheritdoc}
   */
  public function usesLinkDisplay() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function attachTo(ViewExecutable $view, $display_id, array &$build) {
    $displays = $this->getOption('displays');

    if (empty($displays[$display_id])) {
      return;
    }

    if (!$this->access()) {
      return;
    }

    $args = $this->getOption('inherit_arguments') ? $this->view->args : [];
    $view->setArguments($args);
    $view->setDisplay($this->display['id']);
    if ($this->getOption('inherit_pager')) {
      $view->display_handler->usesPager = $this->view->displayHandlers->get($display_id)
        ->usesPager();
      $view->display_handler->setOption('pager', $this->view->displayHandlers->get($display_id)
        ->getOption('pager'));
    }
    if ($render = $view->render()) {
      $this->view->attachment_before[] = $render + [
        '#leaflet-attachment' => TRUE,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = (!empty($this->view->result) || $this->view->style_plugin->evenEmpty()) ? $this->view->style_plugin->render() : [];

    // The element is rendered during preview only; when used as an attachment
    // in the Leaflet class, only the 'rows' property is used.
    $element = [
      '#markup' => print_r($rows, TRUE),
      '#prefix' => '<pre>',
      '#suffix' => '</pre>',
      '#attached' => &$this->view->element['#attached'],
      'rows' => $rows,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return 'leaflet';
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Overrides for standard stuff.
    $options['style']['contains']['type']['default'] = 'leaflet_marker_default';
    $options['defaults']['default']['style'] = FALSE;
    $options['row']['contains']['type']['default'] = 'leaflet_marker';
    $options['defaults']['default']['row'] = FALSE;

    return $options;
  }

}
