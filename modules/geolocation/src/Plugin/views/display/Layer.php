<?php

namespace Drupal\geolocation\Plugin\views\display;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\Attachment;
use Drupal\views\ViewExecutable;

/**
 * The plugin that handles an attachment display.
 *
 * Attachment displays are secondary displays that are 'attached' to a primary
 * display. Effectively they are a simple way to get multiple views within
 * the same view. They can share some information.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "geolocation_layer",
 *   title = @Translation("Geolocation Layer"),
 *   help = @Translation("Blah blah."),
 *   theme = "views_view",
 *   register_theme = FALSE,
 *   contextual_links_locations = {""}
 * )
 */
class Layer extends Attachment {

  /**
   * {@inheritdoc}
   */
  protected $usesAJAX = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesAttachments = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesAreas = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Force the style plugin to 'entity_reference_style' and the row plugin to
    // 'fields'.
    $options['style']['contains']['type'] = ['default' => 'geolocation_layer'];
    $options['defaults']['default']['style'] = FALSE;
    $options['row']['contains']['type'] = ['default' => 'fields'];
    $options['defaults']['default']['row'] = FALSE;
    $options['attachment_position'] = ['default' => 'before'];

    unset($options['render_pager']);

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    unset($options['attachment_position']);
    unset($options['render_pager']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    // It is very important to call the parent function here:
    parent::buildOptionsForm($form, $form_state);
    switch ($form_state->get('section')) {
      case 'displays':
        $displays = [];
        foreach ($this->view->storage->get('display') as $display_id => $display) {
          if ($this->view->displayHandlers->has($display_id)) {
            $style = $this->view->displayHandlers->get($display_id)->getOption('style');
            if ($style['type'] == 'maps_common') {
              $displays[$display_id] = $display['display_title'];
            }
          }
        }
        $form['displays'] = [
          '#title' => $this->t('Displays'),
          '#type' => 'checkboxes',
          '#description' => $this->t('Select which display or displays this should attach to.'),
          '#options' => array_map('\Drupal\Component\Utility\Html::escape', $displays),
          '#default_value' => $this->getOption('displays'),
        ];
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return 'geolocation_layer';
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
      $view->display_handler->usesPager = $this->view->displayHandlers->get($display_id)->usesPager();
      $view->display_handler->setOption('pager', $this->view->displayHandlers->get($display_id)->getOption('pager'));
    }

    if (empty($this->view->geolocationLayers[$display_id])) {
      $this->view->geolocationLayers[$display_id] = [];
    }
    $this->view->geolocationLayers[$display_id][] = $view->buildRenderable($this->display['id'], $args);
  }

  /**
   * {@inheritdoc}
   */
  public function buildRenderable(array $args = [], $cache = TRUE) {
    $render = parent::buildRenderable($args, $cache);

    $render['#embed'] = TRUE;

    return $render;
  }

}
