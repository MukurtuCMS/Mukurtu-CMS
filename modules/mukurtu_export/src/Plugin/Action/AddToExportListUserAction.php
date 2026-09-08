<?php

namespace Drupal\mukurtu_export\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stages selected user accounts for the "Add to export list" confirm form.
 *
 * /admin/people uses core's own user_bulk_form Views field, not the
 * views_bulk_operations contrib module the node/media "manage all content"
 * view uses for AddToExportListAction, so that action's VBO-tempstore-based
 * approach can't be reused here. This follows the plain ActionBase +
 * PrivateTempStoreFactory pattern already used by
 * MukurtuManageCommunityRolesAction for the same reason.
 *
 * @Action(
 *   id = "mukurtu_export_add_to_list_user_action",
 *   label = @Translation("Add to export list"),
 *   type = "user",
 *   confirm_form_route_name = "mukurtu_export.add_users_to_list",
 * )
 */
class AddToExportListUserAction extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The private tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Constructs an AddToExportListUserAction object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PrivateTempStoreFactory $temp_store_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tempStoreFactory = $temp_store_factory;
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
    );
  }

  /**
   * {@inheritdoc}
   *
   * Stages the selected user IDs in tempstore so the confirm form can load
   * them. The actual list assignment is made by ExportListAddUsersForm.
   */
  public function executeMultiple(array $entities) {
    $uids = array_map(fn($account) => $account->id(), $entities);
    $this->tempStoreFactory
      ->get('mukurtu_export.add_users_to_list')
      ->set('uids', array_values($uids));
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
    $result = $account && $account->hasPermission('access mukurtu export')
      ? AccessResult::allowed()
      : AccessResult::forbidden();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
