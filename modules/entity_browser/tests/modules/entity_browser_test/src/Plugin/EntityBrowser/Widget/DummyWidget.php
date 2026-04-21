<?php

namespace Drupal\entity_browser_test\Plugin\EntityBrowser\Widget;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\WidgetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dummy widget implementation for test purposes.
 *
 * @EntityBrowserWidget(
 *   id = "dummy",
 *   label = @Translation("Dummy widget"),
 *   description = @Translation("Dummy widget existing for testing purposes."),
 *   auto_select = FALSE
 * )
 */
class DummyWidget extends WidgetBase {

  /**
   * Entity to be returned.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  public $entity;

  /**
   * State property.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_merge(parent::defaultConfiguration(), ['text' => '']);
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    return [
      '#markup' => $this->configuration['text'],
      '#parents' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $this->selectEntities([$this->entity], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    return $form_state->getValue('dummy_entities', []);
  }

  /**
   * {@inheritdoc}
   */
  public function access() {
    if ($this->state->get('eb_test_dummy_widget_access', TRUE)) {
      $access = AccessResult::allowed();
      $access->addCacheContexts(['eb_dummy']);
    }
    else {
      $access = AccessResult::forbidden();
      $access->addCacheContexts(['eb_dummy']);
    }
    return $access;
  }

}
