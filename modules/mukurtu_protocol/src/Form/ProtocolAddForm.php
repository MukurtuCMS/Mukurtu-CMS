<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\mukurtu_protocol\Entity\Protocol;

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
   * The user IDs of the protocol stewards.
   *
   * @var int[]
   */
  protected $protocolStewards;

  /**
   * The user IDs of the protocol members.
   *
   * @var int[]
   */
  protected $protocolMembers;

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

    // Set the community relationship.
    if ($community) {
      $this->community = $community;
      $this->entity->setCommunities([$community]);
    }

    $form = parent::buildForm($form, $form_state);

    // Community name.
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Protocol Name'),
      '#size' => 60,
      '#required' => TRUE,
    ];

    // Sharing setting.
    // @todo Need to pull these options from field def.
    $form['field_access_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Sharing Protocol'),
      '#description' => $this->t('TODO: Sharing protocol helper text'),
      '#options' => [
        'strict' => $this->t('Strict'),
        'open' => $this->t('Open'),
      ],
      '#default_value' => 'strict',
    ];

    // Description.
    $form['field_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#required' => FALSE,
    ];


    // Protocol Stewards.
    $form['protocol_stewards'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Protocol Stewards'),
      '#description' => $this->t('Helper text about protocol stewards.'),
      '#target_type' => 'user',
      '#selection_handler' => 'default',
    ];

    // Protocol Members.
    $form['protocol_members'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Members'),
      '#description' => $this->t('Helper text about protocol membership.'),
      '#target_type' => 'user',
      '#selection_handler' => 'default',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions['submit_another'] = [
      '#type' => 'submit',
      '#value' => $this
        ->t('Save and create another'),
      '#submit' => [
        '::submitForm',
        '::save',
      ],
    ];

    $actions['submit_done'] = [
      '#type' => 'submit',
      '#value' => $this
        ->t('Save and view community'),
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

    // Set the memberships for the protocol.
    $stewards = $form_state->getValue('protocol_stewards');
    $this->protocolStewards = [$stewards];
    $members = $form_state->getValue('protocol_members');
    $this->protocolMembers = [$members];

    return $entity;
  }

  /**
   * {@inheritDoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($this->entity->save()) {
      /** @var \Drupal\mukurtu_protocol\Entity\Protocol $protocol */
      $protocol = $this->entity;

      // Add protocol stewards.
      /** @var \Drupal\core\Session\AccountInterface[] $stewards */
      if (!empty($this->protocolStewards)) {
        $stewards = $this->entityTypeManager->getStorage('user')->loadMultiple($this->protocolStewards);
        foreach ($stewards as $steward) {
          $protocol->addMember($steward)->setRoles($steward, ['protocol_steward']);
        }
      }

      // Add protocol members.
      /** @var \Drupal\core\Session\AccountInterface[] $members */
      if (!empty($this->protocolMembers)) {
        $members = $this->entityTypeManager->getStorage('user')->loadMultiple($this->protocolMembers);
        foreach ($members as $member) {
          $protocol->addMember($member);
        }
      }
    }
  }

  /**
   * Redirect to the owning community after save.
   */
  public function redirectToCommunity(array $form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.community.canonical', ['community' => $this->community->id()]);
  }

}
