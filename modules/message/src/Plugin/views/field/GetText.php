<?php

namespace Drupal\message\Plugin\views\field;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to present a link to the node.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("get_text")
 */
class GetText extends FieldPluginBase {

  /**
   * Stores the result of node_view_multiple for all rows to reuse it later.
   *
   * @var array
   */
  protected $build;

  /**
   * {@inheritdoc}
   */
  public function query() {}

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $delta = is_numeric($this->options['delta']) ? $this->options['delta'] : NULL;
    return new FormattableMarkup(implode("\n", $values->_entity->getText(NULL, $delta)), []);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['delta'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['delta'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delta'),
      '#size' => 5,
      '#default_value' => $this->options['delta'],
    ];

    parent::buildOptionsForm($form, $form_state);
  }

}
