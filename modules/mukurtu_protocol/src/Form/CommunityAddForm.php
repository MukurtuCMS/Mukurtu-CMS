<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Form controller for Community creation forms.
 *
 * @ingroup mukurtu_protocol
 */
class CommunityAddForm extends ContentEntityForm {

  /**
   * The users to add to the community.
   *
   * @var mixed
   */
  protected $members;

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
        $form[$key]['#access'] = FALSE;
      }
    }

    // If there are no options for Community Type, hide that field as well.
    if (empty($form['field_community_type']['#options'])) {
      $form['field_community_type']['#access'] = FALSE;
    }

    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());

    // Community Members.
    $form['community_member_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Community members'),
      '#description' => $this->t('Helper text about community members.'),
      '#weight' => '1001',
    ];

    $form['community_member'] = [
      '#type' => 'entity_browser',
      '#id' => 'community-member',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
      '#prefix' => '<div id="role-community-member">',
      '#suffix' => '</div>',
      '#process' => [
        [get_called_class(), 'updateDefaultValues'],
        [
          '\Drupal\entity_browser\Element\EntityBrowserElement',
          'processEntityBrowser',
        ],
        [get_called_class(), 'processEntityBrowser'],
      ],
      '#weight' => '1002',
    ];

    // Community Affiliates.
    $form['community_affiliate_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Community affiliates'),
      '#description' => $this->t('Helper text about community affiliates.'),
      '#weight' => '1003',
    ];

    $form['community_affiliate'] = [
      '#type' => 'entity_browser',
      '#id' => 'community-affiliate',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
      '#prefix' => '<div id="role-community-affiliate">',
      '#suffix' => '</div>',
      '#process' => [
        [get_called_class(), 'updateDefaultValues'],
        [
          '\Drupal\entity_browser\Element\EntityBrowserElement',
          'processEntityBrowser',
        ],
        [get_called_class(), 'processEntityBrowser'],
      ],
      '#weight' => '1004',
    ];

    // Community Managers.
    $form['community_manager_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Community managers'),
      '#description' => $this->t('Helper text about community managers.'),
      '#weight' => '1005',
    ];

    $defaultStatus = "<ul>";
    $defaultStatus .= "<li>{$user->getAccountName()} ({$user->getEmail()})</li>";
    $defaultStatus .= "</ul>";

    $form['community_manager'] = [
      '#type' => 'entity_browser',
      '#id' => 'community-manager',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#default_value' => [$user],
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
      '#prefix' => '<div id="role-community-manager">',
      '#suffix' => $defaultStatus . '</div>',
      '#process' => [
        [get_called_class(), 'updateDefaultValues'],
        [
          '\Drupal\entity_browser\Element\EntityBrowserElement',
          'processEntityBrowser',
        ],
        [get_called_class(), 'processEntityBrowser'],
      ],
      '#weight' => '1006',
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

    $form[$role]['#suffix'] = $status . '</div>';
    $response = new AjaxResponse();
    $roleID = str_replace('_', '-', $role);
    $response->addCommand(new ReplaceCommand("#role-{$roleID}", $form[$role]));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = parent::buildEntity($form, $form_state);

    // Grab the memberships.
    foreach (['community_manager', 'community_member', 'community_affiliate'] as $role) {
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
