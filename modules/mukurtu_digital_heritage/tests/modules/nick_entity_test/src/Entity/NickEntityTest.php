<?php

namespace Drupal\nick_entity_test\Entity;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\nick_entity_test\PrintHelloTrait;

/**
 * Defines the test entity class.
 *
 * @ContentEntityType(
 *   id = "nick_entity_test",
 *   label = @Translation("Nick Test entity"),
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
 *   base_table = "nick_entity_test",
 *   admin_permission = "administer nick_entity_test content",
 *   persistent_cache = FALSE,
 *   list_cache_contexts = { "nick_entity_test_view_grants" },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   },
 *   links = {
 *     "canonical" = "/nick_entity_test/{entity_test}",
 *     "add-form" = "/nick_entity_test/add",
 *     "edit-form" = "/nick_entity_test/manage/{entity_test}/edit",
 *     "delete-form" = "/nick_entity_test/delete/entity_test/{entity_test}",
 *   },
 *   field_ui_base_route = "entity.nick_entity_test.admin_form",
 * )
 *
 * Note that this entity type annotation intentionally omits the "create" link
 * template. See https://www.drupal.org/node/2293697.
 */
class NickEntityTest extends EntityTest{
  use PrintHelloTrait;
}

