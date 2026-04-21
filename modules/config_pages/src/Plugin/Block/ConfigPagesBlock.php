<?php

namespace Drupal\config_pages\Plugin\Block;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a generic ConfigPages block.
 *
 * @Block(
 *   id = "config_pages_block",
 *   admin_label = @Translation("ConfigPages Block"),
 * )
 */
#[Block(
  id: "config_pages_block",
  admin_label: new TranslatableMarkup("ConfigPages Block"),
)]
class ConfigPagesBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityDisplayRepositoryInterface $entity_display_repository,
    EntityTypeManagerInterface $entity_type_manager,
  ) {

    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $config = $this->getConfiguration();

    if (!empty($config['config_page_type'])) {
      $config_page = ConfigPages::config($config['config_page_type']);
      return is_object($config_page)
        ? $config_page->getCacheTags()
        : ['config_pages_list:' . $config['config_page_type']];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();

    if (!empty($config['config_page_type'])) {
      $config_page = ConfigPages::config($config['config_page_type']);
      if (!is_object($config_page)) {
        return [];
      }
      $view_mode = $config['config_page_view_mode'];
      $build = $this->entityTypeManager->getViewBuilder('config_pages')
        ->view($config_page, $view_mode, NULL);

      // Add contextual links to block.
      $build['#contextual_links'] = [
        'config_pages_type' => [
          'route_parameters' => ['config_pages_type' => $config['config_page_type']],
        ],
      ];
      return $build;
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $settings = parent::defaultConfiguration();

    // Set custom cache settings.
    if (isset($this->pluginDefinition['cache'])) {
      $settings['cache'] = $this->pluginDefinition['cache'];
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getMachineNameSuggestion() {
    return 'config_pages';
  }

  /**
   * {@inheritDoc}
   */
  public function access(AccountInterface $account, $return_as_object = FALSE) {
    $config = $this->getConfiguration();
    if (!empty($config['config_page_type'])) {
      $config_page = ConfigPages::config($config['config_page_type']);
      if ($config_page) {
        return $config_page->access('view', $account, $return_as_object);
      }
    }
    return parent::access($account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    // Get all available ConfigPages types and prepare options list.
    $config = $this->getConfiguration();
    $config_pages_types = ConfigPagesType::loadMultiple();
    $options = [];
    foreach ($config_pages_types as $cp_type) {
      $id = $cp_type->id();
      $label = $cp_type->label();
      $options[$id] = $label;
    }
    $form['config_page_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select ConfigPage type to show'),
      '#options' => $options,
      '#default_value' => $config['config_page_type'] ?? '',
    ];

    $view_modes = $this->entityDisplayRepository->getViewModes('config_pages');
    $options = [];
    foreach ($view_modes as $id => $view_mode) {
      $options[$id] = $view_mode['label'];
    }
    // Get view modes.
    $form['config_page_view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Select view mode for ConfigPage to show'),
      '#options' => $options,
      '#default_value' => $config['config_page_view_mode'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('config_page_type', $form_state->getValue('config_page_type'));
    $this->setConfigurationValue('config_page_view_mode', $form_state->getValue('config_page_view_mode'));
  }

}
