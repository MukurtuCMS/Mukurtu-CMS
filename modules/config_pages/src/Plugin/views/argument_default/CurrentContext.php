<?php

namespace Drupal\config_pages\Plugin\views\argument_default;

use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;

/**
 * Default argument plugin to use the current context value.
 *
 * @ViewsArgumentDefault(
 *   id = "config_pages_current_context",
 *   title = @Translation("Current context for ConfigPages")
 * )
 */
#[ViewsArgumentDefault(
  id: "config_pages_current_context",
  title: new TranslatableMarkup("Current context for ConfigPages"),
)]
class CurrentContext extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['config_page_type'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Get all available ConfigPages types and prepare options list.
    $config_pages_types = ConfigPagesType::loadMultiple();
    $options = [];
    foreach ($config_pages_types as $cp_type) {
      $id = $cp_type->id();
      $label = $cp_type->label();
      $options[$id] = $label;
    }
    $form['config_page_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select ConfigPage type to get Context for.'),
      '#options' => $options,
      '#default_value' => $this->options['config_page_type'] ?? '',
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {

    $type = ConfigPagesType::load($this->options['config_page_type']);
    $context = $type->getContextData();

    return $context;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [];
  }

}
