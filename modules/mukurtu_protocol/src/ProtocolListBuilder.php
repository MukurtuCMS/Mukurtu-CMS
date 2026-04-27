<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of Protocol entities.
 *
 * @ingroup mukurtu_protocol
 */
class ProtocolListBuilder extends EntityListBuilder {

  protected AccessManagerInterface $accessManager;

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, AccessManagerInterface $access_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $storage);
    $this->accessManager = $access_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('access_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Load all accessible communities, keyed by ID.
    $community_ids = $this->entityTypeManager->getStorage('community')
      ->getQuery()
      ->accessCheck(TRUE)
      ->sort('name')
      ->execute();
    $communities = $this->entityTypeManager->getStorage('community')->loadMultiple($community_ids);

    // Load all accessible protocols and index by community ID using field
    // values (IDs only) to avoid re-loading already-loaded community entities.
    $protocol_ids = $this->entityTypeManager->getStorage('protocol')
      ->getQuery()
      ->accessCheck(TRUE)
      ->sort('name')
      ->execute();
    $all_protocols = $this->entityTypeManager->getStorage('protocol')->loadMultiple($protocol_ids);

    $protocols_by_community = [];
    $orphan_protocols = [];
    foreach ($all_protocols as $protocol) {
      $community_field_values = $protocol->get('field_communities')->getValue();
      if (empty($community_field_values)) {
        $orphan_protocols[$protocol->id()] = $protocol;
      }
      else {
        foreach ($community_field_values as $value) {
          $protocols_by_community[$value['target_id']][$protocol->id()] = $protocol;
        }
      }
    }

    $rows = [];
    $visited = [];

    // Walk only top-level communities (no parent), then recurse into children.
    foreach ($communities as $community) {
      if (!$community->getParentCommunity()) {
        $this->addCommunityRows($community, $communities, $protocols_by_community, $rows, 0, $visited);
      }
    }

    // Protocols with no community.
    if (!empty($orphan_protocols)) {
      $rows[] = [
        'data' => [
          ['data' => ['#markup' => $this->t('(No community)')]],
          ['data' => $this->buildProtocolList($orphan_protocols)],
        ],
        'class' => ['community-row', 'depth-0'],
        'no_striping' => TRUE,
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Community'),
        $this->t('Cultural Protocols'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No protocols found.'),
      '#cache' => [
        'contexts' => [
          'user.roles',
          'og_group_context',
          'og_membership_state',
          'og_role',
        ],
      ],
      '#attached' => ['library' => ['mukurtu_protocol/protocol-list']],
    ];
  }

  /**
   * Recursively adds a community row and then rows for its child communities.
   */
  protected function addCommunityRows(EntityInterface $community, array $all_communities, array $protocols_by_community, array &$rows, int $depth, array &$visited) {
    // Guard against circular community references and excessive nesting.
    if (isset($visited[$community->id()]) || $depth > 10) {
      return;
    }
    $visited[$community->id()] = TRUE;

    $community_protocols = $protocols_by_community[$community->id()] ?? [];
    $can_manage = $this->accessManager->checkNamedRoute('mukurtu_protocol.manage_community', ['group' => $community->id()]);

    if (!$can_manage && empty($community_protocols)) {
      return;
    }

    if ($can_manage) {
      $label = Link::createFromRoute(
        $community->label(),
        'mukurtu_protocol.manage_community',
        ['group' => $community->id()]
      )->toRenderable();
    }
    else {
      $label = ['#markup' => $community->label()];
    }

    // Accessibility: add visually-hidden prefix so screen readers convey depth.
    if ($depth > 0) {
      $label = [
        'prefix' => [
          '#markup' => '<span class="visually-hidden">' . $this->t('Sub-community') . ': </span>',
        ],
        'label' => $label,
      ];
    }

    $community_cell = [
      '#type' => 'container',
      '#attributes' => ['class' => ['name-ops-wrapper']],
      'name' => $label,
      'operations' => $this->buildCommunityOperations($community),
    ];

    $rows[] = [
      'data' => [
        ['data' => $community_cell],
        ['data' => $this->buildProtocolList($community_protocols)],
      ],
      'class' => ['community-row', 'depth-' . $depth],
      'no_striping' => TRUE,
    ];

    // Recurse into accessible child communities.
    foreach ($community->getChildCommunities() as $child) {
      if (isset($all_communities[$child->id()])) {
        $this->addCommunityRows($child, $all_communities, $protocols_by_community, $rows, $depth + 1, $visited);
      }
    }
  }

  /**
   * Builds a render array listing protocol names with inline operations.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $protocols
   *   The protocols to list.
   */
  protected function buildProtocolList(array $protocols) {
    if (empty($protocols)) {
      return [];
    }

    $items = [];

    foreach ($protocols as $protocol) {
      if ($this->accessManager->checkNamedRoute('mukurtu_protocol.manage_protocol', ['group' => $protocol->id()])) {
        $name = Link::createFromRoute(
          $protocol->label(),
          'mukurtu_protocol.manage_protocol',
          ['group' => $protocol->id()]
        )->toRenderable();
      }
      else {
        $name = ['#markup' => $protocol->label()];
      }

      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['name-ops-wrapper']],
        'name' => $name,
        'operations' => $this->buildProtocolOperations($protocol),
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#list_type' => 'ul',
      '#attributes' => ['class' => ['protocol-list']],
    ];
  }

  /**
   * Builds the operations render array for a community entity.
   */
  protected function buildCommunityOperations(EntityInterface $community) {
    $label = $community->label();
    $operations = [];

    if ($this->accessManager->checkNamedRoute('entity.community.canonical', ['community' => $community->id()])) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => 10,
        'url' => Url::fromRoute('entity.community.canonical', ['community' => $community->id()]),
        'attributes' => ['aria-label' => $this->t('View @name', ['@name' => $label])],
      ];
    }
    if ($this->accessManager->checkNamedRoute('mukurtu_protocol.manage_community', ['group' => $community->id()])) {
      $operations['manage'] = [
        'title' => $this->t('Manage Community'),
        'weight' => 20,
        'url' => Url::fromRoute('mukurtu_protocol.manage_community', ['group' => $community->id()]),
        'attributes' => ['aria-label' => $this->t('Manage Community: @name', ['@name' => $label])],
      ];
    }
    if ($this->accessManager->checkNamedRoute('mukurtu_protocol.community_members_list', ['group' => $community->id()])) {
      $operations['members'] = [
        'title' => $this->t('Manage Members'),
        'weight' => 30,
        'url' => Url::fromRoute('mukurtu_protocol.community_members_list', ['group' => $community->id()]),
        'attributes' => ['aria-label' => $this->t('Manage Members of @name', ['@name' => $label])],
      ];
    }
    if ($this->accessManager->checkNamedRoute('mukurtu_protocol.community_add_membership', ['group' => $community->id()])) {
      $operations['add-member'] = [
        'title' => $this->t('Add Member'),
        'weight' => 40,
        'url' => Url::fromRoute('mukurtu_protocol.community_add_membership', ['group' => $community->id()]),
        'attributes' => ['aria-label' => $this->t('Add Member to @name', ['@name' => $label])],
      ];
    }

    return [
      '#type' => 'operations',
      '#links' => $operations,
    ];
  }

  /**
   * Builds the operations render array for a protocol entity.
   */
  protected function buildProtocolOperations(EntityInterface $protocol) {
    $label = $protocol->label();
    $operations = [];

    if ($this->accessManager->checkNamedRoute('entity.protocol.canonical', ['protocol' => $protocol->id()])) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => 10,
        'url' => Url::fromRoute('entity.protocol.canonical', ['protocol' => $protocol->id()]),
        'attributes' => ['aria-label' => $this->t('View @name', ['@name' => $label])],
      ];
    }
    if ($this->accessManager->checkNamedRoute('mukurtu_protocol.manage_protocol', ['group' => $protocol->id()])) {
      $operations['manage'] = [
        'title' => $this->t('Manage Protocol'),
        'weight' => 20,
        'url' => Url::fromRoute('mukurtu_protocol.manage_protocol', ['group' => $protocol->id()]),
        'attributes' => ['aria-label' => $this->t('Manage Protocol: @name', ['@name' => $label])],
      ];
    }
    if ($this->accessManager->checkNamedRoute('mukurtu_protocol.protocol_members_list', ['group' => $protocol->id()])) {
      $operations['members'] = [
        'title' => $this->t('Manage Members'),
        'weight' => 30,
        'url' => Url::fromRoute('mukurtu_protocol.protocol_members_list', ['group' => $protocol->id()]),
        'attributes' => ['aria-label' => $this->t('Manage Members of @name', ['@name' => $label])],
      ];
    }
    if ($this->accessManager->checkNamedRoute('mukurtu_protocol.protocol_add_membership', ['group' => $protocol->id()])) {
      $operations['add-member'] = [
        'title' => $this->t('Add Member'),
        'weight' => 40,
        'url' => Url::fromRoute('mukurtu_protocol.protocol_add_membership', ['group' => $protocol->id()]),
        'attributes' => ['aria-label' => $this->t('Add Member to @name', ['@name' => $label])],
      ];
    }

    return [
      '#type' => 'operations',
      '#links' => $operations,
    ];
  }

}
