<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\entity_browser\Element\EntityBrowserElement;

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

    // Store the URL community for redirect and pre-selection purposes.
    $community_param = $community;
    if ($community_param) {
      $this->community = $community_param;
      $form_state->set('tethered_community', $community_param);
    }

    $form = parent::buildForm($form, $form_state);

    // Seed protocol_communities on first load of the community-bound route.
    $stored_communities = $form_state->get('protocol_communities') ?? [];
    if (empty($stored_communities) && $community_param) {
      $stored_communities = [$community_param->id() => $community_param];
      $form_state->set('protocol_communities', $stored_communities);
    }

    if ($stored_communities) {
      $this->entity->setCommunities(array_values($stored_communities));
    }

    // Use stored communities as default value when available, otherwise fall
    // back to the route community parameter.
    if (!empty($stored_communities)) {
      $default_entities = array_values($stored_communities);
    }
    elseif ($community_param) {
      $default_entities = [$community_param];
    }
    else {
      $default_entities = [];
    }

    $form['communities_and_members'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'communities-and-members-wrapper'],
      '#weight' => 2,
    ];

    $form['communities_and_members']['field_communities'] = [
      '#type' => 'entity_browser',
      '#entity_browser' => 'mukurtu_community_select',
      '#cardinality' => EntityBrowserElement::CARDINALITY_UNLIMITED,
      '#default_value' => $default_entities,
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_APPEND,
      '#title' => $this->t('Communities'),
      '#required' => TRUE,
    ];

    if (!empty($stored_communities)) {
      $community_items = array_map(
        fn($c) => ['#plain_text' => $c->getName()],
        array_values($stored_communities)
      );
      $form['communities_and_members']['communities_selected'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['communities-selected']],
        'label' => ['#markup' => '<strong>' . $this->t('Selected communities:') . '</strong>'],
        'list' => [
          '#theme' => 'item_list',
          '#items' => $community_items,
        ],
      ];
    }

    // Hidden button — clicked automatically by JS after entity browser
    // selection to refresh the communities display and member list.
    $form['communities_and_members']['auto_update_trigger'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#name' => 'auto_update_trigger',
      '#attributes' => [
        'class' => ['js-hide', 'js-communities-auto-update'],
        'tabindex' => '-1',
      ],
      '#validate' => [[static::class, 'membershipNoValidate']],
      '#submit' => [[static::class, 'updateMemberListSubmit']],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::communitiesAndMembersCallback',
        'wrapper' => 'communities-and-members-wrapper',
        'progress' => ['type' => 'throbber', 'message' => NULL],
      ],
    ];

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

    $form['name']['#weight'] = 0;
    $form['field_access_mode']['#weight'] = 1;
    $form['communities_and_members']['#weight'] = 2;
    $form['field_description']['#weight'] = 3;
    $form['field_membership_display']['#weight'] = 4;

    // Remove entity form display fields not used on this custom form.
    $allowed_keys = [
      'name',
      'field_access_mode',
      'communities_and_members',
      'field_description',
      'field_membership_display',
      'actions',
    ];
    foreach (Element::children($form) as $key) {
      if (!in_array($key, $allowed_keys)) {
        unset($form[$key]);
      }
    }

    // Seed the member list on first load with the current user as protocol steward.
    if ($form_state->get('members') === NULL) {
      $form_state->set('members', [
        $currentUser->id() => ['entity' => $currentUser, 'roles' => ['protocol_steward']],
      ]);
    }

    $form['communities_and_members']['membership_wrapper'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['id' => 'membership-wrapper'],
    ];

    $form['communities_and_members']['membership_wrapper']['membership_label'] = [
      '#type' => 'item',
      '#title' => $this->t('Protocol members'),
      '#description' => $this->t('Add members of the parent community to this protocol. A member may hold multiple roles.'),
    ];

    $form['communities_and_members']['membership_wrapper']['role_descriptions'] = static::buildRoleDescriptions();

    $form['communities_and_members']['membership_wrapper']['add_row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['membership-add-row']],
    ];

    // Use the Mukurtu user selection handler to restrict to community members
    // when at least one community is selected.
    $has_communities = !empty($stored_communities);
    $selection_settings = ['include_anonymous' => FALSE];
    if ($has_communities) {
      $selection_settings['group'] = $this->entity;
    }

    $form['communities_and_members']['membership_wrapper']['add_row']['user_search'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('Add a member'),
      '#placeholder' => $this->t('Search by name or email…'),
      '#selection_handler' => $has_communities ? 'mukurtu_user_selection' : 'default:user',
      '#selection_settings' => $selection_settings,
      '#autocreate' => FALSE,
    ];

    $form['communities_and_members']['membership_wrapper']['add_row']['add_member'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#validate' => [[static::class, 'membershipNoValidate']],
      '#submit' => [[static::class, 'addMemberSubmit']],
      '#limit_validation_errors' => [],
    ];

    $form['communities_and_members']['membership_wrapper']['member_table'] = static::buildMembershipTable($form_state);

    $form['#attached']['library'][] = 'mukurtu_protocol/membership-table';

    // Pass users to JS for client-side autocomplete, excluding anyone already
    // added to the membership table. When communities are selected, restrict to
    // their combined active membership; otherwise fall back to all active users.
    $already_added = array_keys($form_state->get('members') ?? []);
    $suggestions = [];
    if ($has_communities) {
      $og_membership_storage = \Drupal::entityTypeManager()->getStorage('og_membership');
      $seen_uids = [];
      foreach ($stored_communities as $c) {
        $memberships = $og_membership_storage->loadByProperties([
          'entity_type' => $c->getEntityTypeId(),
          'entity_id' => $c->id(),
          'state' => \Drupal\og\OgMembershipInterface::STATE_ACTIVE,
        ]);
        foreach ($memberships as $m) {
          $member = $m->getOwner();
          if ($member && $member->id() > 0
              && !in_array($member->id(), $already_added)
              && !isset($seen_uids[$member->id()])) {
            $seen_uids[$member->id()] = TRUE;
            $suggestions[] = [
              'value' => $member->getDisplayName() . ' (' . $member->id() . ')',
              'label' => $member->getDisplayName() . ' (' . $member->getEmail() . ')',
            ];
          }
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
    $form['#attached']['library'][] = 'mukurtu_protocol/protocol-community-browser';
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
   * AJAX callback: returns the full communities-and-members container.
   */
  public function communitiesAndMembersCallback(array &$form, FormStateInterface $form_state): array {
    return $form['communities_and_members'];
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
   * Submit handler: sync protocol_communities from the entity browser selection
   * and rebuild the membership wrapper.
   */
  public static function updateMemberListSubmit(array &$form, FormStateInterface $form_state): void {
    $eb_value = $form_state->getValue('field_communities');
    $communities = [];
    if (isset($eb_value['entities'])) {
      foreach (array_filter($eb_value['entities']) as $entity) {
        $communities[$entity->id()] = $entity;
      }
    }
    elseif (is_array($eb_value)) {
      // First-load default_value format: array of entity objects.
      foreach (array_filter($eb_value) as $entity) {
        if ($entity instanceof \Drupal\Core\Entity\EntityInterface) {
          $communities[$entity->id()] = $entity;
        }
      }
    }

    // Always preserve the tethered community for community-bound routes.
    $tethered = $form_state->get('tethered_community');
    if ($tethered && !isset($communities[$tethered->id()])) {
      $communities[$tethered->id()] = $tethered;
    }
    $form_state->set('protocol_communities', $communities);
    $currentUser = \Drupal::entityTypeManager()->getStorage('user')->load(\Drupal::currentUser()->id());
    $form_state->set('members', $currentUser ? [
      $currentUser->id() => ['entity' => $currentUser, 'roles' => ['protocol_steward']],
    ] : []);
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

    // Validate that at least one community has been selected.
    $eb_value = $form_state->getValue('field_communities');
    $selected_communities = [];
    if (isset($eb_value['entities'])) {
      $selected_communities = array_filter($eb_value['entities']);
    }
    elseif (is_array($eb_value)) {
      $selected_communities = array_filter($eb_value, fn($v) => $v instanceof \Drupal\Core\Entity\EntityInterface);
    }

    // Fall back to form state when the entity browser value is absent (e.g.
    // when the pre-populated default was never opened by the user).
    if (empty($selected_communities)) {
      $stored = $form_state->get('protocol_communities') ?? [];
      $selected_communities = array_values($stored);
    }
    if (empty($selected_communities)) {
      $tethered = $form_state->get('tethered_community');
      if ($tethered) {
        $selected_communities = [$tethered];
      }
    }
    if (empty($selected_communities)) {
      $form_state->setError($form['communities_and_members']['field_communities'], $this->t('At least one community is required.'));
    }

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
      $form_state->setError($form['communities_and_members']['membership_wrapper']['member_table'], '');
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
      $form_state->setError($form['communities_and_members']['membership_wrapper']['member_table'], '');
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

    // Read communities from the entity browser element value.
    $eb_value = $form_state->getValue('field_communities');
    $communities = [];
    if (isset($eb_value['entities'])) {
      $communities = array_values(array_filter($eb_value['entities']));
    }
    elseif (is_array($eb_value)) {
      $communities = array_values(array_filter($eb_value, fn($v) => $v instanceof \Drupal\Core\Entity\EntityInterface));
    }
    // Fall back to form state (set by updateMemberListSubmit or route seeding).
    if (empty($communities)) {
      $stored = $form_state->get('protocol_communities') ?? [];
      $communities = array_values($stored);
    }
    if ($communities) {
      $entity->setCommunities($communities);
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
    $community = $this->community;
    if (!$community) {
      $stored = $form_state->get('protocol_communities') ?? [];
      $community = !empty($stored) ? reset($stored) : NULL;
    }
    if ($community) {
      $form_state->setRedirect('mukurtu_protocol.manage_community', ['group' => $community->id()]);
    }
  }

}
