<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\Core\Access\AccessResult;

/**
 * VBO for managing user memberships/roles in protocols.
 *
 * @Action(
 *   id = "mukurtu_set_protocol_membership_action",
 *   label = @Translation("Set protocol membership and roles"),
 *   type = "user",
 *   confirm = TRUE,
 *   requirements = {
 *     "_custom_access" = TRUE,
 *   },
 * )
 */
class MukurtuSetProtocolMembershipAction extends ViewsBulkOperationsActionBase implements PluginFormInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity) {
      return;
    }

    // Load the protocol we are altering memberships for.
    $protocol_id = $this->configuration['protocol'];
    $protocol = \Drupal::entityTypeManager()->getStorage('node')->load($protocol_id);

    // Load all the possible roles for protocols.
    $roleManager = \Drupal::service('og.role_manager');
    $allProtocolRoles = $roleManager->getRolesByBundle($protocol->getEntityTypeId(), $protocol->bundle());

    // Build the list of roles beyond member/non-member.
    $member_roles = [];
    foreach ($this->configuration as $key => $value) {
      if ($key == 'protocol' || $key == 'node-protocol-non-member') {
        continue;
      }

      if ($value && isset($allProtocolRoles[$key])) {
        $member_roles[] = $allProtocolRoles[$key];
      }
    }

    // Add the user to the correct protocol/roles.
    $mukurtuMembershipManager = \Drupal::service('mukurtu_protocol.membership_manager');

    // First handle member/non-member.
    if ($this->configuration['node-protocol-member'] === 0 || $this->configuration['node-protocol-non-member'] !== 0) {
      $mukurtuMembershipManager->removeMember($protocol, $entity);
      return $this->t('Revoked membership for protocol @protocol', ['@protocol' => $protocol->getTitle()]);
    } else {
      // Add as a member.
      $mukurtuMembershipManager->addMember($protocol, $entity);

      // Add in the additional roles, beyond "member".
      $membership = Og::getMembership($protocol, $entity, OgMembershipInterface::ALL_STATES);
      $membership->setRoles($member_roles);
      $membership->save();
      return $this->t('Granted membership and roles for protocol @protocol', ['@protocol' => $protocol->getTitle()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityTypeId() === 'user') {
      // Load the protocol we are altering memberships for.
      $protocol_id = $this->configuration['protocol'];
      $protocol = \Drupal::entityTypeManager()->getStorage('node')->load($protocol_id);
      $membership = Og::getMembership($protocol, $account, OgMembershipInterface::ALL_STATES);

      // Check if user can modify this protocol's membership.
      if ($membership && ($membership->hasPermission('administer group') || $membership->hasPermission('manage members'))) {
        return $return_as_object ? TRUE : AccessResult::allowed();
      }
    }

    return $return_as_object ? FALSE : AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public static function customAccess(AccountInterface $account = NULL, ViewExecutable $view) {
    // Get the OG Memberships for account.
    $memberships = Og::getMemberships($account);

    // User needs permission to manage membership for at least one protocol
    // to use this VBO.
    foreach ($memberships as $membership) {
      if ($membership->getGroupBundle() != 'protocol') {
        continue;
      }

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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $currentUser = User::load(\Drupal::currentUser()->id());

    // Get protocol roles.
    $role_manager = \Drupal::service('og.role_manager');
    $roles = $role_manager->getRolesByBundle('node', 'protocol');

    // Get the protocols the user has access to modify memberships for.
    $protocols = [];
    $memberships = Og::getMemberships($currentUser);
    foreach ($memberships as $membership) {
      if ($membership->getGroupBundle() != 'protocol') {
        continue;
      }

      if ($membership->hasPermission('administer group') || $membership->hasPermission('manage members')) {
        $group = $membership->getGroup();
        if ($group) {
          $protocols[$group->id()] = $group->getTitle();
        }
      }
    }

    $form['protocol'] = [
      '#title' => t('Protocol'),
      '#description' => t('Select which protocol you want to alter membership & roles for.'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $protocols,
    ];

    $form['membership'] = [
      '#type' => 'radios',
      '#title' => $this->t('Membership'),
      '#description' => t('Protocol membership and roles will be set to exactly what is specified here. Previous user roles for this protocol will not be retained.'),
      '#required' => TRUE,
      '#options' => [
        'node-protocol-non-member' => $this->t('Non-member'),
        'node-protocol-member' => $this->t('Member'),
      ],
      '#default_value' => 'node-protocol-non-member',
    ];

    $form['roles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Roles'),
      '#states' => [
        'visible' => [
          ':input[name="membership"]' => ['value' => 'node-protocol-member'],
        ],
      ],
    ];

    foreach ($roles as $role) {
      if ($role->id() == 'node-protocol-member' || $role->id() == 'node-protocol-non-member') {
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
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $role_manager = \Drupal::service('og.role_manager');
    $roles = $role_manager->getRolesByBundle('node', 'protocol');

    // Handling member/non-member separately, remove them from this list.
    unset($roles['node-protocol-member']);
    unset($roles['node-protocol-non-member']);

    // Set the defaults to 0, we only get one value from the radio.
    $this->configuration['node-protocol-member'] = 0;
    $this->configuration['node-protocol-non-member'] = 0;

    // Get the member/non-member from the radio.
    $membership = $form_state->getValue('membership');
    $this->configuration[$membership] = 1;

    // Protocol ID.
    $this->configuration['protocol'] = $form_state->getValue('protocol');

    // Roles beyond member/non-member.
    foreach ($roles as $role) {
      $this->configuration[$role->id()] = $form_state->getValue($role->id());
    }
  }

}
