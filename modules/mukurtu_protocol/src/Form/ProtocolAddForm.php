<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $community = NULL) {
    $this->setModuleHandler($this->moduleHandler);
    /** @var \Drupal\Core\Session\AccountInterface $currentUser */
    $currentUser = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());

    // Set the community relationship.
    if ($community) {
      $this->community = $community;
      $this->entity->setCommunities([$community]);
    }

    $form = parent::buildForm($form, $form_state);

    // Community name.
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cultural Protocol Name'),
      '#size' => 60,
      '#required' => TRUE,
    ];

    // Sharing setting.
    // @todo Need to pull these options from field def.
    $form['field_access_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Cultural Protocol Type'),
      '#description' => $this->t('Strict - Content that uses this cultural protocol is only visible to members of this cultural protocol. The cultural protocol page is also only visible to cultural protocol members.<br>Open - Content that uses this cultural protocol is visible to all site members and visitors, with no login required. The cultural protocol page is also public.'),
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
      '#title' => $this->t('Membership Display'),
      '#description' => $this->t('TODO: membership display helper text'),
      '#options' => [
        'none' => $this->t('Do not display'),
        'stewards' => $this->t('Display cultural protocol stewards'),
        'all' => $this->t('Display all members'),
      ],
      '#default_value' => 'none',
    ];

        // Protocol affiliates.
    $form['protocol_affiliate_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Cultural protocol affiliates'),
      '#description' => $this->t('Helper text about protocol affiliates.'),
    ];
    $form['protocol_affiliate'] = [
      '#type' => 'entity_browser',
      '#id' => 'protocol-affiliate',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
      '#widget_context' => ['group' => $this->entity],
      '#default_value' => [],
      '#prefix' => '<div id="role-protocol-affiliate">',
      '#suffix' => '</div>',
      '#process' => [
        [get_called_class(), 'updateDefaultValues'],
        [
          '\Drupal\entity_browser\Element\EntityBrowserElement',
          'processEntityBrowser',
        ],
        [get_called_class(), 'processEntityBrowser'],
      ],
    ];

    // Protocol members.
    $form['protocol_member_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Cultural protocol members'),
      '#description' => $this->t('Helper text about protocol members.'),
    ];
    $form['protocol_member'] = [
      '#type' => 'entity_browser',
      '#id' => 'protocol-member',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
      '#widget_context' => ['group' => $this->entity],
      '#default_value' => [],
      '#prefix' => '<div id="role-protocol-member">',
      '#suffix' => '</div>',
      '#process' => [
        [get_called_class(), 'updateDefaultValues'],
        [
          '\Drupal\entity_browser\Element\EntityBrowserElement',
          'processEntityBrowser',
        ],
        [get_called_class(), 'processEntityBrowser'],
      ],
    ];

    // Contributors.
    $form['protocol_contributor_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Contributors'),
      '#description' => $this->t('Helper text about contributors.'),
    ];
    $form['protocol_contributor'] = [
      '#type' => 'entity_browser',
      '#id' => 'protocol-contributor',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
      '#widget_context' => ['group' => $this->entity],
      '#default_value' => [],
      '#prefix' => '<div id="role-protocol-contributor">',
      '#suffix' => '</div>',
      '#process' => [
        [get_called_class(), 'updateDefaultValues'],
        [
          '\Drupal\entity_browser\Element\EntityBrowserElement',
          'processEntityBrowser',
        ],
        [get_called_class(), 'processEntityBrowser'],
      ],
    ];

    // Curators.
    $form['protocol_curator_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Curators'),
      '#description' => $this->t('Helper text about curators.'),
    ];
    $form['protocol_curator'] = [
      '#type' => 'entity_browser',
      '#id' => 'protocol-curator',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
      '#widget_context' => ['group' => $this->entity],
      '#default_value' => [],
      '#prefix' => '<div id="role-protocol-curator">',
      '#suffix' => '</div>',
      '#process' => [
        [get_called_class(), 'updateDefaultValues'],
        [
          '\Drupal\entity_browser\Element\EntityBrowserElement',
          'processEntityBrowser',
        ],
        [get_called_class(), 'processEntityBrowser'],
      ],
    ];

    // Community record stewards.
    $form['protocol_community_record_steward_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Community record stewards'),
      '#description' => $this->t('Helper text about community record stewards'),
    ];
    $form['protocol_community_record_steward'] = [
      '#type' => 'entity_browser',
      '#id' => 'protocol-community-record-steward',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
      '#widget_context' => ['group' => $this->entity],
      '#default_value' => [],
      '#prefix' => '<div id="role-protocol-community-record-steward">',
      '#suffix' => '</div>',
      '#process' => [
        [get_called_class(), 'updateDefaultValues'],
        [
          '\Drupal\entity_browser\Element\EntityBrowserElement',
          'processEntityBrowser',
        ],
        [get_called_class(), 'processEntityBrowser'],
      ],
    ];

    // Language contributors.
    $form['protocol_language_contributor_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Language contributors'),
      '#description' => $this->t('Helper text about language contributors.'),
    ];
    $form['protocol_language_contributor'] = [
      '#type' => 'entity_browser',
      '#id' => 'protocol-language-contributor',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
      '#widget_context' => ['group' => $this->entity],
      '#default_value' => [],
      '#prefix' => '<div id="role-protocol-language-contributor">',
      '#suffix' => '</div>',
      '#process' => [
        [get_called_class(), 'updateDefaultValues'],
        [
          '\Drupal\entity_browser\Element\EntityBrowserElement',
          'processEntityBrowser',
        ],
        [get_called_class(), 'processEntityBrowser'],
      ],
    ];

    // Language stewards.
    $form['protocol_language_steward_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Language stewards'),
      '#description' => $this->t('Helper text about language stewards.'),
    ];
    $form['protocol_language_steward'] = [
      '#type' => 'entity_browser',
      '#id' => 'protocol-language-steward',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
      '#widget_context' => ['group' => $this->entity],
      '#default_value' => [],
      '#prefix' => '<div id="role-protocol-language-steward">',
      '#suffix' => '</div>',
      '#process' => [
        [get_called_class(), 'updateDefaultValues'],
        [
          '\Drupal\entity_browser\Element\EntityBrowserElement',
          'processEntityBrowser',
        ],
        [get_called_class(), 'processEntityBrowser'],
      ],
    ];

    // Protocol stewards.
    $form['protocol_steward_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Cultural protocol stewards'),
      '#description' => $this->t('Helper text about protocol stewards.'),
    ];
    $defaultStatus = "<ul>";
    $defaultStatus .= "<li>{$currentUser->getAccountName()} ({$currentUser->getEmail()})</li>";
    $defaultStatus .= "</ul>";
    $form['protocol_steward'] = [
      '#type' => 'entity_browser',
      '#id' => 'protocol-steward',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#default_value' => [$currentUser],
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
      '#widget_context' => ['group' => $this->entity],
      '#prefix' => '<div id="role-protocol-steward">',
      '#suffix' => $defaultStatus . '</div>',
      '#process' => [
        [get_called_class(), 'updateDefaultValues'],
        [
          '\Drupal\entity_browser\Element\EntityBrowserElement',
          'processEntityBrowser',
        ],
        [get_called_class(), 'processEntityBrowser'],
      ],
    ];




    return $form;
  }

  /**
   * Keep default value for entity browser up to date.
   */
  public static function updateDefaultValues(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['#default_value'] = $element['#value']['entities'] ?? $element['#default_value'];
    return $element;
  }

  /**
   * Render API callback: Processes the entity browser element.
   */
  public static function processEntityBrowser(&$element, FormStateInterface $form_state, &$complete_form) {
    $trigger = $form_state->getTriggeringElement();
    $element['#default_value'] = $element['#value']['entities'] ?? $element['#default_value'];
    $element['entity_ids']['#default_value'] = $trigger['#value'] ?? $element['entity_ids']['#default_value'];
    $element['entity_ids']['#ajax'] = [
      'callback' => [get_called_class(), 'updateCallback'],
      'wrapper' => "role-{$element['#id']}",
      'event' => 'entity_browser_value_updated',
    ];
    return $element;
  }

  /**
   * AJAX callback: Re-renders the Entity Browser.
   */
  public static function updateCallback(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'];
    $role = $parents[0];
    $value = $form_state->getValue($role);
    $status = "<ul>";
    foreach ($value['entities'] as $user) {
      $status .= "<li>{$user->getAccountName()} ({$user->getEmail()})</li>";
    }
    $status .= "</ul>";

    unset($form[$role]['#default_value']);
    unset($form[$role]['entity_ids']['#default_value']);
    $form[$role]['#suffix'] = $status . '</div>';
    $response = new AjaxResponse();
    $roleID = str_replace('_', '-', $role);
    $response->addCommand(new ReplaceCommand("#role-{$roleID}", $form[$role]));
    return $response;
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
    ];

    return $actions;
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

    // Grab the memberships.
    foreach (['protocol_steward', 'protocol_member'] as $role) {
      $members = $form_state->getValue($role);
      $users = !empty($members['entities']) ? $members['entities'] : [];
      foreach ($users as $user) {
        if (!isset($this->members[$user->id()])) {
          $this->members[$user->id()] = ['entity' => $user, 'roles' => []];
        }

        if (!in_array($role, $this->members[$user->id()]['roles'])) {
          $this->members[$user->id()]['roles'][] = $role;
        }
      }
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
    $form_state->setRedirect('mukurtu_protocol.manage_community', ['group' => $this->community->id()]);
  }

}
