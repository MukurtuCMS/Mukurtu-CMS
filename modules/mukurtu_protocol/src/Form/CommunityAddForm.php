<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;

/**
 * Form controller for Community creation forms.
 *
 * @ingroup mukurtu_protocol
 */
class CommunityAddForm extends ContentEntityForm {

  /**
   * The users to add to the community.
   *
   * @var array
   */
  protected array $members = [];

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // The Add form is a streamlined version of the edit form, hide any fields
    // that are not required using an allow list.
    $allow_list = [
      'name',
      'langcode',
      'field_description',
      'field_access_mode',
      'field_community_type',
      'field_membership_display',
    ];

    foreach (Element::children($form) as $key) {
      if (!in_array($key, $allow_list)) {
        unset($form[$key]);
      }
    }

    // If there are no options for Community Type, hide that field as well.
    if (empty($form['field_community_type']['#options'])) {
      $form['field_community_type']['#access'] = FALSE;
    }

    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());

    // Seed the member list on first load with the current user as manager.
    if ($form_state->get('members') === NULL) {
      $form_state->set('members', [
        $user->id() => ['entity' => $user, 'roles' => ['community_manager']],
      ]);
    }

    $form['membership_wrapper'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#weight' => 1001,
      '#access' => $this->isDefaultFormLangcode($form_state),
    ];

    $form['membership_wrapper']['membership_label'] = [
      '#type' => 'item',
      '#title' => $this->t('Community members'),
      '#description' => $this->t('Add existing users to the community. Protocol membership will be managed next.'),
    ];

    $form['membership_wrapper']['role_descriptions'] = static::buildRoleDescriptions();

    $form['membership_wrapper']['add_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['membership-add-row']],
    ];

    $form['membership_wrapper']['add_row']['user_search'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('Add a member'),
      '#placeholder' => $this->t('Search by name or email…'),
      '#selection_settings' => ['include_anonymous' => FALSE],
      '#autocreate' => FALSE,
    ];

    $form['membership_wrapper']['add_row']['add_member'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#validate' => [[static::class, 'membershipNoValidate']],
      '#submit' => [[static::class, 'addMemberSubmit']],
      '#limit_validation_errors' => [],
    ];

    $form['membership_wrapper']['member_table'] = static::buildMembershipTable($form_state);

    $form['#attached']['library'][] = 'mukurtu_protocol/membership-table';

    // Pass all active users to JS for client-side autocomplete, excluding
    // anyone already added to the membership table.
    $already_added = array_keys($form_state->get('members') ?? []);
    $uids = $this->entityTypeManager->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('uid', 0, '<>')
      ->sort('name')
      ->execute();
    $suggestions = [];
    foreach ($this->entityTypeManager->getStorage('user')->loadMultiple($uids) as $uid => $u) {
      if (in_array($uid, $already_added)) {
        continue;
      }
      $suggestions[] = [
        'value' => $u->getDisplayName() . ' (' . $uid . ')',
        'label' => $u->getDisplayName() . ' (' . $u->getEmail() . ')',
      ];
    }
    $form['#attached']['drupalSettings']['mukurtuMembership']['users'] = $suggestions;

    return $form;
  }

  /**
   * Role descriptions shown below the table header.
   */
  protected static function getRoleDescriptions(): array {
    return [
      'community_member'    => t('View the community page and be added to protocols within the community'),
      'community_affiliate' => t('View the community page and be added to protocols within the community. This is a designation for community partners.'),
      'community_manager'   => t('Manage community membership and create new protocols. View the community page and be added to protocols within the community.'),
    ];
  }

  /**
   * Build collapsible role description list.
   */
  protected static function buildRoleDescriptions(): array {
    $roles = [
      'community_member'    => t('Community member'),
      'community_affiliate' => t('Community affiliate'),
      'community_manager'   => t('Community manager'),
    ];
    $items = [];
    foreach (static::getRoleDescriptions() as $role_id => $description) {
      $label = $roles[$role_id];
      $items[] = ['#markup' => '<strong>' . $label . '</strong>: ' . $description];
    }
    return [
      '#type' => 'details',
      '#title' => t('Role descriptions'),
      '#open' => FALSE,
      '#attributes' => ['class' => ['role-descriptions']],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Build the inline membership table render array.
   */
  protected static function buildMembershipTable(FormStateInterface $form_state): array {
    $roles = [
      'community_member' => t('Member'),
      'community_affiliate' => t('Affiliate'),
      'community_manager' => t('Manager'),
    ];

    $header = [t('User')];
    foreach ($roles as $label) {
      $header[] = $label;
    }
    $header[] = ['data' => t('Actions'), 'class' => ['visually-hidden']];

    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => t('No members added yet.'),
      '#attributes' => ['class' => ['membership-table']],
    ];

    $error_uids = $form_state->get('membership_role_errors') ?? [];
    $current_uid = \Drupal::currentUser()->id();

    foreach ($form_state->get('members') ?? [] as $uid => $data) {
      /** @var \Drupal\user\UserInterface $member */
      $member = $data['entity'];
      $name = $member->getDisplayName();

      $row = [];
      if (in_array($uid, $error_uids)) {
        $row['#attributes']['class'][] = 'error';
      }
      $row['user'] = [
        '#markup' => $name . ' <small>(' . $member->getEmail() . ')</small>',
      ];

      foreach ($roles as $role_id => $label) {
        $locked = ($uid == $current_uid && $role_id === 'community_manager');
        $row[$role_id] = [
          '#type' => 'checkbox',
          '#title' => $label,
          '#title_display' => 'invisible',
          '#default_value' => $locked ? 1 : in_array($role_id, $data['roles']),
          '#disabled' => $locked,
          '#attributes' => ['aria-label' => t('@role for @name', ['@role' => $label, '@name' => $name])],
        ];
      }

      $row['remove'] = [
        '#type' => 'submit',
        '#value' => t('Remove'),
        '#name' => 'remove_member_' . $uid,
        '#validate' => [[static::class, 'membershipNoValidate']],
        '#submit' => [[static::class, 'removeMemberSubmit']],
        '#limit_validation_errors' => [],
        '#attributes' => [
          'class' => ['button--danger', 'button--small'],
          'aria-label' => t('Remove @name', ['@name' => $name]),
        ],
      ];

      $table[$uid] = $row;
    }

    return $table;
  }

  /**
   * Validation stub that suppresses all validation for membership buttons.
   */
  public static function membershipNoValidate(array &$form, FormStateInterface $form_state): void {}

  /**
   * Submit handler: add a user to the member list.
   */
  public static function addMemberSubmit(array &$form, FormStateInterface $form_state): void {
    $raw = $form_state->getUserInput();
    $input = $raw['membership_wrapper']['add_row']['user_search'] ?? NULL;

    $uid = NULL;
    if (is_numeric($input) && $input > 0) {
      $uid = (int) $input;
    }
    elseif (is_string($input) && $input !== '') {
      $uid = EntityAutocomplete::extractEntityIdFromAutocompleteInput($input);
    }

    if ($uid) {
      $members = $form_state->get('members') ?? [];
      if (!isset($members[$uid])) {
        $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
        if ($user) {
          $members[$uid] = ['entity' => $user, 'roles' => ['community_member']];
          $form_state->set('members', $members);
        }
      }
    }
    $form_state->setValue(['membership_wrapper', 'add_row', 'user_search'], NULL);
    $user_input = $form_state->getUserInput();
    $user_input['membership_wrapper']['add_row']['user_search'] = '';
    $form_state->setUserInput($user_input);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler: remove a user from the member list.
   */
  public static function removeMemberSubmit(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $uid = substr($trigger['#name'], strlen('remove_member_'));
    $members = $form_state->get('members') ?? [];
    unset($members[$uid]);
    $form_state->set('members', $members);
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $roles = ['community_member', 'community_affiliate', 'community_manager'];
    $stored_members = $form_state->get('members') ?? [];
    $table_values = $form_state->getValue(['membership_wrapper', 'member_table']) ?? [];
    $current_uid = $this->currentUser()->id();
    $missing_names = [];
    $missing_uids = [];

    foreach ($stored_members as $uid => $data) {
      // The current user's community_manager checkbox is locked, so treat it
      // as always checked when determining whether they have a role.
      if ($uid == $current_uid) {
        continue;
      }
      $row = $table_values[$uid] ?? [];
      $has_role = FALSE;
      foreach ($roles as $role) {
        if (!empty($row[$role])) {
          $has_role = TRUE;
          break;
        }
      }
      if (!$has_role) {
        $missing_names[] = $data['entity']->getDisplayName();
        $missing_uids[] = $uid;
      }
    }

    if ($missing_names) {
      $form_state->set('membership_role_errors', $missing_uids);
      $form_state->setError(
        $form['membership_wrapper']['membership_label'],
        $this->t('All members must be assigned at least one role. Missing roles for: @names.', [
          '@names' => implode(', ', $missing_names),
        ])
      );
    }

    $current_uid = $this->currentUser()->id();
    $has_manager = FALSE;
    foreach ($stored_members as $uid => $data) {
      $row = $table_values[$uid] ?? [];
      // Current user's manager checkbox is locked, so check form_state directly.
      if (!empty($row['community_manager']) || $uid == $current_uid) {
        $has_manager = TRUE;
        break;
      }
    }

    if (!$has_manager) {
      $form_state->setError(
        $form['membership_wrapper']['membership_label'],
        $this->t('At least one member must be assigned the Community manager role.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = parent::buildEntity($form, $form_state);

    $stored_members = $form_state->get('members') ?? [];
    $table_values = $form_state->getValue(['membership_wrapper', 'member_table']) ?? [];

    $current_uid = $this->currentUser()->id();

    foreach ($stored_members as $uid => $data) {
      $roles = [];
      $user_row = $table_values[$uid] ?? [];
      foreach (['community_member', 'community_affiliate', 'community_manager'] as $role) {
        if (!empty($user_row[$role])) {
          $roles[] = $role;
        }
      }
      // The current user's manager checkbox is disabled and won't be submitted.
      if ($uid == $current_uid && !in_array('community_manager', $roles)) {
        $roles[] = 'community_manager';
      }
      $this->members[$uid] = ['entity' => $data['entity'], 'roles' => $roles];
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($this->entity->save()) {
      /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
      $community = $this->entity;

      // Add members/roles.
      foreach ($this->members as $member) {
        $community->addMember($member['entity'])->setRoles($member['entity'], $member['roles']);
      }

      // Redirect to the protocol creation form if the author is a community
      // manager, and this is the default langcode.
      $protocolCreateUrl = Url::fromRoute('mukurtu_protocol.add_protocol_from_community', ['community' => $community->id()]);
      if ($protocolCreateUrl->access() && $this->isDefaultFormLangcode($form_state)) {
        $form_state->setRedirect('mukurtu_protocol.add_protocol_from_community', ['community' => $community->id()]);
      }
      else {
        $form_state->setRedirect('mukurtu_protocol.manage_community', ['group' => $community->id()]);
      }
    }
  }

}
