<?php

namespace Drupal\entity_browser\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\DisplayManager;
use Drupal\entity_browser\SelectionDisplayManager;
use Drupal\entity_browser\WidgetManager;
use Drupal\entity_browser\WidgetSelectorManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Class EntityBrowserEditForm.
 */
class EntityBrowserEditForm extends EntityForm {

  /**
   * Entity browser display plugin manager.
   *
   * @var \Drupal\entity_browser\DisplayManager
   */
  protected $displayManager;

  /**
   * Entity browser widget selector plugin manager.
   *
   * @var \Drupal\entity_browser\WidgetSelectorManager
   */
  protected $widgetSelectorManager;

  /**
   * Entity browser selection display plugin manager.
   *
   * @var \Drupal\entity_browser\SelectionDisplayManager
   */
  protected $selectionDisplayManager;

  /**
   * Entity browser widget plugin manager.
   *
   * @var \Drupal\entity_browser\WidgetManager
   */
  protected $widgetManager;

  /**
   * Constructs EntityBrowserEditForm form class.
   *
   * @param \Drupal\entity_browser\DisplayManager $display_manager
   *   Entity browser display plugin manager.
   * @param \Drupal\entity_browser\WidgetSelectorManager $widget_selector_manager
   *   Entity browser widget selector plugin manager.
   * @param \Drupal\entity_browser\SelectionDisplayManager $selection_display_manager
   *   Entity browser selection display plugin manager.
   * @param \Drupal\entity_browser\WidgetManager $widget_manager
   *   Entity browser widget plugin manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(DisplayManager $display_manager, WidgetSelectorManager $widget_selector_manager, SelectionDisplayManager $selection_display_manager, WidgetManager $widget_manager, MessengerInterface $messenger) {
    $this->displayManager = $display_manager;
    $this->selectionDisplayManager = $selection_display_manager;
    $this->widgetSelectorManager = $widget_selector_manager;
    $this->widgetManager = $widget_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.entity_browser.display'),
      $container->get('plugin.manager.entity_browser.widget_selector'),
      $container->get('plugin.manager.entity_browser.selection_display'),
      $container->get('plugin.manager.entity_browser.widget'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_browser\Entity\EntityBrowser $entity_browser */
    $entity_browser = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity_browser->label(),
      '#description' => $this->t("Label for the Entity Browser."),
      '#required' => TRUE,
    ];

    $form['name'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity_browser->id(),
      '#machine_name' => [
        'exists' => '\Drupal\entity_browser\Entity\EntityBrowser::load',
      ],
      '#disabled' => !$entity_browser->isNew(),
    ];

    if ($entity_browser->isNew()) {
      $help_text = '<div class="clearfix eb-help-text"><h2>' . $this->t('Entity Browser creation instructions') . '</h2>';
      $help_text .= '<p>' . $this->t('When you save this form, you will be taken to another form to configure widgets for the entity browser.') . '</p>';
      $help_text .= '<p>' . $this->t('You can find more detailed information about creating and configuring Entity Browsers at the <a href="@guide_href" target="_blank">official documentation</a>.', ['@guide_href' => 'https://drupal-media.gitbooks.io/drupal8-guide/content/modules/entity_browser/intro.html']) . '</p>';
      $help_text .= '</div>';
      $form['help_text'] = [
        '#markup' => $help_text,
      ];
    }

    $form['display_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display'),
    ];

    $form['display_wrapper']['description'] = $this->getPluginDescription('display');
    // Default if not set.
    if (empty($entity_browser->display)) {
      $entity_browser->setDisplay('modal');
    }

    $display_plugin = $entity_browser->getDisplay();

    $form['display_wrapper']['display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display plugin'),
      '#default_value' => $display_plugin->getPluginId(),
      '#options' => $this->getPluginOptions('display'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'displayPluginAjaxCallback'],
        'wrapper' => 'display-config-ajax-wrapper',
        'event' => 'change',
      ],
      '#executes_submit_callback' => TRUE,
      '#submit' => [[get_class($this), 'submitUpdateDisplayPluginSettings']],
      '#limit_validation_errors' => [['display']],
    ];

    $form['display_wrapper']['display_configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Display Plugin settings'),
      '#open' => FALSE,
      '#prefix' => '<div id="display-config-ajax-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    if ($display_config_form = $display_plugin->buildConfigurationForm([], $form_state)) {
      $form['display_wrapper']['display_configuration'] = array_merge($form['display_wrapper']['display_configuration'], $display_config_form);
    }
    else {
      $form['display_wrapper']['display_configuration']['no_options'] = [
        '#prefix' => '<p>',
        '#suffix' => '</p>',
        '#markup' => $this->t('This plugin has no configuration options.'),
      ];
    }

    $form['widget_selector_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Widget Selector'),
    ];

    $form['widget_selector_wrapper']['description'] = $this->getPluginDescription('widget_selector');

    // Set default if empty.
    if (empty($entity_browser->widget_selector)) {
      $entity_browser->setWidgetSelector('tabs');
    }

    $widget_selector_plugin = $entity_browser->getWidgetSelector();

    $form['widget_selector_wrapper']['widget_selector'] = [
      '#type' => 'select',
      '#title' => $this->t('Widget selector plugin'),
      '#default_value' => $widget_selector_plugin->getPluginId(),
      '#options' => $this->getPluginOptions('widget_selector'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'widgetSelectorAjaxCallback'],
        'wrapper' => 'widget-selector-config-ajax-wrapper',
        'event' => 'change',
      ],
      '#executes_submit_callback' => TRUE,
      '#submit' => [[get_class($this), 'submitUpdateWidgetSelector']],
      '#limit_validation_errors' => [['widget_selector']],
    ];

    $form['widget_selector_wrapper']['widget_selector_configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Widget Selector Plugin settings'),
      '#open' => FALSE,
      '#prefix' => '<div id="widget-selector-config-ajax-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    if ($widget_selector_config_form = $widget_selector_plugin->buildConfigurationForm([], $form_state)) {
      $form['widget_selector_wrapper']['widget_selector_configuration'] = array_merge($form['widget_selector_wrapper']['widget_selector_configuration'], $widget_selector_config_form);
    }
    else {
      $form['widget_selector_wrapper']['widget_selector_configuration']['no_options'] = [
        '#prefix' => '<p>',
        '#suffix' => '</p>',
        '#markup' => $this->t('This plugin has no configuration options.'),
      ];
    }

    $form['selection_display_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Selection Display'),
    ];

    $form['selection_display_wrapper']['description'] = $this->getPluginDescription('selection_display');

    if (empty($entity_browser->selection_display)) {
      $entity_browser->setSelectionDisplay('no_display');
    }

    $selection_display = $entity_browser->getSelectionDisplay();

    $form['selection_display_wrapper']['selection_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Selection display plugin'),
      '#default_value' => $selection_display->getPluginId(),
      '#options' => $this->getPluginOptions('selection_display'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'selectionDisplayAjaxCallback'],
        'wrapper' => 'selection-display-config-ajax-wrapper',
        'event' => 'change',
      ],
      '#executes_submit_callback' => TRUE,
      '#submit' => [[get_class($this), 'submitUpdateSelectionDisplay']],
      '#limit_validation_errors' => [['selection_display']],
    ];

    $form['selection_display_wrapper']['selection_display_configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Selection Display Plugin settings'),
      '#open' => FALSE,
      '#prefix' => '<div id="selection-display-config-ajax-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    if ($selection_display_config_form = $selection_display->buildConfigurationForm([], $form_state)) {
      $form['selection_display_wrapper']['selection_display_configuration'] = array_merge($form['selection_display_wrapper']['selection_display_configuration'], $selection_display_config_form);
    }
    else {
      $form['selection_display_wrapper']['selection_display_configuration']['no_options'] = [
        '#prefix' => '<p>',
        '#suffix' => '</p>',
        '#markup' => $this->t('This plugin has no configuration options.'),
      ];
    }

    return $form;
  }

  /**
   * Get options for form element.
   *
   * @return array
   *   Plugin options.
   */
  protected function getPluginOptions($plugin_type) {

    switch ($plugin_type) {
      case 'display':
        $definitions = $this->displayManager->getDefinitions();
        break;

      case 'widget_selector':
        $definitions = $this->widgetSelectorManager->getDefinitions();
        break;

      case 'selection_display':
        $definitions = $this->selectionDisplayManager->getDefinitions();
        break;

      default:
        return [];
    }

    $options = [];
    foreach ($definitions as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = $plugin_definition['label'];
    }

    return $options;
  }

  /**
   * Get an introductory description to the one of the three plugin types.
   *
   * @param string $plugin_type
   *   The plugin type.
   *
   * @return array
   *   Markup render element.
   */
  protected function getPluginDescription($plugin_type = '') {

    switch ($plugin_type) {
      case 'display':
        $intro = $this->t('Choose here how the entity browser should be presented to the end user.');
        $definitions = $this->displayManager->getDefinitions();
        break;

      case 'widget_selector':
        $intro = $this->t('In the last step of the entity browser configuration you can decide how the widgets will be available to the editor.');
        $definitions = $this->widgetSelectorManager->getDefinitions();
        break;

      case 'selection_display':
        $intro = $this->t('You can optionally allow a "work-in-progress selection zone" to be available to the editor, while still navigating, browsing and selecting the entities.');
        $definitions = $this->selectionDisplayManager->getDefinitions();
        break;

      default:
        return NULL;
    }

    $output = "<p>$intro</p>";
    $output .= '<p>' . $this->t('The available plugins are:') . '</p>';

    $output .= '<dl>';

    foreach ($definitions as $plugin_id => $plugin_definition) {
      $output .= '<dt><strong>' . $plugin_definition['label'] . ':</strong></dt>';
      $output .= '<dd>' . $plugin_definition['description'] . '</dd>';
    }
    $output .= '</dl>';

    return ['#markup' => $output];
  }

  /**
   * AJAX callback for returning new display configuration.
   */
  public static function displayPluginAjaxCallback($form, $form_state) {
    $form['display_wrapper']['display_configuration']['#open'] = TRUE;
    return $form['display_wrapper']['display_configuration'];
  }

  /**
   * AJAX callback for returning new widget selector configuration.
   */
  public static function widgetSelectorAjaxCallback($form, $form_state) {
    $form['widget_selector_wrapper']['widget_selector_configuration']['#open'] = TRUE;
    return $form['widget_selector_wrapper']['widget_selector_configuration'];
  }

  /**
   * AJAX callback for returning new widget selector configuration.
   */
  public static function selectionDisplayAjaxCallback($form, $form_state) {
    $form['selection_display_wrapper']['selection_display_configuration']['#open'] = TRUE;
    return $form['selection_display_wrapper']['selection_display_configuration'];
  }

  /**
   * AJAX submit callback for updating display.
   */
  public static function submitUpdateDisplayPluginSettings($form, FormStateInterface $form_state) {
    $display = $form_state->getValue('display');
    $form_state->getFormObject()->getEntity()->setDisplay($display);
    $form_state->setRebuild();
  }

  /**
   * AJAX submit callback for updating widget selector.
   */
  public static function submitUpdateWidgetSelector($form, FormStateInterface $form_state) {
    $widget_selector = $form_state->getValue('widget_selector');
    $form_state->getFormObject()->getEntity()->setWidgetSelector($widget_selector);
    $form_state->setRebuild();
  }

  /**
   * AJAX submit callback for updating display.
   */
  public static function submitUpdateSelectionDisplay($form, FormStateInterface $form_state) {
    $selection_display = $form_state->getValue('selection_display');
    $form_state->getFormObject()->getEntity()->setSelectionDisplay($selection_display);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {

    $this->entity->setName($form_state->getValue('name'))
      ->setLabel($form_state->getValue('label'))
      ->setDisplay($form_state->getValue('display'))
      ->setWidgetSelector($form_state->getValue('widget_selector'))
      ->setSelectionDisplay($form_state->getValue('selection_display'));

    $subform_state = SubformState::createForSubform($form['display_wrapper']['display_configuration'], $form, $form_state);
    $this->entity->getDisplay()->submitConfigurationForm($form, $subform_state);

    $subform_state = SubformState::createForSubform($form['widget_selector_wrapper']['widget_selector_configuration'], $form, $form_state);
    $this->entity->getWidgetSelector()->submitConfigurationForm($form, $subform_state);

    $subform_state = SubformState::createForSubform($form['selection_display_wrapper']['selection_display_configuration'], $form, $form_state);
    $this->entity->getSelectionDisplay()->submitConfigurationForm($form, $subform_state);

    return parent::buildEntity($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Only validate on form submission.
    if ($form_state->getTriggeringElement()['#name'] !== 'op') {
      return;
    }

    $subform_state = SubformState::createForSubform($form['display_wrapper']['display_configuration'], $form, $form_state);
    $this->entity->getDisplay()->validateConfigurationForm($form['display_wrapper']['display_configuration'], $subform_state);

    $subform_state = SubformState::createForSubform($form['widget_selector_wrapper']['widget_selector_configuration'], $form, $form_state);
    $this->entity->getWidgetSelector()->validateConfigurationForm($form['widget_selector_wrapper']['widget_selector_configuration'], $subform_state);

    $subform_state = SubformState::createForSubform($form['selection_display_wrapper']['selection_display_configuration'], $form, $form_state);
    $this->entity->getSelectionDisplay()->validateConfigurationForm($form['selection_display_wrapper']['selection_display_configuration'], $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // If this is a new entity, redirect to the widget edit form.
    if ($this->entity->isNew()) {
      $params = [
        'entity_browser' => $this->entity->id(),
      ];
      $form_state->setRedirect('entity.entity_browser.edit_widgets', $params);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = $this->entity->save();

    if ($status == SAVED_UPDATED) {
      $this->messenger->addMessage($this->t('The entity browser %name has been updated.', ['%name' => $this->entity->label()]));
    }
    elseif ($status == SAVED_NEW) {
      $this->messenger->addMessage($this->t('The entity browser %name has been added. Now you may configure the widgets you would like to use.', ['%name' => $this->entity->label()]));
    }
    return $status;
  }

}
