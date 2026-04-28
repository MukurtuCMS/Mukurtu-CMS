<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\og\Entity\OgMembership;
use Drupal\og\OgAccessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deletes a group membership, blocking removal when community/protocol rules apply.
 *
 * Wired in as the implementation for og_membership_delete_action via
 * hook_action_info_alter() so that bulk deletes on community member pages
 * respect the rule that a user cannot be removed from a community while they
 * still belong to one of its child protocols.
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

    $group = $membership->getGroup();

    // Prevent removing a community member who still belongs to a child protocol.
    if ($group instanceof CommunityInterface) {
      $member = $membership->getOwner();
      if ($member) {
        foreach ($group->getProtocols() as $protocol) {
          if ($protocol->getMembership($member)) {
            \Drupal::messenger()->addWarning($this->t('Could not remove %user from the community because they still have protocol roles. Remove them from all protocols first.', ['%user' => $member->getDisplayName()]));
            return;
          }
        }
      }
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
