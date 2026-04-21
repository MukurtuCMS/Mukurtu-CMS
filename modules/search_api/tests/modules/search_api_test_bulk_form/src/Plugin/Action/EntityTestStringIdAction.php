<?php

namespace Drupal\search_api_test_bulk_form\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an action for the entity_test_string_id entity type.
 */
#[Action(
  id: 'search_api_test_bulk_form_entity_test_string_id',
  label: new TranslatableMarkup('Search API test bulk form action: entity_test_string_id'),
  type: 'entity_test_string_id',
)]
class EntityTestStringIdAction extends ActionBase {

  use TestActionTrait;

}
