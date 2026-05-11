<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\og\Og;

/**
 * Form controller for Protocol creation forms.
 *
 * @ingroup mukurtu_protocol
 */
class ProtocolAddForm extends EntityForm {
  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The owning community for the protocol.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Community
   */
  protected $community;

  /**
   * The users to add to the community.
   *
   * @var mixed
   */
  protected $members;

  /**
   * The Module Handler.
   */
  protected $moduleHandler;

  /**
   * The user IDs of the community managers.
   *
   * @var int[]
   */
  protected $communityManagers;

  public function __construct() {
    $this->entity = Protocol::create([]);

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->account = $container->get('current_user');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Role definitions: machine name => label.
   */
  protected static function getRoles(): array {
    return [
      'protocol_member'           => t('Protocol member'),
      'protocol_affiliate'        => t('Protocol affiliate'),
      'contributor'               => t('Contributor'),
      'curator'                   => t('Curator'),
      'community_record_steward'  => t('Community record steward'),
      'language_contributor'      => t('Language contributor'),
      'language_steward'          => t('Language Steward'),
      'protocol_steward'          => t('Protocol Steward'),
    ];
  }

  /**
   * Role descriptions shown below the table header.
   */
  protected static function getRoleDescriptions(): array {
    return [
      'protocol_member'           => t('View content but cannot create or edit.'),
      'protocol_affiliate'        => t('View content but cannot create or edit. This is a designation for community partners that mirrors the community affiliate role.'),
      'contributor'               => t('Create, edit, and delete their own digital heritage items, person records, place records, and media assets.'),
      'curator'                   => t('Create, edit, and delete their own collections and media assets.'),
      'community_record_steward'  => t('Add community records to content, as well as edit and delete their community records.'),
      'language_contributor'      => t('Create, edit, and delete their own dictionary words and word lists.'),
      'language_steward'          => t('Create, edit, and delete ALL dictionary words and word lists, and media assets.'),
      'protocol_steward'          => t('Manage protocol membership, create, edit, and delete ALL content and media assets, and manage Local Contexts projects.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $community = NULL) {
    $this->setModuleHandler($this->moduleHandler);
    /** @var \Drupal\user\UserInterface $currentUser */
    $currentUser = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());

    // Preserve the original URL parameter to distinguish the two routes.
    $community_param = $community;

    // Set the community relationship.
    if ($community) {
      $this->community = $community;
      $this->entity->setCommunities([$community]);
    }

    $form = parent::buildForm($form, $form_state);

    if (!$community_param) {
      // Build a list of communities where the current user is a manager.
      $community_options = [];
      foreach (Og::getMemberships($currentUser) as $membership) {
        if ($membership->getGroupEntityType() === 'community' &&
            $membership->hasRole('community-community-community_manager')) {
          $group = $membership->getGroup();
          if ($group) {
            $community_options[$group->id()] = $group->label();
          }
        }
      }

      $form['field_communities'] = [
        '#type' => 'select',
        '#title' => $this->t('Community'),
        '#options' => $community_options,
        '#empty_option' => $this->t('- Select a community -'),
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '::membershipWrapperCallback',
          'wrapper' => 'membership-wrapper',
          'event' => 'change',
        ],
      ];

      // Resolve the effective community from the just-submitted select value or
      // from form state (persists across rebuilds).
      $cid = $form_state->getValue('field_communities');
      $stored_community = $form_state->get('protocol_community');
      $new_cid = $stored_community ? $stored_community->id() : NULL;

      if ($cid && $cid != $new_cid) {
        // Community changed — load the new one and reset the member list so
        // carried-over members from the previous community are cleared.
        $community = $this->entityTypeManager->getStorage('community')->load($cid);
        if ($community) {
          $form_state->set('protocol_community', $community);
          // Reset members to just the current user when community changes.
          $form_state->set('members', [
            $currentUser->id() => ['entity' => $currentUser, 'roles' => ['protocol_steward']],
          ]);
        }
      }
      elseif ($stored_community) {
        $community = $stored_community;
      }

      if ($community) {
        $this->entity->setCommunities([$community]);
      }
    }

    // Community name.
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cultural Protocol Name'),
      '#size' => 60,
      '#required' => TRUE,
    ];

    // Sharing setting.
    $form['field_access_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Cultural Protocol Type'),
      '#description' => $this->t('<p><strong>Strict</strong> — Content that uses this cultural protocol is only visible to members of this cultural protocol. The cultural protocol page is also only visible to cultural protocol members.</p><p><strong>Open</strong> — Content that uses this cultural protocol is visible to all site members and visitors, with no login required. The cultural protocol page is also public.</p>'),
      '#options' => [
        'strict' => $this->t('Strict'),
        'open' => $this->t('Open'),
      ],
      '#default_value' => 'strict',
    ];

    // Description.
    $form['field_description'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Description'),
      '#required' => FALSE,
      '#format' => 'basic_html',
      '#allowed_formats' => ['basic_html', 'full_html', 'mukurtu_html'],
    ];

