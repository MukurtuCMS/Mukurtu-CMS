<?php

namespace Drupal\drafts_entity_test\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Defines the TestDraftEntity class.
 *
 * Note that this entity type attribute intentionally omits the "create" link
 * template. See https://www.drupal.org/node/2293697.
 */
#[ContentEntityType(
  id: 'drafts_entity_test',
  label: new TranslatableMarkup('entity'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'bundle' => 'type',
    'label' => 'name',
    'langcode' => 'langcode',
  ],
  handlers: [
    'list_builder' => 'Drupal\entity_test\EntityTestListBuilder',
    'view_builder' => 'Drupal\entity_test\EntityTestViewBuilder',
    'access' => 'Drupal\entity_test\EntityTestAccessControlHandler',
    'form' => [
      'default' => 'Drupal\entity_test\EntityTestForm',
      'delete' => 'Drupal\entity_test\EntityTestDeleteForm',
    ],
    'route_provider' => [
      'html' => 'Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider',
    ],
    'views_data' => 'Drupal\entity_test\EntityTestViewsData',
  ],
  links: [
    'canonical' => '/drafts_entity_test/{entity_test}',
    'add-form' => '/drafts_entity_test/add',
    'edit-form' => '/drafts_entity_test/manage/{entity_test}/edit',
    'delete-form' => '/drafts_entity_test/delete/entity_test/{entity_test}',
  ],
  admin_permission: 'administer drafts_entity_test content',
  persistent_cache: FALSE,
  field_ui_base_route: 'entity.drafts_entity_test.admin_form',
  list_cache_contexts: ['drafts_entity_test_view_grants'],
)]
class TestDraftEntity extends EntityTest
{
}
