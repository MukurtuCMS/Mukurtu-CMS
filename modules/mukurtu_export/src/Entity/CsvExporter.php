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
 *     "entity_fields_export_list",
 *     "separator",
 *     "enclosure",
 *     "escape",
 *     "eol",
 *     "multivalue_delimiter",
 *     "field_id",
 *     "field_file",
 *     "field_image",
 *     "entity_reference_node",
 *     "entity_reference_media",
 *     "entity_reference_taxonomy_term",
 *     "entity_reference_user",
 *     "entity_reference_paragraph",
 *   },
 *   handlers = {
 *     "access" = "Drupal\mukurtu_export\CsvExporterAccessController",
 *     "list_builder" = "Drupal\mukurtu_export\Controller\CsvExporterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\mukurtu_export\Form\CsvExporterAddForm",
 *       "edit" = "Drupal\mukurtu_export\Form\CsvExporterEditForm",
 *       "delete" = "Drupal\mukurtu_export\Form\CsvExporterDeleteForm",
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
class CsvExporter extends ConfigEntityBase implements EntityOwnerInterface {
  protected $uid;

  /**
   * The description of the export config.
   *
   * @var string
   */
  protected $description;

  /**
   * Boolean to indicate if the config is available site wide.
   *
   * @var bool
   */
  protected $site_wide;

  /**
   * @var mixed
   */
  protected $entity_fields_export_list;

  /**
   * @var string
   */
  protected $separator;

  /**
   * @var string
   */
  protected $enclosure;

  /**
   * @var string
   */
  protected $escape;

  /**
   * @var string
   */
  protected $eol;

  /**
   * @var string
   */
  protected $multivalue_delimiter;

  /**
   * @var string
   */
  protected $field_id;

  /**
   * @var string
   */
  protected $field_file;

  /**
   * @var string
   */
  protected $field_image;

  /**
   * @var string
   */
  protected $entity_reference_node;

  /**
   * @var string
   */
  protected $entity_reference_media;

  /**
   * @var string
   */
  protected $entity_reference_user;

  /**
   * @var string
   */
  protected $entity_reference_taxonomy_term;

  /**
   * @var string
   */
  protected $entity_reference_paragraph;


  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);

    $uid = $this->getOwnerId() ?? (\Drupal::currentUser()->id() ?? 1);
    $this->setOwnerId($uid);

    if (!$this->id()) {
      $uuid = $this->uuid();
      $uuid = str_replace('-', '_', $uuid);
      $this->set('id', $uuid);
    }

    if (!$this->getSeparator()) {
      $this->setSeparator(",");
    }

    if (!$this->getEnclosure()) {
      $this->setEnclosure('"');
    }

    if (!$this->getEscape()) {
      $this->setEscape('\\');
    }

    if (!$this->getEol()) {
      $this->setEol('\n');
    }

    if (!$this->getMultivalueDelimiter()) {
      $this->setMultivalueDelimiter('||');
    }

    if (!$this->getIdFieldSetting()) {
      $this->setIdFieldSetting('id');
    }

    if (!$this->getFileFieldSetting()) {
      $this->setFileFieldSetting('id');
    }

    if (!$this->getImageFieldSetting()) {
      $this->setImageFieldSetting('id');
    }

    foreach (['node', 'media', 'taxonomy_term', 'user', 'paragraph'] as $target_type) {
      if (!$this->getEntityReferenceSetting($target_type)) {
        $this->setEntityReferenceSetting($target_type, 'id');
      }
    }
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

  public function getIdFieldSetting() {
    return $this->field_id;
  }

  public function setIdFieldSetting(string $id_field_option) {
    $this->field_id = $id_field_option;
    return $this;
  }

  public function getFileFieldSetting() {
    return $this->field_file;
  }

  public function setFileFieldSetting(string $file_field_option) {
    $this->field_file = $file_field_option;
    return $this;
  }

  public function getImageFieldSetting() {
    return $this->field_image;
  }

  public function setImageFieldSetting(string $image_field_option) {
    $this->field_image = $image_field_option;
    return $this;
  }

  public function getEntityReferenceSetting($target_type) {
    return $this->{"entity_reference_{$target_type}"} ?? NULL;
  }

  public function setEntityReferenceSetting($target_type, $option) {
    $this->{"entity_reference_{$target_type}"} = $option;
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
    return $this->site_wide === TRUE;
  }

  public function getSeparator() {
    return $this->separator;
  }

  public function setSeparator($separator) {
    $this->separator = $separator;
  }

  public function getEnclosure() {
    return $this->enclosure;
  }

  public function setEnclosure($enclosure) {
    $this->enclosure = $enclosure;
  }

  public function getEscape() {
    return $this->escape;
  }

  public function setEscape($escape) {
    $this->escape = $escape;
  }

  public function getEol() {
    return $this->eol;
  }

  public function setEol($eol) {
    $this->eol = $eol;
  }

  public function getMultivalueDelimiter() {
    return $this->multivalue_delimiter;
  }

  public function setMultivalueDelimiter($multivalue_delimiter) {
    $this->multivalue_delimiter = $multivalue_delimiter;
    return $this;
  }

  public function getMappedFields($entity_type_id, $bundle) {
    $all_field_defs = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);
    $entity_type = \Drupal::entityTypeManager()->getStorage($entity_type_id)->getEntityType();
    $id_fields = [$entity_type->getKey('id'), $entity_type->getKey('uuid')];
    $key = "{$entity_type_id}__{$bundle}";
    $mappedSubfields = [];
    $map = $this->get('entity_fields_export_list');
    $result = [];

    // Mapped fields are in the order we want them already.
    if (isset($map[$key]) && !empty($map[$key])) {
      foreach ($map[$key] as $mapped_field_name => $mapped_field_label) {
        $mapped_field_components = explode('/', $mapped_field_name);
        $mapped_base_field_name = $mapped_field_components[0];
        $mapped_subfield_name = $mapped_field_components[1] ?? NULL;

        if ($mappedField = $all_field_defs[$mapped_base_field_name] ?? NULL) {
          $result[] = [
            'field_name' => $mapped_field_name,
            'field_label' => $mappedField->getLabel(),
            'csv_header_label' => $mapped_field_label,
            'export' => TRUE,
          ];

          // Remove the field from the list. The remainder of the list will
          // be added to the end of the form for the user to assign mappings.
          if (!$mapped_subfield_name) {
            unset($all_field_defs[$mapped_field_name]);
          } else {
            // Track subfields.
            $mappedSubfields[$key][$mapped_base_field_name][] = $mapped_subfield_name;
          }
        }
      }
    }

    // Add the remaining, unmapped fields to the end of the list.
    /** @var \Drupal\Core\Field\FieldConfigInterface $field_def */
    foreach($all_field_defs as $field_name => $field_def) {
      if ($field_def->isComputed()) {
        continue;
      }

      // Break protocol field into the two separate sub-fields.
      // We are doing this because in v3, sharing setting and protocols were
      // two separate fields and users expect this behavior.
      // @todo this should really be an event/hook rather than hardcoded.
      if ($field_def->getType() == "cultural_protocol") {
        $protocolsSubfieldLabel = t("Protocols");
        $sharingSubfieldLabel = t("Sharing Setting");

        if (!in_array('protocols', $mappedSubfields[$key][$field_name] ?? [])) {
          $result[] = [
            'field_name' => $field_name . '/protocols',
            'field_label' => $field_def->getLabel() . ": " . $protocolsSubfieldLabel,
            'csv_header_label' => $protocolsSubfieldLabel,
            'export' => $this->isNew() ? (!$field_def->isReadOnly() || in_array($field_name, $id_fields)) : FALSE,
          ];
        }

        if (!in_array('sharing_setting', $mappedSubfields[$key][$field_name] ?? [])) {
          $result[] = [
            'field_name' => $field_name . '/sharing_setting',
            'field_label' => $field_def->getLabel() . ": " . $sharingSubfieldLabel,
            'csv_header_label' => $sharingSubfieldLabel,
            'export' => $this->isNew() ? (!$field_def->isReadOnly() || in_array($field_name, $id_fields)) : FALSE,
          ];
        }
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

  public function getHeaders($entity_type_id, $bundle) {
    $map = $this->get('entity_fields_export_list');
    $key = "{$entity_type_id}__{$bundle}";
    $headers = [];

    // Mapped fields are in the order we want them already.
    if (isset($map[$key]) && !empty($map[$key])) {
      foreach ($map[$key] as $mapped_field_name => $mapped_field_label) {
        $headers[$mapped_field_name] = $mapped_field_label;
      }
    }
    return $headers;
  }

  public function getExportFields($entity_type_id, $bundle) {
    $map = $this->get('entity_fields_export_list');
    $key = "{$entity_type_id}__{$bundle}";
    $fields = [];

    // Mapped fields are in the order we want them already.
    if (isset($map[$key]) && !empty($map[$key])) {
      foreach ($map[$key] as $mapped_field_name => $mapped_field_label) {
        $fields[] = $mapped_field_name;
      }
    }
    return $fields;
  }

  public function getSupportedEntityTypes() {
    return ['node', 'media', 'community', 'protocol', 'paragraph', 'file', 'taxonomy_term'];
  }

}
