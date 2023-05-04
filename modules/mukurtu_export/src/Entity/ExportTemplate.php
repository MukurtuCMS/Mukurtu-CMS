<?php

namespace Drupal\mukurtu_export\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;



/**
 * Defines the Mukurtu export entity.
 *
 * @ContentEntityType(
 *   id = "export_template",
 *   label = @Translation("Export Template"),
 *   label_collection = @Translation("Export Templates"),
 *   label_singular = @Translation("Export Template"),
 *   label_plural = @Translation("Export Templates"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Export Template",
 *     plural = "@count Export Templates",
 *   ),
 *   bundle_label = @Translation("Export Output Format"),
 *   handlers = {
 *     "access" = "Drupal\mukurtu_export\ExportTemplateAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   links = {
 *     "canonical" = "/dashboard/export/{export_template}",
 *     "add-page" = "/dashboard/export/add",
 *     "add-form" = "/dashboard/export/add/{bundle}",
 *     "edit-form" = "/dashboard/export/{export_template}/edit",
 *     "delete-form" = "/dashboard/export/{export_template}/delete",
 *     "collection" = "/admin/content/exports",
 *   },
 *   translatable = TRUE,
 *   base_table = "export_template",
 *   data_table = "export_template_field_data",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "bundle",
 *     "langcode" = "langcode",
 *     "published" = "status"
 *   },
 *   bundle_entity_type = "export_type",
 * )
 */
class ExportTemplate extends ContentEntityBase implements ContentEntityInterface {
  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Title'))
      ->setDescription(t('A title for the export settings if saving them to be reused later.'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['field_public'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Public'))
      ->setDescription(t('Public templates are available to all users site-wide.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'checkbox',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The Export Process entity language code.'))
      ->setRevisionable(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Published'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the Export Process was created.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', array(
        'region' => 'hidden',
        'weight' => 0,
      ))
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }
}
