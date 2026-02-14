<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\og\Og;
use Drupal\user\Entity\User;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\views\ViewExecutable;

/**
 * VBO for removing users from a protocol.
 *
 * @Action(
 *   id = "mukurtu_remove_protocol_membership_action",
 *   label = @Translation("Remove user(s) from a cultural protocol"),
 *   type = "user",
 *   confirm = TRUE,
 *   requirements = {
 *     "_custom_access" = TRUE,
 *   },
 * )
 */
class MukurtuRemoveProtocolMembershipAction extends ViewsBulkOperationsActionBase implements PluginFormInterface
{

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL)
  {
    if (!$entity || !($entity instanceof User)) {
      return;
    }
    /** @var \Drupal\user\Entity\User $entity */

    // Load the protocol we are altering memberships for.
    $protocol_id = $this->configuration['protocol'];
    /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
    $protocol = \Drupal::entityTypeManager()->getStorage('protocol')->load($protocol_id);

    // Set membership and roles.
    $protocol->removeMember($entity);

    return $this->t('Removed user @user from cultural protocol @protocol', ['@user' => $entity->getDisplayName(), '@protocol' => $protocol->getName()]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE)
  {
    if ($object instanceof User) {
      // Load the protocol we are altering memberships for.
      /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
      $protocol = \Drupal::entityTypeManager()->getStorage('protocol')->load($this->configuration['protocol']);

      // Don't let a user remove themself. Probably a mistake for a batch operation.
      if ($account->id() === $object->id()) {
        return $return_as_object ? AccessResult::forbidden() : FALSE;
      }

      // Check if target user is already a member.
      if (!$protocol->getMembership($object)) {
        return $return_as_object ? AccessResult::forbidden() : FALSE;
      }

      // Check if requesting user can modify this protocol's membership.
      $membership = $protocol->getMembership($account);
      if ($membership && ($membership->hasPermission('administer group') || $membership->hasPermission('manage members'))) {
        return $return_as_object ? AccessResult::allowed() : TRUE;
      }
    }

    return $return_as_object ? AccessResult::forbidden() : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function customAccess(AccountInterface $account, ViewExecutable $view): bool
  {
    // Get the OG Memberships for account.
    $memberships = array_filter(Og::getMemberships($account), fn ($m) => $m->getGroupBundle() === 'protocol');

    // User needs permission to manage membership for at least one protocol
    // to use this VBO.
    foreach ($memberships as $membership) {
      // Found one protocol where they have sufficent access, no need to check
      // the rest.
      if ($membership->hasPermission('administer group') || $membership->hasPermission('manage members')) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state)
  {
    $currentUser = User::load(\Drupal::currentUser()->id());

    // Get the communities the user has access to modify memberships for.
    $protocols = [];
    $protocolMemberships = array_filter(Og::getMemberships($currentUser), fn ($m) => $m->getGroupBundle() === 'protocol');
    $managerMemberships = array_filter($protocolMemberships, fn ($m) => $m->hasPermission('administer group') || $m->hasPermission('manage members'));
    $managerCommunities = array_filter(array_map(fn ($m) => $m->getGroup(), $managerMemberships));

    /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
    foreach ($managerCommunities as $protocol) {
      $protocols[$protocol->id()] = $protocol->getName();
    }

    $form['protocol'] = [
      '#title' => t('Cultural Protocol'),
      '#description' => t('Select which cultural protocol you want to alter membership & roles for.'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $protocols,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void
  {
    $this->configuration['protocol'] = $form_state->getValue('protocol');
  }
}
