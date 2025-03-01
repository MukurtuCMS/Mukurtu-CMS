<?php

namespace Drupal\mukurtu_core;

use Drupal\Core\Entity\EntityInterface;
use Drupal\user\RoleInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\user\Entity\Role;
use Drupal\og\Og;

class MukurtuUserListBuilder extends \Drupal\user\UserListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'username' => [
        'data' => $this->t('Username'),
        'field' => 'name',
        'specifier' => 'name',
      ],
      'field_display_name' => [
        'data' => $this->t('Display Name'),
        'field' => 'field_display_name',
        'specifier' => 'field_display_name',
      ],
      'status' => [
        'data' => $this->t('Status'),
        'field' => 'status',
        'specifier' => 'status',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'roles' => [
        'data' => $this->t('Roles'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'communities' => [
        'data' => $this->t('Communities'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'member_for' => [
        'data' => $this->t('Member for'),
        'field' => 'created',
        'specifier' => 'created',
        'sort' => 'desc',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'access' => [
        'data' => $this->t('Last access'),
        'field' => 'access',
        'specifier' => 'access',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['username']['data'] = [
      '#theme' => 'username',
      '#account' => $entity,
    ];
    $row['field_display_name']['data']['#markup'] = $entity->get('field_display_name')->value;
    $row['status'] = $entity->isActive() ? $this->t('active') : $this->t('blocked');

    $roles = Role::loadMultiple($entity->getRoles());
    unset($roles[RoleInterface::ANONYMOUS_ID]);
    unset($roles[RoleInterface::AUTHENTICATED_ID]);
    $users_roles = array_map(fn(RoleInterface $role) => $role->label(), $roles);
    asort($users_roles);
    $row['roles']['data'] = [
      '#theme' => 'item_list',
      '#items' => $users_roles,
    ];
    $options = [
      'return_as_object' => TRUE,
    ];
    $row['communities']['data'] = [
      '#theme' => 'item_list',
      '#items' => $this->getUserCommunities($entity),
    ];
    $row['member_for']['data'] = $this->dateFormatter->formatTimeDiffSince($entity->getCreatedTime(), $options)->toRenderable();
    $last_access = $this->dateFormatter->formatTimeDiffSince($entity->getLastAccessedTime(), $options);

    if ($entity->getLastAccessedTime()) {
      $row['access']['data']['#markup'] = $last_access->getString();
      CacheableMetadata::createFromObject($last_access)->applyTo($row['access']['data']);
    }
    else {
      $row['access']['data']['#markup'] = t('never');
    }
    return $row + parent::buildRow($entity);
  }

  protected function getUserCommunities($account) {
    $memberships = Og::getMemberships($account);

    $communities_only = function ($e) {
      if ($e->get('entity_bundle')->value == 'community') {
        return TRUE;
      }
      return FALSE;
    };

    $memberships = array_filter($memberships, $communities_only);
    $communities = [];
    foreach ($memberships as $membership) {
      $community = $membership->getGroup();
      $communities[$community->id()] = $community->getTitle();
    }
    return $communities;
  }
}
