<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\Entity\OgMembership;
use Drupal\og\OgAccessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deletes a group membership, blocking removal when community/protocol rules apply.
 *
 * Replaces og_membership_delete_action to respect Mukurtu's entity access
 * check that prevents removing a community member who still belongs to a child
 * protocol of that community.
 *
 * @Action(
 *   id = "mukurtu_delete_og_membership_action",
 *   label = @Translation("Delete the selected membership(s)"),
 *   type = "og_membership"
 * )
 */
class MukurtuDeleteOgMembershipAction extends ActionBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The OG access service.
   *
   * @var \Drupal\og\OgAccessInterface
   */
  protected $ogAccess;

  /**
   * Constructs a MukurtuDeleteOgMembershipAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, OgAccessInterface $og_access) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('og.access')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?OgMembership $membership = NULL) {
    if (!$membership) {
      return;
    }

    // Respect entity access — this catches the community/protocol membership
    // guard in mukurtu_protocol_entity_access() that bulk delete would otherwise
    // bypass by calling $membership->delete() directly.
    if (!$membership->access('delete')) {
      $member = $membership->getOwner();
      \Drupal::messenger()->addWarning($this->t('Could not remove %user from the community because they still have protocol roles. Remove them from all protocols first.', ['%user' => $member->getDisplayName()]));
      return;
    }

    $membership->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\og\Entity\OgMembership $object */
    $access = $this->ogAccess->userAccess($object->getGroup(), 'manage members', $account);

    return $return_as_object ? $access : $access->isAllowed();
  }

}