    // Membership list display setting.
    $form['field_membership_display'] = [
      '#type' => 'radios',
      '#title' => $this->t('Membership display'),
      '#description' => $this->t('Select which, if any, protocol members to display on the protocol page.'),
      '#options' => [
        'none' => $this->t('Do not display any protocol members'),
        'stewards' => $this->t('Only display cultural protocol stewards'),
        'all' => $this->t('Display all protocol members'),
      ],
      '#default_value' => 'none',
    ];

    // On the standalone form, set display order after all fields are defined.
    if (!$community_param) {
      $form['name']['#weight'] = 0;
      $form['field_access_mode']['#weight'] = 1;
      $form['field_communities']['#weight'] = 2;
      $form['field_description']['#weight'] = 3;
      $form['field_membership_display']['#weight'] = 4;

      // Remove all entity form display fields not needed on the standalone form.
      $standalone_allowed = [
        'name',
        'field_access_mode',
        'field_communities',
        'field_description',
        'field_membership_display',
        'actions',
      ];
      foreach (Element::children($form) as $key) {
        if (!in_array($key, $standalone_allowed)) {
          unset($form[$key]);
        }
      }
    }

    // Seed the member list on first load with the current user as protocol steward.
    if ($form_state->get('members') === NULL) {
      $form_state->set('members', [
        $currentUser->id() => ['entity' => $currentUser, 'roles' => ['protocol_steward']],
      ]);
    }

    $form['membership_wrapper'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#weight' => 5,
      '#attributes' => ['id' => 'membership-wrapper'],
    ];

    $form['membership_wrapper']['membership_label'] = [
      '#type' => 'item',
      '#title' => $this->t('Protocol members'),
      '#description' => $this->t('Add members of the parent community to this protocol. A member may hold multiple roles.'),
    ];

    $form['membership_wrapper']['role_descriptions'] = static::buildRoleDescriptions();

    $form['membership_wrapper']['add_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['membership-add-row']],
    ];

    // Use the Mukurtu user selection handler to restrict to community members.
    $selection_settings = ['include_anonymous' => FALSE];
    if ($community) {
      $selection_settings['group'] = $this->entity;
    }

    $form['membership_wrapper']['add_row']['user_search'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('Add a member'),
      '#placeholder' => $this->t('Search by name or email…'),
      '#selection_handler' => $community ? 'mukurtu_user_selection' : 'default:user',
      '#selection_settings' => $selection_settings,
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

    // Pass users to JS for client-side autocomplete, excluding anyone already
    // added to the membership table. When a community is set, restrict to its
    // active members; otherwise fall back to all active users.
    $already_added = array_keys($form_state->get('members') ?? []);
    $suggestions = [];
    if ($community) {
      $memberships = \Drupal::entityTypeManager()
        ->getStorage('og_membership')
        ->loadByProperties([
          'entity_type' => $community->getEntityTypeId(),
          'entity_id' => $community->id(),
          'state' => \Drupal\og\OgMembershipInterface::STATE_ACTIVE,
        ]);
      foreach ($memberships as $membership) {
        $member = $membership->getOwner();
        if ($member && $member->id() > 0 && !in_array($member->id(), $already_added)) {
          $suggestions[] = [
            'value' => $member->getDisplayName() . ' (' . $member->id() . ')',
            'label' => $member->getDisplayName() . ' (' . $member->getEmail() . ')',
          ];
        }
      }
    }
    else {
      $uids = $this->entityTypeManager->getStorage('user')->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('uid', 0, '<>')
        ->sort('name')
        ->execute();
      foreach ($this->entityTypeManager->getStorage('user')->loadMultiple($uids) as $uid => $u) {
        if (in_array($uid, $already_added)) {
          continue;
        }
        $suggestions[] = [
          'value' => $u->getDisplayName() . ' (' . $uid . ')',
          'label' => $u->getDisplayName() . ' (' . $u->getEmail() . ')',
        ];
      }
    }
    $form['#attached']['drupalSettings']['mukurtuMembership']['users'] = $suggestions;
    $form['#attached']['drupalSettings']['mukurtuMembership']['scrollToTable'] = (bool) $form_state->get('membership_scroll');
    $form_state->set('membership_scroll', FALSE);

    return $form;
  }

