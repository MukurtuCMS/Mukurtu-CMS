<?php

namespace Drupal\drafts_entity_test\Entity;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftTrait;
use Drupal\mukurtu_drafts\Entity\MukurtuDraftInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the TestDraftEntity class.
 *
 * @ContentEntityType(
 *   id = "drafts_entity_test",
 *   label = @Translation("entity"),
 *   handlers = {
 *     "list_builder" = "Drupal\entity_test\EntityTestListBuilder",
 *     "view_builder" = "Drupal\entity_test\EntityTestViewBuilder",
 *     "access" = "Drupal\entity_test\EntityTestAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\entity_test\EntityTestForm",
 *       "delete" = "Drupal\entity_test\EntityTestDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "views_data" = "Drupal\entity_test\EntityTestViewsData"
 *   },
 *   base_table = "drafts_entity_test",
 *   admin_permission = "administer drafts_entity_test content",
 *   persistent_cache = FALSE,
 *   list_cache_contexts = { "drafts_entity_test_view_grants" },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/drafts_entity_test/{entity_test}",
 *     "add-form" = "/drafts_entity_test/add",
 *     "edit-form" = "/drafts_entity_test/manage/{entity_test}/edit",
 *     "delete-form" = "/drafts_entity_test/delete/entity_test/{entity_test}",
 *   },
 *   field_ui_base_route = "entity.drafts_entity_test.admin_form",
 * )
 *
 * Note that this entity type annotation intentionally omits the "create" link
 * template. See https://www.drupal.org/node/2293697.
 */
class TestDraftEntity extends EntityTest implements MukurtuDraftInterface
{
  use MukurtuDraftTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::draftBaseFieldDefinitions($entity_type);
    return $fields;
  }
}
