<?php

namespace Drupal\mukurtu_export\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * CSV Exporter Settings Config Entity
 *
 * @ConfigEntityType(
 *   id = "csv_exporter",
 *   label = @Translation("CSV Exporter Setting"),
 *   label_collection = @Translation("CSV Exporter Settings"),
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_prefix = "csv_exporter",
 *   config_export = {
 *     "id",
 *     "label",
 *     "uid",
 *     "description",
 *     "site_wide",
 *     "include_files",
 *     "entity_fields_export_list",
 *   },
 *   handlers = {
 *     "access" = "Drupal\mukurtu_export\CsvExporterAccessController",
 *     "list_builder" = "Drupal\mukurtu_export\Controller\CsvExporterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\mukurtu_export\Form\CsvExporterAddForm",
 *       "edit" = "Drupal\mukurtu_export\Form\CsvExporterEditForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer site configuration",
 *   links = {
 *     "canonical" = "/dashboard/export/format/csv/manage/{csv_exporter}",
 *     "add-form" = "/dashboard/export/format/csv/add",
 *     "edit-form" = "/dashboard/export/format/csv/manage/{csv_exporter}",
 *     "delete-form" = "/dashboard/export/format/csv/manage/{csv_exporter}/delete",
 *     "collection" = "/dashboard/export/settings/csv",
 *   }
 * )
 */
class CsvExporter extends ConfigEntityBase implements EntityOwnerInterface
{
  protected $uid;

  protected $include_files;

  protected $description;
  protected $site_wide;

  protected $entity_fields_export_list;


  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $uid = $this->getOwnerId() ?? (\Drupal::currentUser()->id() ?? 1);
    $this->setOwnerId($uid);
  }

  /**
   * {@inheritDoc}
   */
  public function getOwner() {
    return $this->entityTypeManager()->getStorage('user')->load($this->uid);
  }

  /**
   * {@inheritDoc}
   */
  public function setOwner(UserInterface $account) {
    $this->uid = $account->id();
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getOwnerId() {
    return $this->uid;
  }

  /**
   * {@inheritDoc}
   */
  public function setOwnerId($uid) {
    $this->uid = $uid;
    return $this;
  }

  public function getIncludeFiles() {
    return $this->include_files;
  }

  public function setIncludeFiles(bool $include_files) {
    $this->include_files = $include_files;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function isSiteWide() {
    return $this->site_wide == TRUE;
  }

  public function getMappedFields($entity_type_id, $bundle) {
    $all_field_defs = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);
    $entity_type = \Drupal::entityTypeManager()->getStorage($entity_type_id)->getEntityType();
    $id_fields = [$entity_type->getKey('id'), $entity_type->getKey('uuid')];
    $key = "{$entity_type_id}__{$bundle}";
    $map = $this->get('entity_fields_export_list');
    $result = [];

    // Mapped fields are in the order we want them already.
    if (isset($map[$key]) && !empty($map[$key])) {
      foreach ($map[$key] as $mapped_field_name => $mapped_field_label) {
        $result[] = [
          'field_name' => $mapped_field_name,
          'field_label' => $all_field_defs[$mapped_field_name]->getLabel(),
          'csv_header_label' => $mapped_field_label,
          'export' => TRUE,
        ];

        if (isset($all_field_defs[$mapped_field_name])) {
          unset($all_field_defs[$mapped_field_name]);
        }
      }
    }

    // Add the remaining, unmapped fields to the end of the list.
    foreach($all_field_defs as $field_name => $field_def) {
      if ($field_def->isComputed()) {
        continue;
      }
      $result[] = [
        'field_name' => $field_name,
        'field_label' => $field_def->getLabel(),
        'csv_header_label' => $field_def->getLabel(),
        'export' => $this->isNew() ? (!$field_def->isReadOnly() || in_array($field_name, $id_fields)) : FALSE,
      ];
    }

    return $result;
  }

  public function getSupportedEntityTypes() {
    return ['node', 'media', 'community', 'protocol', 'paragraph'];
  }

}
