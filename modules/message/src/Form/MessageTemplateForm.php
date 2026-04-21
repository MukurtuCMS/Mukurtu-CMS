<?php

namespace Drupal\message\Form;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\message\FormElement\MessageTemplateMultipleTextField;
use Drupal\message\MessagePurgePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for node type forms.
 */
final class MessageTemplateForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\message\Entity\MessageTemplate
   */
  protected $entity;

  /**
   * The purge plugin manager.
   *
   * @var \Drupal\message\MessagePurgePluginManager
   */
  protected $purgeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs the message template form.
   *
   * @param \Drupal\message\MessagePurgePluginManager $purge_manager
   *   The message purge plugin manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(MessagePurgePluginManager $purge_manager, LanguageManagerInterface $language_manager, ModuleHandlerInterface $module_handler) {
    $this->purgeManager = $purge_manager;
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('plugin.manager.message.purge'),
      $container->get('language_manager'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\message\Entity\MessageTemplate $template */
    $template = $this->entity;

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $template->label(),
      '#description' => $this->t('The human-readable name of this message template. This text will be displayed as part of the list on the <em>Add message</em> page. It is recommended that this name begin with a capital letter and contain only letters, numbers, and spaces. This name must be unique.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['template'] = [
      '#type' => 'machine_name',
      '#default_value' => $template->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#disabled' => $template->isLocked(),
      '#machine_name' => [
        'exists' => '\Drupal\message\Entity\MessageTemplate::load',
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name for this message template. It must only contain lowercase letters, numbers, and underscores. This name will be used for constructing the URL of the %message-add page, in which underscores will be converted into hyphens.', [
        '%message-add' => $this->t('Add message'),
      ]),
    ];

    $form['description'] = [
      '#title' => $this->t('Description'),
      '#type' => 'textfield',
      '#default_value' => $this->entity->getDescription(),
      '#description' => $this->t('The human-readable description of this message template.'),
    ];
    $current_language = $this->languageManager->getCurrentLanguage()->getId();
    $multiple = new MessageTemplateMultipleTextField($this->entity, [get_class($this), 'addMoreAjax'], $current_language);

    $has_token_module = $this->moduleHandler->moduleExists('token');
    $multiple->textField($form, $form_state, $has_token_module);

    $settings = $this->entity->getSettings();

    $form['settings'] = [
      // Placeholder for other module to add their settings, that should be
      // added to the settings column.
      '#tree' => TRUE,
    ];

    $form['settings']['token options']['clear'] = [
      '#title' => $this->t('Clear empty tokens'),
      '#type' => 'checkbox',
      '#description' => $this->t('When this option is selected, empty tokens will be removed from display.'),
      '#default_value' => $settings['token options']['clear'] ?? FALSE,
    ];

    $form['settings']['token options']['token replace'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Token replace'),
      '#description' => $this->t('When this option is selected, token processing will happen.'),
      '#default_value' => !isset($settings['token options']['token replace']) || !empty($settings['token options']['token replace']),
    ];

    $form['settings']['purge_override'] = [
      '#title' => $this->t('Override global purge settings'),
      '#type' => 'checkbox',
      '#description' => $this->t('Override <a href=":settings">global purge settings</a> for messages using this template.', [':settings' => Url::fromRoute('message.settings')->toString()]),
      '#default_value' => $this->entity->getSetting('purge_override'),
    ];

    // Add the purge method settings form.
    $settings = $this->entity->getSetting('purge_methods', []);
    $this->purgeManager->purgeSettingsForm($form, $form_state, $settings);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save message template');
    $actions['delete']['#value'] = $this->t('Delete message template');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Save only the enabled purge methods if overriding the global settings.
    $override = $form_state->getValue(['settings', 'purge_override']);
    $settings = $this->entity->getSettings();
    $settings['purge_methods'] = $override ? $this->purgeManager->getPurgeConfiguration($form, $form_state) : [];
    $this->entity->setSettings($settings);
  }

  /**
   * Ajax callback for the "Add another item" button.
   *
   * This returns the new page content to replace the page content made obsolete
   * by the form submission.
   */
  public static function addMoreAjax(array $form, FormStateInterface $form_state) {
    return $form['text'];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Sort by weight.
    $text = $form_state->getValue('text');
    usort($text, function ($a, $b) {
      return SortArray::sortByKeyInt($a, $b, '_weight');
    });
    // Do not store weight, as these are now sorted.
    $text = array_map(function ($a) {
      unset($a['_weight']);
      return $a;
    }, $text);
    $this->entity->set('text', $text);

    $new = $this->entity->isNew();
    parent::save($form, $form_state);

    $params = [
      '%template' => $form_state->getValue('label'),
    ];

    if ($new) {
      $this->messenger()->addMessage($this->t('The message template %template created successfully.', $params));
    }
    else {
      $this->messenger()->addMessage($this->t('The message template %template has been updated.', $params));
    }
    $form_state->setRedirect('message.overview_templates');
    return $this->entity;
  }

}
