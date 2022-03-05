<?php

namespace Drupal\mukurtu_protocol\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Component\Utility\Html;
use Drupal\og\Og;

/**
 * Provides an entity reference selection for protocols.
 *
 * @EntityReferenceSelection(
 *   id = "mukurtu_protocol",
 *   label = @Translation("Protocol Selection for Protocol Control"),
 *   entity_types = {
 *     "protocol"
 *   },
 *   group = "mukurtu_protocol",
 *   weight = 1
 * )
 */
class ProtocolSelection extends DefaultSelection {

  /**
   * Builds an EntityQuery to get referenceable entities.
   *
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (Optional) The operation the matching should be done with. Defaults
   *   to "CONTAINS".
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The EntityQuery object with the basic conditions and sorting applied to
   *   it.
   */
  protected function buildProtocolQuery($match = NULL, $match_operator = 'CONTAINS') {
    $configuration = $this->getConfiguration();
    $target_type = $configuration['target_type'];
    $entity_type = $this->entityTypeManager->getDefinition($target_type);

    $query = $this->entityTypeManager->getStorage($target_type)->getQuery();
    $query->accessCheck(TRUE);

    // If 'target_bundles' is NULL, all bundles are referenceable, no further
    // conditions are needed.
    if (is_array($configuration['target_bundles'])) {
      // If 'target_bundles' is an empty array, no bundle is referenceable,
      // force the query to never return anything and bail out early.
      if ($configuration['target_bundles'] === []) {
        $query->condition($entity_type->getKey('id'), NULL, '=');
        return $query;
      }
      else {
        $query->condition($entity_type->getKey('bundle'), $configuration['target_bundles'], 'IN');
      }
    }

    if (isset($match) && $label_key = $entity_type->getKey('label')) {
      $query->condition($label_key, $match, $match_operator);
    }

    // Add protocol specific access tag.
    $query->addTag($target_type . '_members');

    // Add the Selection handler for system_query_entity_reference_alter().
    $query->addTag('entity_reference');
    $query->addMetaData('entity_reference_selection_handler', $this);

    // Add the sort option.
    if ($configuration['sort']['field'] !== '_none') {
      $query->sort($configuration['sort']['field'], $configuration['sort']['direction']);
    }

    return $query;
  }

  /**
   * Check if user has create/update permission in protocol.
   */
  protected function canApplyProtocol($entity) {
    $membership = Og::getMembership($entity, $this->currentUser);
    return $membership ? $membership->hasPermission('apply protocol') : FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->getConfiguration()['target_type'];

    $query = $this->buildProtocolQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      if ($this->canApplyProtocol($entity)) {
        $bundle = $entity->bundle();
        $protocolLabel = $this->entityRepository->getTranslationFromContext($entity)->label();
        $options[$bundle][$entity_id] = Html::escape($protocolLabel ?? '');
      }
    }

    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public function countReferenceableEntities($match = NULL, $match_operator = 'CONTAINS') {
    return 1;
  }

  /**
   * {@inheritDoc}
   */
  public function validateReferenceableEntities(array $ids) {
    return [1];
  }

}
