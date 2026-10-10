<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\og\OgAccessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stages the selected group memberships for removal, pending confirmation.
 *
 * Wired in as the implementation for og_membership_delete_action by
 * mukurtu_protocol_action_info_alter(), which also gives the plugin a
 * confirm_form_route_name.
 *
 * This plugin does not delete anything. Views' bulk form runs the action and
 * then, because the definition carries a confirm_form_route_name, redirects to
 * that form; so the staging happens here and the removal happens in
 * MukurtuOgMembershipRemoveMultipleForm. Bulk member removal used to take
 * effect on the first click with no way back, which is what #2370 is about
 * (WCAG 2.1 3.3.4 Error Prevention).
 *
 * The staged value shape is core's (id => langcode => langcode), so that
 * anything written against core's delete-multiple convention reads here too.
 *
 * @see \Drupal\mukurtu_protocol\Form\MukurtuOgMembershipRemoveMultipleForm
 * @see \Drupal\Core\Action\Plugin\Action\DeleteAction
 */
class MukurtuDeleteOgMembershipAction extends ActionBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The tempstore collection shared with the confirm form.
   */
  public const TEMPSTORE_COLLECTION = 'mukurtu_protocol_member_removal_confirm';

  /**
   * The tempstore the staged selection is written to.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $tempStore;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The OG access service.
   *
   * @var \Drupal\og\OgAccessInterface
   */
  protected OgAccessInterface $ogAccess;

  /**
   * Constructs a MukurtuDeleteOgMembershipAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private tempstore factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PrivateTempStoreFactory $temp_store_factory, AccountInterface $current_user, OgAccessInterface $og_access) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tempStore = $temp_store_factory->get(self::TEMPSTORE_COLLECTION);
    $this->currentUser = $current_user;
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
      $container->get('current_user'),
      $container->get('og.access')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    /** @var \Drupal\og\OgMembershipInterface[] $entities */
    $selection = [];
    foreach ($entities as $membership) {
      $langcode = $membership->language()->getId();
      $selection[$membership->id()][$langcode] = $langcode;
    }
    $this->tempStore->set($this->tempStoreKey(), $selection);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    if ($object) {
      $this->executeMultiple([$object]);
    }
  }

  /**
   * The tempstore key holding the current user's staged selection.
   *
   * @return string
   *   The key, in the uid:entity_type_id form core's access check expects.
   */
  public function tempStoreKey(): string {
    return $this->currentUser->id() . ':' . $this->getPluginDefinition()['type'];
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    // Deliberately only asks whether this user may manage the group's
    // members, not whether this particular membership can be removed. Core's
    // bulk form drops anything this method refuses with an error that names
    // the entity by its label, and an og_membership has no label, so such an
    // error cannot even say who it is about. Staging the whole selection lets
    // the confirm form re-check each membership with entity access and show
    // the real reason, e.g. that the person still belongs to a protocol
    // within the community.
    $group = $object->getGroup();
    $access = $group
      ? $this->ogAccess->userAccess($group, 'manage members', $account)
      : AccessResult::forbidden();

    return $return_as_object ? $access : $access->isAllowed();
  }

}
