<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\og\OgAccessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bulk-edits roles for selected protocol members.
 *
 * Stages the selected membership IDs in a private tempstore, then redirects
 * to a per-user role management form via confirm_form_route_name.
 *
 * @Action(
 *   id = "mukurtu_manage_protocol_roles_action",
 *   label = @Translation("Manage roles"),
 *   type = "og_membership",
 *   confirm_form_route_name = "mukurtu_protocol.protocol_manage_roles_form",
 * )
 */
class MukurtuManageProtocolRolesAction extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The private tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The OG access service.
   *
   * @var \Drupal\og\OgAccessInterface
   */
  protected $ogAccess;

  /**
   * Constructs a MukurtuManageProtocolRolesAction object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PrivateTempStoreFactory $temp_store_factory, OgAccessInterface $og_access) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tempStoreFactory = $temp_store_factory;
    $this->ogAccess = $og_access;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tempstore.private'),
      $container->get('og.access')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Stores selected membership IDs in tempstore so the confirm form can load
   * them. The actual role changes are made by ManageProtocolBulkRolesForm.
   */
  public function executeMultiple(array $entities) {
    $ids = array_map(fn($m) => $m->id(), $entities);
    $this->tempStoreFactory
      ->get('mukurtu_protocol.manage_protocol_roles')
      ->set('membership_ids', array_values($ids));
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    // Handled by executeMultiple().
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\og\Entity\OgMembership $object */
    if ($object->getGroupEntityType() !== 'protocol') {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }
    $access = $this->ogAccess->userAccess($object->getGroup(), 'manage members', $account);
    return $return_as_object ? $access : $access->isAllowed();
  }

}
