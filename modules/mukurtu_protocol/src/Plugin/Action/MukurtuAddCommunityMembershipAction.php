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
 * VBO for adding users to a community.
 *
 * @Action(
 *   id = "mukurtu_add_community_membership_action",
 *   label = @Translation("Add user(s) to a community"),
 *   type = "user",
 *   confirm = TRUE,
 *   requirements = {
 *     "_custom_access" = TRUE,
 *   },
 * )
 */
class MukurtuAddCommunityMembershipAction extends ViewsBulkOperationsActionBase implements PluginFormInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if (!$entity || !($entity instanceof User)) {
      return;
    }
    /** @var \Drupal\user\Entity\User $entity */

    // Load the community we are altering memberships for.
    $community_id = $this->configuration['community'];
    /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
    $community = \Drupal::entityTypeManager()->getStorage('community')->load($community_id);

    // Set membership and roles.
    $community->addMember($entity, array_keys(array_filter($this->configuration['roles'])));

    return $this->t('Added user @user to community @community', ['@user' => $entity->getDisplayName(), '@community' => $community->getName()]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($object instanceof User) {
      // Load the community we are altering memberships for.
      /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
      $community = \Drupal::entityTypeManager()->getStorage('community')->load($this->configuration['community']);

      // Check if target user is already a member.
      if ($community->getMembership($object)) {
        return $return_as_object ? AccessResult::forbidden() : FALSE;
      }

      // Check if requesting user can modify this community's membership.
      $membership = $community->getMembership($account);
      if ($membership && ($membership->hasPermission('administer group') || $membership->hasPermission('manage members'))) {
        return $return_as_object ? AccessResult::allowed() : TRUE;
      }
    }

    return $return_as_object ? AccessResult::forbidden() : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function customAccess(AccountInterface $account, ViewExecutable $view): bool {
    // Get the OG Memberships for account.
    $memberships = array_filter(Og::getMemberships($account), fn ($m) => $m->getGroupBundle() === 'community');

    // User needs permission to manage membership for at least one community
    // to use this VBO.
    foreach ($memberships as $membership) {
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
    $roles = $role_manager->getRolesByBundle('community', 'community');

    // Get the communities the user has access to modify memberships for.
    $communities = [];
    $communityMemberships = array_filter(Og::getMemberships($currentUser), fn ($m) => $m->getGroupBundle() === 'community');
    $managerMemberships = array_filter($communityMemberships, fn($m) => $m->hasPermission('administer group') || $m->hasPermission('manage members'));
    $managerCommunities = array_map(fn($m) => $m->getGroup(), $managerMemberships);

    /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
    foreach ($managerCommunities as $community) {
      $communities[$community->id()] = $community->getName();
    }

    $form['community'] = [
      '#title' => t('Community'),
      '#description' => t('Select which community you want to alter membership & roles for.'),
      '#type' => 'select',
      '#required' => TRUE,
      '#options' => $communities,
    ];

    $form['roles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Roles'),
      '#states' => [
        'visible' => [
          ':input[name="membership"]' => ['value' => 'community-community-member'],
        ],
      ],
    ];

    foreach ($roles as $role) {
      if ($role->id() == 'community-community-member' || $role->id() == 'community-community-non-member') {
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
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $role_manager = \Drupal::service('og.role_manager');
    $roles = $role_manager->getRolesByBundle('community', 'community');

    // Handling member/non-member separately, remove them from this list.
    unset($roles['community-community-member']);
    unset($roles['community-community-non-member']);

    // Community ID.
    $this->configuration['community'] = $form_state->getValue('community');

    // Roles beyond member/non-member.
    foreach ($roles as $role) {
      $this->configuration['roles'][str_replace('community-community-', '', $role->id())] = $form_state->getValue($role->id());
    }
  }

}
