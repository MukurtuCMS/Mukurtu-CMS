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
 *   label_collection = @Translation("  CSV Exporter Settings"),
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "description" = "description",
 *     "site_wide" = "site_wide",
 *     "include_files" = "include_files",
 *   },
 *   config_prefix = "csv_exporter",
 *   config_export = {
 *     "id",
 *     "label",
 *     "uid",
 *     "description",
 *     "site_wide",
 *     "include_files",
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

}
