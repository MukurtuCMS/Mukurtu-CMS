<?php

namespace Drupal\message_notify_ui\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\message_notify\MessageNotifier;
use Drupal\message_notify\Plugin\Notifier\Manager;
use Drupal\message_notify_ui\MessageNotifyUiSenderSettingsFormManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for send a message entity.
 *
 * @ingroup message_notify_ui
 */
final class MessageNotifyForm extends EntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface|\Drupal\Core\Entity\RevisionLogInterface
   */
  protected $entity;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The message notifier service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected $messageNotifier;

  /**
   * Message notifier manager service.
   *
   * @var \Drupal\message_notify\Plugin\Notifier\Manager
   */
  protected $messageNotifierManager;

  /**
   * Message notify UI sender settings form manager.
   *
   * @var \Drupal\message_notify_ui\MessageNotifyUiSenderSettingsFormInterface
   */
  protected $messageNotifyUiSenderSettingsForm;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Keep track of the notify plugins and their matching sender pluign ID.
   *
   * @var array
   */
  protected $plugins;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\message_notify\MessageNotifier $message_notifier
   *   The message notifier service.
   * @param \Drupal\message_notify\Plugin\Notifier\Manager $message_notify_manager
   *   Message notifier manager service.
   * @param \Drupal\message_notify_ui\MessageNotifyUiSenderSettingsFormManager $message_notify_ui_setting_form_manager
   *   The message notify UI sender settings form manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager interface.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    MessageNotifier $message_notifier,
    Manager $message_notify_manager,
    MessageNotifyUiSenderSettingsFormManager $message_notify_ui_setting_form_manager,
    LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->time = $time;
    $this->messageNotifier = $message_notifier;
    $this->messageNotifierManager = $message_notify_manager;
    $this->messageNotifyUiSenderSettingsForm = $message_notify_ui_setting_form_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('message_notify.sender'),
      $container->get('plugin.message_notify.notifier.manager'),
      $container->get('plugin.manager.message_notify_ui_sender_settings_form'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions'] = parent::buildForm($form, $form_state)['actions'];

    $senders = [];
    foreach ($this->messageNotifierManager->getDefinitions() as $definition) {
      $senders[$definition['id']] = $definition['title'];
    }

    $senders_ids = array_keys($senders);

    $form['senders'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select a notifier handler'),
      '#options' => $senders,
      '#default_value' => reset($senders_ids),
    ];

    $form['senders_form'] = [];

    if ($this->languageManager->isMultilingual()) {
      // Multilingual is on. Add languages to the select list.
      $languages = [];

      foreach ($this->languageManager->getLanguages() as $language) {
        $languages[$language->getId()] = $language->getName();
      }

      $form['language'] = [
        '#type' => 'select',
        '#title' => $this->t('Select a language'),
        '#options' => $languages,
      ];
    }

    foreach ($this->messageNotifyUiSenderSettingsForm->getDefinitions() as $definition) {
      $plugin = $this->messageNotifyUiSenderSettingsForm->createInstance($definition['id']);
      $this->plugins[$definition['notify_plugin']] = $definition['id'];

      $form['senders_form'][$definition['id']] = $plugin->form();
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Notify'),
        '#submit' => ['::submitForm', '::save'],
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this
      ->messageNotifyUiSenderSettingsForm
      ->createInstance($this->plugins[$form_state->getValue('senders')])
      ->setMessage($this->entity)
      ->validate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $plugin = $form_state->getValue('senders');
    $this
      ->messageNotifyUiSenderSettingsForm
      ->createInstance($this->plugins[$plugin])
      ->setMessage($this->entity)
      ->submit($this->messageNotifier, $form_state);
  }

}
