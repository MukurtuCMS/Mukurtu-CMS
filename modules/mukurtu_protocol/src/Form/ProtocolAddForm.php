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
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $community = NULL) {
    $this->setModuleHandler($this->moduleHandler);

    // Set the community relationship.
    if ($community) {
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


    // Community Managers.
/*     $form['community_managers'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Community Managers'),
      '#description' => $this->t('Helper text about community managers.'),
      '#target_type' => 'user',
      '#selection_handler' => 'default',
    ]; */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this
        ->t('Create Protocol'),
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
    /** @var \Drupal\mukurtu_protocol\Entity\Protocol $entity */
    $entity->setName($form_state->getValue('name'));
    $entity->setDescription($form_state->getValue('field_description'));
    $entity->setSharingSetting($form_state->getValue('field_access_mode'));

    // @todo set the memberships for the protocol.

    return $entity;
  }

  /**
   * {@inheritDoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($this->entity->save()) {
      /** @var \Drupal\mukurtu_protocol\Entity\Protocol $community */
      $protocol = $this->entity;

      // @todo Add community managers here.
      /** @var \Drupal\core\Session\AccountInterface[] $managers */
/*       $managers = $this->entityTypeManager->getStorage('user')->loadMultiple($this->communityManagers);
      foreach ($managers as $manager) {
        $community->addMember($manager, ['community_manager']);
      } */

      //$form_state->setRedirect('entity.community.canonical', ['community' => $this->community->id()]);
    }
  }

}
