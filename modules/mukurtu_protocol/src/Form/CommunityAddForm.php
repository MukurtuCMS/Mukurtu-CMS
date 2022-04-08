<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\Element\EntityBrowserElement;

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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $currentUser = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());

    // Community name.
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Community Name'),
      '#size' => 60,
      '#required' => TRUE,
    ];

    // Description.
    $form['field_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#required' => FALSE,
    ];

    // Community Managers.
    $form['community_managers_item'] = [
      '#type' => 'item',
      '#title' => $this->t('Community Managers'),
      '#description' => $this->t('Helper text about community managers.'),
    ];

    $form['community_managers'] = [
      '#type' => 'entity_browser',
      '#cardinality' => -1,
      '#entity_browser' => 'mukurtu_community_and_protocol_user_browser',
      '#default_value' => [$currentUser],
      '#selection_mode' => EntityBrowserElement::SELECTION_MODE_EDIT,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this
        ->t('Create Community'),
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

    // Grab the community managers.
    $managers = $form_state->getValue('community_managers');
    $this->communityManagers = !empty($managers['entities']) ? $managers['entities'] : [];

    return $entity;
  }

  /**
   * {@inheritDoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($this->entity->save()) {
      /** @var \Drupal\mukurtu_protocol\Entity\Community $community */
      $community = $this->entity;

      // Add community managers here.
      foreach ($this->communityManagers as $manager) {
        $community->addMember($manager)->setRoles($manager, ['community_manager']);
      }

      // Redirect to the protocol creation form if the author is
      // a community manager.
      $protocolCreateUrl = Url::fromRoute('mukurtu_protocol.add_protocol_from_community', ['communityID' => $community->id()]);
      if ($protocolCreateUrl->access()) {
        $form_state->setRedirect('mukurtu_protocol.add_protocol_from_community', ['communityID' => $community->id()]);
      }
      else {
        $form_state->setRedirect('entity.community.canonical', ['community' => $community->id()]);
      }
    }
  }

}
