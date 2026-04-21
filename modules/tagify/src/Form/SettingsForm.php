<?php

declare(strict_types=1);

namespace Drupal\tagify\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to configure Tagify settings.
 *
 * @package Drupal\tagify\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManager
   */
  protected FieldTypePluginManager $fieldTypePluginManager;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Field\FieldTypePluginManager $field_type_plugin_manager
   *   The field type plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, FieldTypePluginManager $field_type_plugin_manager) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->fieldTypePluginManager = $field_type_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('plugin.manager.field.field_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['tagify.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'tagify_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('tagify.settings');

    $form['set_default_widget'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set Tagify widget as default'),
      '#description' => $this->t('Enable this option to set Tagify as the default widget for all entity reference field types.'),
      '#default_value' => $config->get('set_default_widget'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('tagify.settings');
    $values = $form_state->getValues();

    $config->set('set_default_widget', $values['set_default_widget'])->save();
    $this->fieldTypePluginManager->clearCachedDefinitions();
    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));

    parent::submitForm($form, $form_state);
  }

}
