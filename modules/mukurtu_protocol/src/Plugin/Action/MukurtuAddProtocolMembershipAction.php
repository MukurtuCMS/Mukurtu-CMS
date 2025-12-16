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
 * VBO for adding users to a protocol.
 *
 * @Action(
 *   id = "mukurtu_add_protocol_membership_action",
 *   label = @Translation("Add user(s) to a cultural protocol"),
 *   type = "user",
 *   confirm = TRUE,
 *   requirements = {
 *     "_custom_access" = TRUE,
 *   },
 * )
 */
class MukurtuAddProtocolMembershipAction extends ViewsBulkOperationsActionBase implements PluginFormInterface
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
    $protocol->addMember($entity, array_keys(array_filter($this->configuration['roles'])));

    return $this->t('Added user @user to cultural protocol @protocol', ['@user' => $entity->getDisplayName(), '@protocol' => $protocol->getName()]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE)
  {
    if ($object instanceof User) {
      // Load the protocol we are altering memberships for.
      /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
      $protocol = \Drupal::entityTypeManager()->getStorage('protocol')->load($this->configuration['protocol']);

      // Check if requesting user is a member of at least one owning community.
      $communities = $protocol->getCommunities();
      $hasMembership = FALSE;

      foreach ($communities as $community) {
        if ($community->getMembership($object)) {
          $hasMembership = TRUE;
          break;
        }
      }

      if (!$hasMembership) {
        return $return_as_object ? AccessResult::forbidden() : FALSE;
      }

      // Check if target user is already a member.
      if ($protocol->getMembership($object)) {
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

    // Get protocol roles.
    $role_manager = \Drupal::service('og.role_manager');
    $roles = $role_manager->getRolesByBundle('protocol', 'protocol');

    // Get the protocols the user has access to modify memberships for.
    $protocols = [];
    $protocolMemberships = array_filter(Og::getMemberships($currentUser), fn ($m) => $m->getGroupBundle() === 'protocol');
    $managerMemberships = array_filter($protocolMemberships, fn ($m) => $m->hasPermission('administer group') || $m->hasPermission('manage members'));
    $managerProtocols = array_filter(array_map(fn ($m) => $m->getGroup(), $managerMemberships));

    /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
    foreach ($managerProtocols as $protocol) {
      $protocols[$protocol->id()] = $protocol->getName();
    }

    $form['protocol'] = [
      '#title' => t('Cultural Protocol'),
      '#description' => t('Select which cultural protocol you want to alter membership & roles for.'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $protocols,
    ];

    $form['roles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Roles'),
      '#states' => [
        'visible' => [
          ':input[name="membership"]' => ['value' => 'protocol-protocol-member'],
        ]
      ]
    ];

    foreach ($roles as $role) {
      if ($role->id() == 'protocol-protocol-member' || $role->id() == 'protocol-protocol-non-member') {
        continue;
      }

      $form['roles'][$role->id()] = [
        '#type' => 'checkbox',
        '#title' => $role->getLabel(),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void
  {
    $role_manager = \Drupal::service('og.role_manager');
    $roles = $role_manager->getRolesByBundle('protocol', 'protocol');

    // Handling member/non-member separately, remove them from this list.
    unset($roles['protocol-protocol-member']);
    unset($roles['protocol-protocol-non-member']);

    // Protocol ID.
    $this->configuration['protocol'] = $form_state->getValue('protocol');

    // Roles beyond member/non-member.
    foreach ($roles as $role) {
      $this->configuration['roles'][str_replace('protocol-protocol-', '', $role->id())] = $form_state->getValue($role->id());
    }
  }
}
