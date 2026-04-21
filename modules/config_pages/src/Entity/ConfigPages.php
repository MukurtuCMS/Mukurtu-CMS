<?php

namespace Drupal\config_pages\Entity;

use Drupal\config_pages\ConfigPagesAccessControlHandler;
use Drupal\config_pages\ConfigPagesForm;
use Drupal\config_pages\ConfigPagesInterface;
use Drupal\config_pages\ConfigPagesListBuilder;
use Drupal\config_pages\ConfigPagesStorage;
use Drupal\config_pages\ConfigPagesViewsData;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\config_pages\ConfigPagesViewBuilder;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Defines the config page entity class.
 *
 * @ContentEntityType(
 *   id = "config_pages",
 *   label = @Translation("Config page"),
 *   bundle_label = @Translation("Config page type"),
 *   handlers = {
 *     "storage" = "Drupal\config_pages\ConfigPagesStorage",
 *     "access" = "Drupal\config_pages\ConfigPagesAccessControlHandler",
 *     "list_builder" = "Drupal\config_pages\ConfigPagesListBuilder",
 *     "view_builder" = "Drupal\config_pages\ConfigPagesViewBuilder",
 *     "views_data" = "Drupal\config_pages\ConfigPagesViewsData",
 *     "form" = {
 *       "add" = "Drupal\config_pages\ConfigPagesForm",
 *       "edit" = "Drupal\config_pages\ConfigPagesForm",
 *       "default" = "Drupal\config_pages\ConfigPagesForm"
 *     }
 *   },
 *   admin_permission = "administer config_pages types",
 *   base_table = "config_pages",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "label" = "label",
 *     "context" = "context",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/config_pages/{config_pages}",
 *     "edit-form" = "/config_pages/{config_pages}",
 *     "collection" = "/admin/structure/config_pages/config-pages-content",
 *   },
 *   bundle_entity_type = "config_pages_type",
 *   field_ui_base_route = "entity.config_pages_type.edit_form",
 *   render_cache = TRUE,
 * )
 */
#[ContentEntityType(
  id: "config_pages",
  label: new TranslatableMarkup("Config page"),
  bundle_label: new TranslatableMarkup("Config page type"),
  handlers: [
    "storage" => ConfigPagesStorage::class,
    "access" => ConfigPagesAccessControlHandler::class,
    "list_builder" => ConfigPagesListBuilder::class,
    "view_builder" => ConfigPagesViewBuilder::class,
    "views_data" => ConfigPagesViewsData::class,
    "form" => [
      "add" => ConfigPagesForm::class,
      "edit" => ConfigPagesForm::class,
      "default" => ConfigPagesForm::class,
    ],
  ],
  admin_permission: "administer config_pages types",
  base_table: "config_pages",
  entity_keys: [
    "id" => "id",
    "bundle" => "type",
    "label" => "label",
    "context" => "context",
    "uuid" => "uuid",
  ],
  links: [
    "canonical" => "/config_pages/{config_pages}",
    "edit-form" => "/config_pages/{config_pages}",
    "collection" => "/admin/structure/config_pages/config-pages-content",
  ],
  bundle_entity_type: "config_pages_type",
  field_ui_base_route: "entity.config_pages_type.edit_form",
  render_cache: TRUE,
)]
class ConfigPages extends ContentEntityBase implements ConfigPagesInterface {

  use EntityChangedTrait;

  /**
   * The theme the config page is being created in.
   *
   * When creating a new config page from the config page library, the user is
   * redirected to the configure form for that config page in the given theme.
   * The theme is stored against the config page when the config page
   * add form is shown.
   *
   * @var string
   */
  protected $theme;

  /**
   * {@inheritdoc}
   */
  public function createDuplicate() {
    $duplicate = parent::createDuplicate();
    if ($duplicate->revision_id) {
      $duplicate->revision_id->value = NULL;
    }
    $duplicate->id->value = NULL;
    return $duplicate;
  }

