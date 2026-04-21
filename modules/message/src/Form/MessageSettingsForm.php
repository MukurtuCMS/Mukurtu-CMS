<?php

namespace Drupal\message\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\message\MessagePurgePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure file system settings for this site.
 */
final class MessageSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The message purge plugin manager.
   *
   * @var \Drupal\message\MessagePurgePluginManager
   */
  protected $purgeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'message_system_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['message.settings'];
  }

  /**
   * Holds the name of the keys we holds in the variable.
   */
  public function defaultKeys() {
    return [
      'delete_on_entity_delete',
    ];
  }

  /**
   * Constructs a Message Settings Form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\message\MessagePurgePluginManager $purge_manager
   *   The message purge plugin manager service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface|null $config_type_manager
   *   The typed config manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    MessagePurgePluginManager $purge_manager,
    ?TypedConfigManagerInterface $config_type_manager = NULL,
  ) {

    if (version_compare(\Drupal::VERSION, '10.2.0', '<')) {
      // @phpstan-ignore-next-line
      parent::__construct($config_factory);
    }
    else {
      parent::__construct($config_factory, $config_type_manager);
    }

    $this->entityTypeManager = $entity_type_manager;
    $this->purgeManager = $purge_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.message.purge'),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('message.settings');

    // Uses the same form keys as the MessageTemplateForm so that the purge
    // plugins form can be re-used.
    $form['settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Purge settings'),
      '#tree' => TRUE,
    ];

    $form['settings']['purge_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Purge messages'),
      '#description' => $this->t('Configure how messages will be deleted.'),
      '#default_value' => $config->get('purge_enable'),
    ];

    // Add the purge method settings form.
    $this->purgeManager->purgeSettingsForm($form, $form_state, $config->get('purge_methods'));

    $form['delete_on_entity_delete'] = [
      '#title' => $this->t('Auto delete messages referencing the following entities'),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => $this->getContentEntityTypes(),
      '#default_value' => $config->get('delete_on_entity_delete'),
      '#description' => $this->t('Messages that reference entities of these types will be deleted when the referenced entity gets deleted.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $config = $this->config('message.settings');

    foreach ($this->defaultKeys() as $key) {
      $config->set($key, $form_state->getValue($key));
    }

    $purge_enable = $form_state->getValue(['settings', 'purge_enable']);
    $config->set('purge_enable', $purge_enable);
    $config->set('purge_methods', $purge_enable ? $this->purgeManager->getPurgeConfiguration($form, $form_state) : []);

    $config->save();
  }

  /**
   * Get content entity types keyed by id.
   *
   * @return array
   *   Returns array of content entity types.
   */
  protected function getContentEntityTypes() {
    $options = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_id => $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        $options[$entity_type->id()] = $entity_type->getLabel();
      }
    }
    return $options;
  }

}
