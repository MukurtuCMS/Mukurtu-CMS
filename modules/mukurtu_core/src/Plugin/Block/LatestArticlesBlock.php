<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\Block\ViewsBlock;

/**
 * The Latest Articles block, with a setting for its View all articles link.
 *
 * Not a plugin of its own: LayoutBuilderHooks::latestArticlesBlockClass()
 * swaps it in as the class of the views_block derivative for
 * views.view.mukurtu_articles' latest_articles_block display, so the block
 * keeps its plugin ID, allowlist entries and existing placements.
 */
class LatestArticlesBlock extends ViewsBlock {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // On by default, so blocks placed before the setting existed keep their
    // link.
    return ['show_more_link' => TRUE] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $form['show_more_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show "View all articles" link'),
      '#default_value' => $this->configuration['show_more_link'] ?? TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['show_more_link'] = (bool) $form_state->getValue('show_more_link');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (empty($this->configuration['show_more_link']) && $this->displaySet) {
      $this->view->display_handler->setOption('use_more', FALSE);
    }
    return parent::build();
  }

}