  /**
   * Build collapsible role description list.
   */
  protected static function buildRoleDescriptions(): array {
    $items = [];
    foreach (static::getRoleDescriptions() as $role_id => $description) {
      $label = static::getRoles()[$role_id];
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
    $roles = static::getRoles();

    $header = [t('User')];
    foreach ($roles as $label) {
      $header[] = $label;
    }
    $header[] = ['data' => t('Actions'), 'class' => ['visually-hidden']];

    $table = [
      '#type' => 'table',
      '#caption' => t('Protocol members and roles'),
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
        $row['#attributes']['aria-invalid'] = 'true';
      }
      $row['user'] = [
        '#markup' => $name . ' <small>(' . $member->getEmail() . ')</small>',
      ];

      foreach ($roles as $role_id => $label) {
        $locked = ($uid == $current_uid && $role_id === 'protocol_steward');
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
   * AJAX callback: returns the rebuilt membership wrapper after community selection.
   */
  public function membershipWrapperCallback(array &$form, FormStateInterface $form_state): array {
    return $form['membership_wrapper'];
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
          $members[$uid] = ['entity' => $user, 'roles' => ['protocol_member']];
          $form_state->set('members', $members);
        }
      }
    }
    $form_state->setValue(['membership_wrapper', 'add_row', 'user_search'], NULL);
    $user_input = $form_state->getUserInput();
    $user_input['membership_wrapper']['add_row']['user_search'] = '';
    $form_state->setUserInput($user_input);
    $form_state->set('membership_scroll', TRUE);
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
    $form_state->set('membership_scroll', TRUE);
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions['submit_another'] = [
      '#type' => 'submit',
      '#value' => $this
        ->t('Save and create another protocol'),
      '#submit' => [
        '::submitForm',
        '::save',
      ],
      '#gin_action_item' => TRUE,
    ];

    $actions['submit_done'] = [
      '#type' => 'submit',
      '#value' => $this
        ->t('Save'),
      '#submit' => [
        '::submitForm',
        '::save',
        '::redirectToCommunity',
      ],
      '#gin_action_item' => TRUE,
    ];

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $roles = array_keys(static::getRoles());
    $stored_members = $form_state->get('members') ?? [];
    $table_values = $form_state->getValue(['membership_wrapper', 'member_table']) ?? [];
    $current_uid = $this->currentUser()->id();
    $missing_names = [];
    $missing_uids = [];

    foreach ($stored_members as $uid => $data) {
      // The current user's protocol_steward checkbox is locked, so treat it
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
      $this->messenger()->addError(
        $this->t('All members must be assigned at least one role. Missing roles for: @names.', [
          '@names' => implode(', ', $missing_names),
        ])
      );
      $form_state->setError($form['membership_wrapper']['member_table'], '');
    }

    $has_steward = FALSE;
    foreach ($stored_members as $uid => $data) {
      $row = $table_values[$uid] ?? [];
      // Current user's steward checkbox is locked, so count them directly.
      if (!empty($row['protocol_steward']) || $uid == $current_uid) {
        $has_steward = TRUE;
        break;
      }
    }

    if (!$has_steward) {
      $this->messenger()->addError(
        $this->t('At least one member must be assigned the Protocol steward role.')
      );
      $form_state->setError($form['membership_wrapper']['member_table'], '');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = clone $this->entity;
    /** @var \Drupal\mukurtu_protocol\Entity\Protocol $entity */
    $entity->setName($form_state->getValue('name'));
    $entity->setDescription($form_state->getValue('field_description'));
    $entity->setSharingSetting($form_state->getValue('field_access_mode'));
    $entity->setMembershipDisplay($form_state->getValue('field_membership_display'));

    // On the standalone form the community is selected via a custom element
    // and stored in form state rather than pre-set via the URL.
    $stored_community = $form_state->get('protocol_community');
    if ($stored_community) {
      $entity->setCommunities([$stored_community]);
    }

    $role_keys = array_keys(static::getRoles());
    $stored_members = $form_state->get('members') ?? [];
    $table_values = $form_state->getValue(['membership_wrapper', 'member_table']) ?? [];

    $current_uid = $this->currentUser()->id();

    foreach ($stored_members as $uid => $data) {
      $roles = [];
      $user_row = $table_values[$uid] ?? [];
      foreach ($role_keys as $role) {
        if (!empty($user_row[$role])) {
          $roles[] = $role;
        }
      }
      // The current user's steward checkbox is disabled and won't be submitted.
      if ($uid == $current_uid && !in_array('protocol_steward', $roles)) {
        $roles[] = 'protocol_steward';
      }
      $this->members[$uid] = ['entity' => $data['entity'], 'roles' => $roles];
    }

    return $entity;
  }

  /**
   * {@inheritDoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($this->entity->save()) {
      // Add the success message.
      $this->messenger()->addStatus(t('Created %protocol.', ['%protocol' => $this->entity->getName()]));

      /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
      $protocol = $this->entity;

      // Add members/roles.
      foreach ($this->members as $member) {
        $protocol->addMember($member['entity'])->setRoles($member['entity'], $member['roles']);
      }
    }
  }

  /**
   * Redirect to the owning community after save.
   */
  public function redirectToCommunity(array $form, FormStateInterface $form_state) {
    $community = $this->community ?? $form_state->get('protocol_community');
    if ($community) {
      $form_state->setRedirect('mukurtu_protocol.manage_community', ['group' => $community->id()]);
    }
  }

}
