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
 * VBO for managing user memberships/roles in communities.
 *
 * @Action(
 *   id = "mukurtu_set_community_membership_action",
 *   label = @Translation("Set community membership and roles"),
 *   type = "user",
 *   confirm = TRUE,
 *   requirements = {
 *     "_custom_access" = TRUE,
 *   },
 * )
 */
class MukurtuSetCommunityMembershipAction extends ViewsBulkOperationsActionBase implements PluginFormInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity) {
      return;
    }

    // Load the community we are altering memberships for.
    $community_id = $this->configuration['community'];
    $community = \Drupal::entityTypeManager()->getStorage('node')->load($community_id);

    // Load all the possible roles for communities.
    $roleManager = \Drupal::service('og.role_manager');
    $allCommunityRoles = $roleManager->getRolesByBundle($community->getEntityTypeId(), $community->bundle());

    // Build the list of roles beyond member/non-member.
    $member_roles = [];
    foreach ($this->configuration as $key => $value) {
      if ($key == 'community' || $key == 'node-community-non-member') {
        continue;
      }

      if ($value && isset($allCommunityRoles[$key])) {
        $member_roles[] = $allCommunityRoles[$key];
      }
    }

    // Add the user to the correct community/roles.
    $mukurtuMembershipManager = \Drupal::service('mukurtu_protocol.membership_manager');

    // First handle member/non-member.
    if ($this->configuration['node-community-member'] === 0 || $this->configuration['node-community-non-member'] !== 0) {
      $mukurtuMembershipManager->removeMember($community, $entity);
      return $this->t('Revoked membership for community @community', ['@community' => $community->getTitle()]);
    } else {
      // Add as a member.
      $mukurtuMembershipManager->addMember($community, $entity);

      // Add in the additional roles, beyond "member".
      $membership = Og::getMembership($community, $entity, OgMembershipInterface::ALL_STATES);
      $membership->setRoles($member_roles);
      $membership->save();
      return $this->t('Granted membership and roles for community @community', ['@community' => $community->getTitle()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object->getEntityTypeId() === 'user') {
      // Load the community we are altering memberships for.
      $community_id = $this->configuration['community'];
      $community = \Drupal::entityTypeManager()->getStorage('node')->load($community_id);
      $membership = Og::getMembership($community, $account, OgMembershipInterface::ALL_STATES);

      // Check if user can modify this community's membership.
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

    // User needs permission to manage membership for at least one community
    // to use this VBO.
    foreach ($memberships as $membership) {
      if ($membership->getGroupBundle() != 'community') {
        continue;
      }

      // Found one community where they have sufficent access, no need to check
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

    // Get community roles.
    $role_manager = \Drupal::service('og.role_manager');
    $roles = $role_manager->getRolesByBundle('node', 'community');

    // Get the communities the user has access to modify memberships for.
    $communities = [];
    $memberships = Og::getMemberships($currentUser);
    foreach ($memberships as $membership) {
      if ($membership->getGroupBundle() != 'community') {
        continue;
      }

      if ($membership->hasPermission('administer group') || $membership->hasPermission('manage members')) {
        $group = $membership->getGroup();
        if ($group) {
          $communities[$group->id()] = $group->getTitle();
        }
      }
    }

    $form['community'] = [
      '#title' => t('Community'),
      '#description' => t('Select which community you want to alter membership & roles for.'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $communities,
    ];

    $form['membership'] = [
      '#type' => 'radios',
      '#title' => $this->t('Membership'),
      '#description' => t("Community membership and roles will be set to exactly what is specified here. Previous user roles for this community will not be retained. If you revoke membership for a community, the user will automatically be removed from all the community's protocols."),
      '#required' => TRUE,
      '#options' => [
        'node-community-non-member' => $this->t('Non-member'),
        'node-community-member' => $this->t('Member'),
      ],
      '#default_value' => 'node-community-non-member',
    ];

    $form['roles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Roles'),
      '#states' => [
        'visible' => [
          ':input[name="membership"]' => ['value' => 'node-community-member'],
        ],
      ],
    ];

    foreach ($roles as $role) {
      if ($role->id() == 'node-community-member' || $role->id() == 'node-community-non-member') {
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
    $roles = $role_manager->getRolesByBundle('node', 'community');

    // Handling member/non-member separately, remove them from this list.
    unset($roles['node-community-member']);
    unset($roles['node-community-non-member']);

    // Set the defaults to 0, we only get one value from the radio.
    $this->configuration['node-community-member'] = 0;
    $this->configuration['node-community-non-member'] = 0;

    // Get the member/non-member from the radio.
    $membership = $form_state->getValue('membership');
    $this->configuration[$membership] = 1;

    // Community ID.
    $this->configuration['community'] = $form_state->getValue('community');

    // Roles beyond member/non-member.
    foreach ($roles as $role) {
      $this->configuration[$role->id()] = $form_state->getValue($role->id());
    }
  }

}
