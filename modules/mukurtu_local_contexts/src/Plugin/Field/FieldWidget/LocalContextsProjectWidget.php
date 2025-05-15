<?php

namespace Drupal\mukurtu_local_contexts\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\BooleanCheckboxWidget;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'local_contexts_project' field widget.
 *
 * @FieldWidget(
 *   id = "local_contexts_project",
 *   label = @Translation("Local Contexts Project Widget"),
 *   field_types = {"local_contexts_project"},
 *   multiple_values = TRUE
 * )
 */
class LocalContextsProjectWidget extends BooleanCheckboxWidget {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  protected $localContextsProjectManager;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountInterface $currentUser) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->currentUser = $currentUser;
    $this->localContextsProjectManager = new LocalContextsSupportedProjectManager();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition, $configuration['field_definition'], $configuration['settings'], $configuration['third_party_settings'], $container->get('current_user'));
  }

}
