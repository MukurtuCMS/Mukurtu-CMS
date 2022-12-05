<?php

namespace Drupal\mukurtu_import\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\mukurtu_import\MukurtuImportStrategyInterface;
use Drupal\user\UserInterface;
use Drupal\file\FileInterface;
use League\Csv\Reader;
use Exception;
/**
 * Defines the mukurtu_import_strategy entity type.
 *
 * @ConfigEntityType(
 *   id = "mukurtu_import_strategy",
 *   label = @Translation("Mukurtu Import Strategy"),
 *   label_collection = @Translation("mukurtu_import_strategies"),
 *   label_singular = @Translation("mukurtu_import_strategy"),
 *   label_plural = @Translation("mukurtu_import_strategies"),
 *   label_count = @PluralTranslation(
 *     singular = "@count mukurtu_import_strategy",
 *     plural = "@count mukurtu_import_strategies",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\mukurtu_import\MukurtuImportStrategyListBuilder",
 *     "form" = {
 *       "add" = "Drupal\mukurtu_import\Form\MukurtuImportStrategyForm",
 *       "edit" = "Drupal\mukurtu_import\Form\MukurtuImportStrategyForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "mukurtu_import_strategy",
 *   admin_permission = "administer mukurtu_import_strategy",
 *   links = {
 *     "collection" = "/admin/structure/mukurtu-import-strategy",
 *     "add-form" = "/admin/structure/mukurtu-import-strategy/add",
 *     "edit-form" = "/admin/structure/mukurtu-import-strategy/{mukurtu_import_strategy}",
 *     "delete-form" = "/admin/structure/mukurtu-import-strategy/{mukurtu_import_strategy}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "uid",
 *     "label",
 *     "description",
 *     "target_entity_type_id",
 *     "target_bundle",
 *     "mapping",
 *   }
 * )
 */
class MukurtuImportStrategy extends ConfigEntityBase implements MukurtuImportStrategyInterface {

  /**
   * The mukurtu_import_strategy ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The mukurtu_import_strategy label.
   *
   * @var string
   */
  protected $label;

  /**
   * The mukurtu_import_strategy status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The mukurtu_import_strategy description.
   *
   * @var string
   */
  protected $description;

  protected $target_entity_type_id;
  protected $target_bundle;
  protected $uid;

  /**
   * [0] => Array
   *  (
   *     [target] => nid
   *     [source] => id
   *  )
   */
  protected $mapping;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  public function setTargetEntityTypeId($entity_type_id) {
    $this->target_entity_type_id = $entity_type_id;
  }

  public function getTargetEntityTypeId() {
    return $this->target_entity_type_id ?? 'node';
  }

  public function setTargetBundle($bundle) {
    $this->target_bundle = $bundle;
  }

  public function getTargetBundle() {
    return $this->target_bundle ?? NULL;
  }

  public function getMapping() {
    return $this->mapping ?? [];
  }

  public function setMapping($mapping) {
    $this->mapping = $mapping;
  }

  public function getLabel() {
    return $this->label;
  }

  public function setLabel($label) {
    $this->label = $label;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    if (!$this->id()) {
      $this->id = $this->uuidGenerator()->generate();
    }

    if (!$this->uid) {
      $this->uid = 1;
    }
    parent::save();
  }

  public function getOwner() {
    return $this->entityTypeManager()->getStorage('user')->load($this->getOwnerId());
  }

  public function setOwner(UserInterface $account) {
    $this->uid = $account->id();
    return $this;
  }

  public function getOwnerId() {
    return $this->uid ?: 1;
  }

  public function setOwnerId($uid) {
    $this->uid = $uid;
    return $this;
  }

  public function applies(FileInterface $file) {
    $headers = $this->getCSVHeaders($file);
    $mapping = $this->getMapping();
    $diff = array_diff(array_column($mapping, 'source'), $headers);
    if (empty($diff)) {
      return TRUE;
    }
    return FALSE;
  }

  protected function getCSVHeaders(FileInterface $file) {
    try {
      $csv = Reader::createFromPath($file->getFileUri(), 'r');
    } catch (Exception $e) {
      return [];
    }
    $csv->setHeaderOffset(0);
    return $csv->getHeader();
  }

  protected function getDefinitionId(FileInterface $file) {
    return $this->getOwnerId() . "_" . $file->id() . "_" . $this->getTargetEntityTypeId() . "_" . $this->getTargetBundle();
  }

  public function toDefinition(FileInterface $file) {
    $mapping = $this->getMapping();
    $naiveProcess = array_combine(array_column($mapping, 'target'), array_column($mapping, 'source'));

    $entity_type_id = $this->getTargetEntityTypeId();
    $bundle = $this->getTargetBundle();
    $definition = [
      'id' => $this->getDefinitionId($file),
      'migration_tags' => ['importtest'],
      'source' => [
        'plugin' => 'csv',
        'path' => $file->getFileUri(),
        //'ids' => ['id', 'title'],
        //'ids' => ['title'],
        'ids' => ['id'],
        //'ids' => ['uuid'],
        'track_changes' => TRUE,
      ],
      'process' => $naiveProcess,
      'destination' => [
        'plugin' => "entity:$entity_type_id",
        'default_bundle' => $bundle,
        'overwrite_properties' => ['title'],
        'validate' => TRUE,
      ],
    ];

    return $definition;
  }

}
