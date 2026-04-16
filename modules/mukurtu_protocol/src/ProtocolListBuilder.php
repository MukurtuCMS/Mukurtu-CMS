<?php

namespace Drupal\mukurtu_protocol;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Protocol entities.
 *
 * @ingroup mukurtu_protocol
 */
class ProtocolListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $entity_type_manager = \Drupal::entityTypeManager();

    // Load all accessible communities, keyed by ID.
    $community_ids = $entity_type_manager->getStorage('community')
      ->getQuery()
      ->accessCheck(TRUE)
      ->sort('name')
      ->execute();
    $communities = $entity_type_manager->getStorage('community')->loadMultiple($community_ids);

    // Load all accessible protocols and index by community ID.
    $protocol_ids = $entity_type_manager->getStorage('protocol')
      ->getQuery()
      ->accessCheck(TRUE)
      ->sort('name')
      ->execute();
    $all_protocols = $entity_type_manager->getStorage('protocol')->loadMultiple($protocol_ids);

    $protocols_by_community = [];
    $orphan_protocols = [];
    foreach ($all_protocols as $protocol) {
      $protocol_communities = $protocol->getCommunities();
      if (empty($protocol_communities)) {
        $orphan_protocols[$protocol->id()] = $protocol;
      }
      else {
        foreach ($protocol_communities as $community) {
          $protocols_by_community[$community->id()][$protocol->id()] = $protocol;
        }
      }
    }

    $rows = [];

    // Walk only top-level communities (no parent), then recurse into children.
    foreach ($communities as $community) {
      if (!$community->getParentCommunity()) {
        $this->addCommunityRows($community, $communities, $protocols_by_community, $rows, 0);
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
  protected function addCommunityRows(EntityInterface $community, array $all_communities, array $protocols_by_community, array &$rows, int $depth) {
    $access_manager = \Drupal::service('access_manager');
    $community_protocols = $protocols_by_community[$community->id()] ?? [];
    $can_manage = $access_manager->checkNamedRoute('mukurtu_protocol.manage_community', ['group' => $community->id()]);

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
        $this->addCommunityRows($child, $all_communities, $protocols_by_community, $rows, $depth + 1);
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

    $access_manager = \Drupal::service('access_manager');
    $items = [];

    foreach ($protocols as $protocol) {
      if ($access_manager->checkNamedRoute('mukurtu_protocol.manage_protocol', ['group' => $protocol->id()])) {
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
    $access_manager = \Drupal::service('access_manager');
    $operations = [];

    if ($access_manager->checkNamedRoute('entity.community.canonical', ['community' => $community->id()])) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => 10,
        'url' => Url::fromRoute('entity.community.canonical', ['community' => $community->id()]),
      ];
    }
    if ($access_manager->checkNamedRoute('mukurtu_protocol.manage_community', ['group' => $community->id()])) {
      $operations['manage'] = [
        'title' => $this->t('Manage Community'),
        'weight' => 20,
        'url' => Url::fromRoute('mukurtu_protocol.manage_community', ['group' => $community->id()]),
      ];
    }
    if ($access_manager->checkNamedRoute('mukurtu_protocol.community_members_list', ['group' => $community->id()])) {
      $operations['members'] = [
        'title' => $this->t('Manage Members'),
        'weight' => 30,
        'url' => Url::fromRoute('mukurtu_protocol.community_members_list', ['group' => $community->id()]),
      ];
    }
    if ($access_manager->checkNamedRoute('mukurtu_protocol.community_add_membership', ['group' => $community->id()])) {
      $operations['add-member'] = [
        'title' => $this->t('Add Member'),
        'weight' => 40,
        'url' => Url::fromRoute('mukurtu_protocol.community_add_membership', ['group' => $community->id()]),
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
    $access_manager = \Drupal::service('access_manager');
    $operations = [];

    if ($access_manager->checkNamedRoute('entity.protocol.canonical', ['protocol' => $protocol->id()])) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => 10,
        'url' => Url::fromRoute('entity.protocol.canonical', ['protocol' => $protocol->id()]),
      ];
    }
    if ($access_manager->checkNamedRoute('mukurtu_protocol.manage_protocol', ['group' => $protocol->id()])) {
      $operations['manage'] = [
        'title' => $this->t('Manage Protocol'),
        'weight' => 20,
        'url' => Url::fromRoute('mukurtu_protocol.manage_protocol', ['group' => $protocol->id()]),
      ];
    }
    if ($access_manager->checkNamedRoute('mukurtu_protocol.protocol_members_list', ['group' => $protocol->id()])) {
      $operations['members'] = [
        'title' => $this->t('Manage Members'),
        'weight' => 30,
        'url' => Url::fromRoute('mukurtu_protocol.protocol_members_list', ['group' => $protocol->id()]),
      ];
    }
    if ($access_manager->checkNamedRoute('mukurtu_protocol.protocol_add_membership', ['group' => $protocol->id()])) {
      $operations['add-member'] = [
        'title' => $this->t('Add Member'),
        'weight' => 40,
        'url' => Url::fromRoute('mukurtu_protocol.protocol_add_membership', ['group' => $protocol->id()]),
      ];
    }

    return [
      '#type' => 'operations',
      '#links' => $operations,
    ];
  }

}
