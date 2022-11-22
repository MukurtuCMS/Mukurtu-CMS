<?php

namespace Drupal\mukurtu_import\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\mukurtu_import\MukurtuImportStrategyInterface;

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
 *     "label",
 *     "description"
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

}
