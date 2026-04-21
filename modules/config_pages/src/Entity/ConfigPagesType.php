<?php

namespace Drupal\config_pages\Entity;

use Drupal\config_pages\ConfigPagesAccessControlHandler;
use Drupal\config_pages\ConfigPagesTypeForm;
use Drupal\config_pages\ConfigPagesTypeInterface;
use Drupal\config_pages\ConfigPagesTypeListBuilder;
use Drupal\config_pages\Form\ConfigPagesTypeDeleteForm;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the config page type entity.
 *
 * @ConfigEntityType(
 *   id = "config_pages_type",
 *   label = @Translation("Config page type"),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\config_pages\ConfigPagesTypeForm",
 *       "add" = "Drupal\config_pages\ConfigPagesTypeForm",
 *       "edit" = "Drupal\config_pages\ConfigPagesTypeForm",
 *       "delete" = "Drupal\config_pages\Form\ConfigPagesTypeDeleteForm"
 *     },
 *     "access" = "Drupal\config_pages\ConfigPagesAccessControlHandler",
 *     "list_builder" = "Drupal\config_pages\ConfigPagesTypeListBuilder"
 *   },
 *   admin_permission = "administer config_pages types",
 *   config_prefix = "type",
 *   bundle_of = "config_pages",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "context" = "context",
 *     "menu" = "menu",
 *     "token" = "token"
 *   },
 *   links = {
 *     "delete-form" = "/admin/structure/config_pages/config-pages-content/manage/{config_pages_type}/delete",
 *     "edit-form" = "/admin/structure/config_pages/config-pages-content/manage/{config_pages_type}",
 *     "collection" = "/admin/structure/config_pages/config-pages-content/types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "context",
 *     "menu",
 *     "token"
 *   }
 * )
 */
#[ConfigEntityType(
  id: "config_pages_type",
  label: new TranslatableMarkup("Config page type"),
  handlers: [
    "form" => [
      "default" => ConfigPagesTypeForm::class,
      "add" => ConfigPagesTypeForm::class,
      "edit" => ConfigPagesTypeForm::class,
      "delete" => ConfigPagesTypeDeleteForm::class,
    ],
    "access" => ConfigPagesAccessControlHandler::class,
    "list_builder" => ConfigPagesTypeListBuilder::class,
  ],
  admin_permission: "administer config_pages types",
  config_prefix: "type",
  bundle_of: "config_pages",
  entity_keys: [
    "id" => "id",
    "label" => "label",
    "context" => "context",
    "menu" => "menu",
    "token" => "token",
  ],
  links: [
    "delete-form" => "/admin/structure/config_pages/config-pages-content/manage/{config_pages_type}/delete",
    "edit-form" => "/admin/structure/config_pages/config-pages-content/manage/{config_pages_type}",
    "collection" => "/admin/structure/config_pages/config-pages-content/types",
  ],
  config_export: [
    "id",
    "label",
    "context",
    "menu",
    "token",
  ],
)]
class ConfigPagesType extends ConfigEntityBundleBase implements ConfigPagesTypeInterface {

  /**
   * The config page type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The config page type label.
   *
   * @var string
   */
  protected $label;

  /**
   * Context Plugin manager to handle context dependency for ConfigPage.
   *
   * @var \Drupal\config_pages\ConfigPagesContextManagerInterface
   */
  protected $config_pages_context;

  /**
   * Provides the list of config_pages types.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Storage interface.
   * @param array $entities
   *   Array of entities.
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    $query = \Drupal::entityQuery('config_pages');

    $type = array_shift($entities);
    $config_page_ids = $query->accessCheck(TRUE)->condition('type', $type->id())->execute();
    $cp_storage = \Drupal::service('entity_type.manager')->getStorage('config_pages');
    if ($cp_storage && $config_page_ids) {
      // ConfigPage could possibly never submitted,
      // so no entities exists for this CP type.
      // Delete them only if we have some ID's for that.
      $cp_entities = $cp_storage->loadMultiple($config_page_ids);
      $cp_storage->delete($cp_entities);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $this->config_pages_context = \Drupal::service('plugin.manager.config_pages_context');
  }

  /**
   * Provides the serialized context data.
   *
   * @param bool $fallback
   *   Not count the context plugins.
   *
   * @return string
   *   Return context data.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getContextData($fallback = FALSE) {
    $contextData = [];
    if (!empty($this->context['group'])) {
      foreach ($this->context['group'] as $context_id => $context_enabled) {
        if ($context_enabled) {
          $item = $this->config_pages_context->createInstance($context_id);

          if ($fallback && !empty($this->context['fallback'][$context_id])) {
            $context_value = $this->context['fallback'][$context_id];
          }
          else {
            $context_value = $item->getValue();
          }
          $contextData[] = [$context_id => $context_value];
        }
      }
    }
    return serialize($contextData);
  }

  /**
   * Provides the context labels.
   *
   * @return string
   *   Return context label.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getContextLabel() {
    $contextData = [];
    if (!empty($this->context['group'])) {
      foreach ($this->context['group'] as $context_id => $context_enabled) {
        if ($context_enabled) {
          $item = $this->config_pages_context->getDefinition($context_id);
          $context = $this->config_pages_context->createInstance($context_id);
          $context_value = $item['label'] . ' (' . $context->getLabel() . ')';
          $contextData[] = $context_value;
        }
      }
    }
    return implode(', ', $contextData);
  }

  /**
   * Provides the context links.
   *
   * @return array
   *   Get context links.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getContextLinks() {
    $contextLinks = [];
    if (!empty($this->context['group'])) {
      foreach ($this->context['group'] as $context_id => $context_enabled) {
        if ($context_enabled) {
          $context = $this->config_pages_context->createInstance($context_id);
          $contextLinks[$context_id] = $context->getLinks();
        }
      }
    }
    return $contextLinks;
  }

}