  /**
   * {@inheritdoc}
   */
  public function setTheme($theme) {
    $this->theme = $theme;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTheme() {
    return $this->theme;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Config page ID'))
      ->setDescription(t('The config page ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The config page UUID.'))
      ->setReadOnly(TRUE);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('ConfigPage description'))
      ->setDescription(t('A brief description of your config page.'))
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', ['region' => 'hidden'])
      ->setDisplayConfigurable('form', TRUE);

    $fields['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('ConfigPage type'))
      ->setDescription(t('The config page type.'))
      ->setSetting('target_type', 'config_pages_type');

    $fields['context'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Context'))
      ->setDescription(t('The Config Page context.'))
      ->setRevisionable(FALSE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the config page was last edited.'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabel($label) {
    $this->set('label', $label);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(array $values = []) {
    return \Drupal::entityTypeManager()
      ->getStorage('config_pages')
      ->create($values);
  }

  /**
   * Static map of (type:context) -> entity_id to avoid repeated queries.
   *
   * @var array
   */
  protected static $idMap = [];

  /**
   * Loads a config page entity by type and optional context.
   *
   * Uses a static ID map to avoid repeated entity queries within a single
   * request. The actual entity is always loaded via core's load() which
   * uses its own memory cache with proper invalidation.
   *
   * @param string|null $type
   *   Config page type to load.
   * @param string|null $context
   *   Context which should be used to load entity.
   *
   * @return \Drupal\config_pages\Entity\ConfigPages|null
   *   Returns config page entity.
   */
  public static function config($type = NULL, $context = NULL) {
    if (empty($type)) {
      return NULL;
    }

    $conditions['type'] = $type;

    // Get current context if NULL.
    if ($context === NULL) {
      $typeEntity = ConfigPagesType::load($type);
      if (!is_object($typeEntity)) {
        return NULL;
      }
      $conditions['context'] = $typeEntity->getContextData();
    }
    else {
      $conditions['context'] = $context;
    }

    $cid = $conditions['type'] . ':' . $conditions['context'];

    if (isset(static::$idMap[$cid])) {
      return \Drupal::entityTypeManager()
        ->getStorage('config_pages')
        ->load(static::$idMap[$cid]);
    }

    $list = \Drupal::entityTypeManager()
      ->getStorage('config_pages')
      ->loadByProperties($conditions);

    // Try to get the fallback config page.
    if (!$list && $context === NULL) {
      $conditions['context'] = $typeEntity->getContextData(TRUE);
      $cid = $conditions['type'] . ':' . $conditions['context'];

      if (isset(static::$idMap[$cid])) {
        return \Drupal::entityTypeManager()
          ->getStorage('config_pages')
          ->load(static::$idMap[$cid]);
      }

      $list = \Drupal::entityTypeManager()
        ->getStorage('config_pages')
        ->loadByProperties($conditions);
    }

    // Try to load entity with empty context hash as a safety net.
    // This handles the case where context was recently enabled but existing
    // entities still have the old no-context hash.
    if (!$list && $context == NULL) {
      $emptyContextHash = serialize([]);
      if ($conditions['context'] !== $emptyContextHash) {
        $conditions['context'] = $emptyContextHash;
        $cid = $conditions['type'] . ':' . $conditions['context'];

        if (isset(static::$idMap[$cid])) {
          return \Drupal::entityTypeManager()
            ->getStorage('config_pages')
            ->load(static::$idMap[$cid]);
        }

        $list = \Drupal::entityTypeManager()
          ->getStorage('config_pages')
          ->loadByProperties($conditions);
      }
    }

    $entity = $list ? current($list) : NULL;
    if ($entity) {
      static::$idMap[$cid] = $entity->id();
    }

    return $entity;
  }

  /**
   * Resets the static lookup cache used by config().
   *
   * Call this after creating or deleting config page entities in tests
   * or long-running processes to ensure fresh lookups.
   */
  public static function resetConfigCache(): void {
    static::$idMap = [];
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    $config_pages_type = ConfigPagesType::load($this->bundle());
    $menu = $config_pages_type ? $config_pages_type->get('menu') : [];
    $path = $menu['path'] ?? '';

    return $path
      ? Url::fromRoute('config_pages.' . $this->bundle(), ['config_pages' => $this->id()], $options)
      : Url::fromRoute('entity.config_pages.canonical', ['config_pages' => $this->id()], $options);
  }

}
