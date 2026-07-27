<?php

namespace Drupal\mukurtu_core\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Entity reference selection restricted to eligible "add to X" containers.
 *
 * "Eligible" means the container does not already reference the current
 * item (per handler_settings.mukurtu_containing_field/mukurtu_current_item)
 * and the current user has update access to it. Used by the "Add to
 * Collection", "Add to Word List", and "Add to Personal Collection" widgets
 * so their autocomplete only suggests targets the item can actually be
 * added to.
 */
#[EntityReferenceSelection(
  id: "mukurtu_eligible_container",
  label: new TranslatableMarkup("Mukurtu: eligible container"),
  group: "mukurtu_eligible_container",
  weight: 1,
)]
class MukurtuEligibleContainerSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    // Custom handler_settings keys are merged directly into the top-level
    // plugin configuration by the selection plugin manager, not nested
    // under a 'handler_settings' key.
    $settings = $this->getConfiguration();
    $targetType = $settings['target_type'];
    $containingField = $settings['mukurtu_containing_field'] ?? NULL;
    $currentItemId = $settings['mukurtu_current_item'] ?? NULL;

    if ($containingField && $currentItemId) {
      $alreadyContaining = $this->entityTypeManager->getStorage($targetType)->getQuery()
        ->condition($containingField, $currentItemId)
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($alreadyContaining)) {
        $idKey = $this->entityTypeManager->getDefinition($targetType)->getKey('id');
        $query->condition($idKey, $alreadyContaining, 'NOT IN');
      }
    }

    // Some container types (e.g. personal collections) are per-user; only
    // ever suggest ones owned by the current user, matching how they're
    // scoped everywhere else on the site. Use the 'uid' entity key, not
    // 'owner' - personal_collection (and other entities using the generic
    // EntityOwnerTrait) only declare 'uid', so getKey('owner') returns FALSE
    // and would silently skip this restriction entirely.
    if (!empty($settings['mukurtu_owned_by_current_user'])) {
      $ownerKey = $this->entityTypeManager->getDefinition($targetType)->getKey('uid');
      if ($ownerKey) {
        $query->condition($ownerKey, $this->currentUser->id());
      }
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $options = parent::getReferenceableEntities($match, $match_operator, $limit);

    $targetType = $this->getConfiguration()['target_type'];
    $storage = $this->entityTypeManager->getStorage($targetType);

    foreach ($options as $bundle => &$bundleOptions) {
      foreach (array_keys($bundleOptions) as $id) {
        $entity = $storage->load($id);
        if (!$entity || !$entity->access('update')) {
          unset($bundleOptions[$id]);
        }
      }
      if (empty($bundleOptions)) {
        unset($options[$bundle]);
      }
    }

    return $options;
  }

}
