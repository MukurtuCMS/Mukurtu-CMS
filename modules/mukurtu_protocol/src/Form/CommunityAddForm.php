<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\Element\EntityBrowserElement;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;

/**
 * Form controller for Community creation forms.
 *
 * @ingroup mukurtu_protocol
 */
class CommunityAddForm extends EntityForm {
  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;


  /**
   * The users to be made community managers.
   *
   * @var \Drupal\core\Session\AccountInterface[]
   */
  protected $communityManagers;

  /**
   * The users to add to the community.
   *
   * @var mixed
   */
  protected $members;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->account = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state) {
    $form_display = EntityFormDisplay::collectRenderDisplay($this->entity, $this->getOperation());
    $this->setFormDisplay($form_display, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDisplay(FormStateInterface $form_state) {
    return $form_state->get('form_display');
  }

  /**
   * {@inheritdoc}
   */
  public function setFormDisplay(EntityFormDisplayInterface $form_display, FormStateInterface $form_state) {
    $form_state->set('form_display', $form_display);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Set #parents to 'top-level' by default.
    $form += ['#parents' => []];

    /** @var \Drupal\Core\Session\AccountInterface $currentUser */
    $currentUser = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());

    // Community name.
    $form['name'] = $this->renderField('name', $form_state, $form);

    // Description.
    $form['field_description'] = $this->renderField('field_description', $form_state, $form);

    // Access Mode.
    $form['field_access_mode'] = $this->renderField('field_access_mode', $form_state, $form);

    // Community Type.
    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery();
    $community_type_terms = $query->condition('vid', 'community_type')
      ->accessCheck(TRUE)
      ->execute();
    // Don't display if there aren't any available terms.
    if (!empty($community_type_terms)) {
      $form['field_community_type'] = $this->renderField('field_community_type', $form_state, $form);
    }

    // Community Managers.
    $form['community_manager_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Community managers'),
      '#description' => $this->t('Helper text about community managers.'),
      '#weight' => 9000,
    ];

    $defaultStatus = "<ul>";
    $defaultStatus .= "<li>{$currentUser->getAccountName()} ({$currentUser->getEmail()})</li>";
    $defaultStatus .= "</ul>";

    $form['community_manager'] = [
      '#type' => 'entity_browser',
      '#id' => 'community-manager',
      '#cardinality' => -1,
      '#weight' => 9001,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#default_value' => [$currentUser],
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
    ];

    // Community Members.
    $form['community_member_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Community members'),
      '#description' => $this->t('Helper text about community members.'),
      '#weight' => 9002,
    ];

    $form['community_member'] = [
      '#type' => 'entity_browser',
      '#id' => 'community-member',
      '#cardinality' => -1,
      '#weight' => 9003,
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
    ];

    // Community Affiliates.
    $form['community_affiliate_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Community affiliates'),
      '#description' => $this->t('Helper text about community affiliates.'),
      '#weight' => 9004,
    ];

    $form['community_affiliate'] = [
      '#type' => 'entity_browser',
      '#id' => 'community-affiliate',
      '#cardinality' => -1,
      '#weight' => 9005,
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
    ];

    // Membership list display setting.
    $form['field_membership_display'] = [
      '#type' => 'radios',
      '#title' => $this->t('Membership display'),
      '#description' => $this->t('TODO: membership display helper text'),
      '#options' => [
        'none' => $this->t('None: Do not display'),
        'managers' => $this->t('Managers: Display community managers'),
        'all' => $this->t('All: Display all members'),
      ],
      '#default_value' => 'none',
    ];

    $form['#process'][] = [$this, 'processForm'];
    return $form;
  }

  /**
   * Render a field form element.
   *
   * @param string $field_name
   *   The machine name of the field.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The form.
   *
   * @return array
   *   The field form element.
   */
  protected function renderField($field_name, FormStateInterface $form_state, array $form) {
    $element = [];
    $widget = $this->getFormDisplay($form_state)->getRenderer($field_name);
    if ($widget) {
      $items = $this->entity->get($field_name);
      $items->filterEmptyItems();
      $element = $widget->form($items, $form, $form_state);
      $element['#access'] = $items->access('edit');
    }

    return $element;
  }

  /**
   * Process callback: assigns weights and hides extra fields.
   *
   * @see \Drupal\Core\Entity\Entity\EntityFormDisplay::buildForm()
   */
  public function processForm($element, FormStateInterface $form_state, $form) {
    $formDisplay = $this->getFormDisplay($form_state);

    // Assign the weights configured in the form display.
    foreach ($formDisplay->getComponents() as $name => $options) {
      if (isset($element[$name])) {
        $element[$name]['#weight'] = $options['weight'];
      }
    }

    // Hide extra fields.
    $extra_fields = \Drupal::service('entity_field.manager')->getExtraFields($this->entity->getEntityTypeId(), $this->entity->bundle());
    $extra_fields = $extra_fields['form'] ?? [];
    foreach ($extra_fields as $extra_field => $info) {
      if (!$formDisplay->getComponent($extra_field)) {
        $element[$extra_field]['#access'] = FALSE;
      }
    }

    $element["actions"]['#weight'] = 9999;

    return $element;
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
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this
        ->t('Save community'),
      '#description' => $this->t('After saving this community, you will be directed to create a protocol within this community.'),
      '#submit' => [
        '::submitForm',
        '::save',
      ],
    ];
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = clone $this->entity;
    /** @var \Drupal\mukurtu_protocol\Entity\Community $entity */
    $entity->setName($form_state->getValue('name'));
    $entity->setDescription($form_state->getValue('field_description'));
    $entity->setSharingSetting($form_state->getValue('field_access_mode'));
    $entity->setCommunityType($form_state->getValue('field_community_type'));

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
   * {@inheritDoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($this->entity->save()) {
      /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
      $community = $this->entity;

      // Add members/roles.
      foreach ($this->members as $member) {
        $community->addMember($member['entity'])->setRoles($member['entity'], $member['roles']);
      }

      // Redirect to the protocol creation form if the author is
      // a community manager.
      $protocolCreateUrl = Url::fromRoute('mukurtu_protocol.add_protocol_from_community', ['community' => $community->id()]);
      if ($protocolCreateUrl->access()) {
        $form_state->setRedirect('mukurtu_protocol.add_protocol_from_community', ['community' => $community->id()]);
      }
      else {
        $form_state->setRedirect('mukurtu_protocol.manage_community', ['group' => $community->id()]);
      }
    }
  }

}
