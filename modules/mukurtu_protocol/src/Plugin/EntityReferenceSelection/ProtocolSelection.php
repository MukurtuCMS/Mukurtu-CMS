<?php

namespace Drupal\mukurtu_protocol\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\og\Og;
use Drupal\og\OgRoleManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an entity reference selection for protocols.
 *
 * @EntityReferenceSelection(
 *   id = "default:protocol",
 *   label = @Translation("Protocol Selection for Protocol Control"),
 *   entity_types = {
 *     "protocol"
 *   },
 *   group = "default",
 *   weight = 1
 * )
 */
class ProtocolSelection extends DefaultSelection {

  /**
   * The OG role manager.
   *
   * @var \Drupal\og\OgRoleManagerInterface
   */
  protected $ogRoleManager;

  /**
   * Constructs a new ProtocolSelection object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\og\OgRoleManagerInterface $ogRoleManager
   *   The OG role manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, EntityFieldManagerInterface $entity_field_manager, EntityRepositoryInterface $entity_repository, OgRoleManagerInterface $ogRoleManager, ?EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user, $entity_field_manager, $entity_type_bundle_info, $entity_repository);
    $this->ogRoleManager = $ogRoleManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository'),
      $container->get('og.role_manager')
    );
  }

  /**
   * Builds an EntityQuery to get referenceable protocols.
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
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    // Look-up what OG roles can apply protocols.
    $roles = $this->ogRoleManager->getRolesByPermissions(['apply protocol']);
    $role_ids = array_keys($roles);

    // Check if the user has any of those roles.
    $memberships = Og::getMemberships($this->currentUser);
    $protocols = [];
    foreach ($memberships as $membership) {
      if ($membership->getGroupEntityType() !== 'protocol') {
        continue;
      }

      foreach ($role_ids as $role_id) {
        if ($membership->hasRole($role_id)) {
          $protocols[$membership->getGroupId()] = $membership->getGroupId();
        }
      }
    }

    // Build a normal entity query to handle the matching.
    $configuration = $this->getConfiguration();
    $target_type = $configuration['target_type'];
    $entity_type = $this->entityTypeManager->getDefinition($target_type);

    $query = $this->entityTypeManager->getStorage($target_type)->getQuery();
    $query->accessCheck(TRUE);

    // Restrict our results to protocols with the required memberships.
    if ($protocols) {
      $query->condition('id', $protocols, 'IN');
    }

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

}
