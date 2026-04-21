<?php

namespace Drupal\geolocation_demo\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for geolocation_demo module routes.
 */
abstract class DemoWidget extends FormBase {

  /**
   * Drupal\Core\Field\WidgetPluginManager definition.
   *
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected $pluginManagerFieldWidget;

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(WidgetPluginManager $plugin_manager_field_widget, EntityTypeManager $entity_type_manager) {
    $this->pluginManagerFieldWidget = $plugin_manager_field_widget;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.field.widget'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getWidgetForm($widget_id, array $form, FormStateInterface $form_state) {

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'geolocation_default_article',
    ]);

    $field_name = 'field_geolocation_demo_multiple';

    $field_definition = $node->getFieldDefinition($field_name);

    $widget_settings = [
      'field_definition' => $field_definition,
      'form_mode' => 'default',
      // No need to prepare, defaults have been merged in setComponent().
      'prepare' => TRUE,
      'configuration' => [
        'settings' => [],
        'third_party_settings' => [],
      ],
    ];

    $widget = $this->pluginManagerFieldWidget->getInstance(array_merge_recursive($widget_settings, ['configuration' => ['type' => $widget_id]]));

    $items = $node->get($field_name);

    $form['#parents'] = [];

    return $widget->form($items, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
