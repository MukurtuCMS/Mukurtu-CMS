<?php

declare(strict_types = 1);

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Url;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\og\Og;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a class to build a listing of og_role entities.
 *
 * @see \Drupal\og\Entity\OgRole
 */
class MukurtuOgRoleListBuilder extends DraggableListBuilder {

  /**
   * The group entity type.
   *
   * @var string
   */
  protected $groupType;

  /**
   * The group entity bundle id.
   *
   * @var string
   */
  protected $groupBundle;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('current_route_match')
    );
  }

  /**
   * Constructs a new OgRoleListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route matcher.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, RouteMatchInterface $route_match) {
    parent::__construct($entity_type, $storage);

    if ($route_match->getRouteName() == 'mukurtu_protocol.community_roles') {
      $this->groupType = 'community';
      $this->groupBundle = 'community';
    }

    if ($route_match->getRouteName() == 'mukurtu_protocol.protocol_roles') {
      $this->groupType = 'protocol';
      $this->groupBundle = 'protocol';
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->condition('group_type', $this->groupType, '=')
      ->condition('group_bundle', $this->groupBundle, '=')
      ->accessCheck(TRUE)
      ->sort($this->entityType->getKey('weight'));

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return array_values($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_og_roles_admin';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $role) {
    $operations = parent::getDefaultOperations($role);

    // @todo If ($entity->hasLinkTemplate('edit-permissions-form')).
    $operations['permissions'] = [
      'title' => $this->t('Edit permissions'),
      'weight' => 20,
      'url' => Url::fromRoute('og_ui.permissions_edit_form', [
        'entity_type_id' => $this->groupType,
        'bundle_id' => $this->groupBundle,
        'role_name' => $role->getName(),
      ]),
    ];

    if ($role->isLocked()) {
      if (isset($operations['edit'])) {
        unset($operations['edit']);
      }

      if (isset($operations['delete'])) {
        unset($operations['delete']);
      }
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Return a 404 error when this is not a group.
    if (!Og::isGroup($this->groupType, $this->groupBundle)) {
      throw new NotFoundHttpException();
    }

    $build = parent::render();
    $build['entities']['#empty'] = $this->t('There are no OG roles available yet. <a href="@link">Add an OG role</a>.', [
      '@link' => Url::fromRoute('entity.og_role.add_form', [
        'entity_type_id' => $this->groupType,
        'bundle_id' => $this->groupBundle,
      ])->toString(),
    ]);

    return $build;
  }

}
