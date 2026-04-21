<?php

namespace Drupal\entity_browser\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\entity_browser\WidgetManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for configuring widgets for entity browser.
 */
class WidgetsConfig extends EntityForm {

  /**
   * Entity browser widget plugin manager.
   *
   * @var \Drupal\entity_browser\WidgetManager
   */
  protected $widgetManager;

  /**
   * WidgetsConfig constructor.
   *
   * @param \Drupal\entity_browser\WidgetManager $widget_manager
   *   Entity browser widget plugin manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(WidgetManager $widget_manager, MessengerInterface $messenger) {
    $this->widgetManager = $widget_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.entity_browser.widget'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_browser_widgets_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\entity_browser\EntityBrowserInterface $entity_browser */
    $entity_browser = $this->getEntity();

    $options = [
      '_none_' => '- ' . $this->t('Select a widget to add it') . ' -',
    ];

    $description = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('The available plugins are:'),
      '#items' => [],
      '#attributes' => ['class' => 'widget-description-list'],
    ];

    foreach ($this->widgetManager->getDefinitions() as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = $plugin_definition['label'];
      $description['#items'][] = ['#markup' => '<strong>' . $plugin_definition['label'] . ':</strong> ' . $plugin_definition['description']];
    }
    $default_widgets = [];
    foreach ($entity_browser->getWidgets() as $widget) {
      /** @var \Drupal\entity_browser\WidgetInterface $widget */
      $default_widgets[] = $widget->id();
    }
    $form['widget'] = [
      '#type' => 'select',
      '#title' => $this->t('Add widget plugin'),
      '#options' => $options,
      '#description' => $description,
      '#ajax' => [
        'callback' => [get_class($this), 'tableUpdatedAjaxCallback'],
        'wrapper' => 'widgets',
        'event' => 'change',
      ],
      '#executes_submit_callback' => TRUE,
      '#submit' => [[get_class($this), 'submitAddWidget']],
      '#limit_validation_errors' => [['widget']],
    ];
    $form_state->unsetValue('widget');

    $form['widgets'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'widgets'],
    ];

    $form['widgets']['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Form'),
        $this->t('Operations'),
        $this->t('Actions'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('There are no widgets.'),
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'variant-weight',
      ],
      ],
    ];

    /** @var \Drupal\entity_browser\WidgetInterface $widget */
    foreach ($entity_browser->getWidgets() as $uuid => $widget) {
      $row = [
        '#attributes' => [
          'class' => ['draggable'],
        ],
      ];
      $row['label'] = [
        '#type' => 'textfield',
        '#default_value' => $widget->label(),
        '#title' => $this->t('Label (%label)', [
          '%label' => $widget->getPluginDefinition()['label'],
        ]),
      ];
      $row['form'] = [];
      $row['form'] = $widget->buildConfigurationForm($row['form'], $form_state);
      $row['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#name' => 'remove' . $uuid,
        '#ajax' => [
          'callback' => [get_class($this), 'tableUpdatedAjaxCallback'],
          'wrapper' => 'widgets',
          'event' => 'click',
        ],
        '#executes_submit_callback' => TRUE,
        '#submit' => [[get_class($this), 'submitDeleteWidget']],
        '#arguments' => $uuid,
        '#limit_validation_errors' => [],
      ];
      $row['weight'] = [
        '#type' => 'weight',
        '#default_value' => $widget->getWeight(),
        '#title' => $this->t('Weight for @widget widget', ['@widget' => $widget->label()]),
        '#title_display' => 'invisible',
        '#attributes' => [
          'class' => ['variant-weight'],
        ],
      ];
      $form['widgets']['table'][$uuid] = $row;
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * AJAX submit callback for adding widgets to the entity browser.
   */
  public static function submitAddWidget($form, FormStateInterface $form_state) {
    $entity_browser = $form_state->getFormObject()->getEntity();
    $widget = $form_state->getValue('widget');
    $weight = count($entity_browser->getWidgets()) + 1;
    $entity_browser->addWidget([
      'id' => $widget,
      'label' => $widget,
      'weight' => $weight,
      // Configuration will be set on the widgets page.
      'settings' => [],
    ]);

    $form_state->setRebuild();
  }

  /**
   * AJAX submit callback for removing widgets from the entity browser.
   */
  public static function submitDeleteWidget($form, FormStateInterface $form_state) {
    $entity_browser = $form_state->getFormObject()->getEntity();
    $entity_browser->deleteWidget($entity_browser->getWidget($form_state->getTriggeringElement()['#arguments']));
    $form_state->setRebuild();
  }

  /**
   * AJAX callback for all operations that update widgets table.
   */
  public static function tableUpdatedAjaxCallback($form, $form_state) {
    return $form['widgets'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $entity_browser = $this->getEntity();
    /** @var \Drupal\entity_browser\WidgetInterface $widget */
    foreach ($entity_browser->getWidgets() as $widget) {
      $widget->validateConfigurationForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_browser = $this->getEntity();
    $table = $form_state->getValue('table');
    /** @var \Drupal\entity_browser\WidgetInterface $widget */
    foreach ($entity_browser->getWidgets() as $uuid => $widget) {
      $widget->submitConfigurationForm($form, $form_state);
      $widget->setWeight($table[$uuid]['weight']);
      $widget->setLabel($table[$uuid]['label']);
    }
    $status = $entity_browser->save();

    if ($status == SAVED_UPDATED) {
      $this->messenger->addMessage($this->t('The entity browser %name has been updated.', ['%name' => $this->entity->label()]));
    }
  }

}
