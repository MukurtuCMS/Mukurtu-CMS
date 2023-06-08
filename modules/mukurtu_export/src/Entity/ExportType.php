<?php

namespace Drupal\mukurtu_export\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Export Type
 *
 * @ConfigEntityType(
 *   id = "export_type",
 *   label = @Translation("Export Type"),
 *   bundle_of = "export_template",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_prefix = "export_type",
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\advertiser\Form\AdvertiserTypeEntityForm",
 *       "add" = "Drupal\advertiser\Form\AdvertiserTypeEntityForm",
 *       "edit" = "Drupal\advertiser\Form\AdvertiserTypeEntityForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer site configuration",
 *   links = {
 *     "canonical" = "/admin/structure/export_type/{export_type}",
 *     "add-form" = "/admin/structure/export_type/add",
 *     "edit-form" = "/admin/structure/export_type/{export_type}/edit",
 *     "delete-form" = "/admin/structure/export_type/{export_type}/delete",
 *     "collection" = "/admin/structure/export_type",
 *   }
 * )
 */
class ExportType extends ConfigEntityBundleBase
{
}
