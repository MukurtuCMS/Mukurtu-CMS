<?php

namespace Drupal\entity_browser\Plugin\views\argument_default;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The entity browser widget context argument default handler.
 *
 * @ViewsArgumentDefault(
 *   id = "entity_browser_widget_context",
 *   title = @Translation("Entity Browser Context")
 * )
 */
class EntityBrowserWidgetContext extends ArgumentDefaultPluginBase {

  /**
   * The selection storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $selectionStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->selectionStorage = $container->get('entity_browser.selection_storage');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['context_key'] = ['default' => 'target_bundles'];
    $options['fallback'] = ['default' => 'all'];
    $options['multiple'] = ['default' => 'or'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['context_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Context key'),
      '#description' => $this->t('The key within the widget context.'),
      '#default_value' => $this->options['context_key'],
    ];
    $form['fallback'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fallback value'),
      '#description' => $this->t('The fallback value to use when the context is not present. (ex: "all")'),
      '#default_value' => $this->options['fallback'],
    ];
    $form['multiple'] = [
      '#type' => 'radios',
      '#title' => $this->t('Multiple values'),
      '#description' => $this->t('Conjunction to use when handling multiple values. NOTE: for multiple values to work, at the bottom of this form expand the "More" fieldset and check "Allow multiple values".'),
      '#default_value' => $this->options['multiple'],
      '#options' => [
        'or' => $this->t('OR'),
        'and' => $this->t('AND'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function access() {
    return $this->view->getDisplay()->pluginId === 'entity_browser';
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $current_request = $this->view->getRequest();
    $context_key = $this->options['context_key'];
    $argument = $this->options['fallback'];
    // Check if the widget context is available.
    if (!empty($context_key) && $current_request->query->has('uuid')) {
      $uuid = $current_request->query->get('uuid');
      if ($storage = $this->selectionStorage->get($uuid)) {
        if (isset($storage['widget_context']) && !empty($storage['widget_context'][$context_key])) {
          $value = $storage['widget_context'][$context_key];
          if (is_string($value)) {
            $argument = $value;
          }
          // If the context value is an array, test that it can be imploded.
          elseif (is_array($value)) {
            $non_scalar = array_filter($value, function ($item) {
              return !is_scalar($item);
            });
            if (empty($non_scalar)) {
              $conjunction = ($this->options['multiple'] == 'and') ? ',' : '+';
              $argument = implode($conjunction, $value);
            }
          }
        }
      }
    }
    return $argument;
  }

}
