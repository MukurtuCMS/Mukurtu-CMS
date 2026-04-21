<?php

namespace Drupal\message_subscribe_ui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\flag\FlagServiceInterface;
use Drupal\message_subscribe\SubscribersInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An entity subscriptions block.
 *
 * @Block(
 *   id = "message_subscribe_ui_block",
 *   admin_label = @Translation("Manage subscriptions"),
 *   category = @Translation("Subscriptions")
 * )
 */
class Subscriptions extends BlockBase implements FormInterface, ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The subscribers service.
   *
   * @var \Drupal\message_subscribe\SubscribersInterface
   */
  protected $subscribers;

  /**
   * Constructs the subscriptions block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param \Drupal\message_subscribe\SubscribersInterface $subscribers
   *   The subscribers service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder, SubscribersInterface $subscribers, RouteMatchInterface $route_match, AccountProxyInterface $current_user, FlagServiceInterface $flag_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->flagService = $flag_service;
    $this->formBuilder = $form_builder;
    $this->routeMatch = $route_match;
    $this->subscribers = $subscribers;
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('message_subscribe.subscribers'),
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('flag')
    );
  }

  /**
   * Helper method to retrieve the current page entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity for the current route.
   */
  protected function getCurrentEntity() {
    // Let's look up in the route object for the name of upcasted values.
    foreach ($this->routeMatch->getParameters() as $parameter) {
      if ($parameter instanceof EntityInterface) {
        return $parameter;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ((!$entity = $this->getCurrentEntity()) || !$this->hasSubscribableEntities($entity)) {
      // Not on an entity page. Ensure the block is only cached for this route.
      return [
        '#cache' => [
          'contexts' => ['user', 'url.path'],
        ],
      ];
    }

    return $this->formBuilder->getForm($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'message_subscribe_ui_block';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    // Add the entity being viewed.
    $entity = $this->getCurrentEntity();
    $entities = [$entity];
    $entities += $entity->referencedEntities();
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Manage all <a href=":url">subscriptions</a>.', [
        ':url' => Url::fromRoute('message_subscribe_ui.tab',
        ['user' => $this->currentUser->id()],
        )->toString(),
      ]),
    ];
    $form['subscriptions'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    foreach ($entities as $referenced_entity) {
      // Verify user can access the referenced entity.
      if ($referenced_entity->access('view')) {
        $flags = $this->subscribers->getFlags($referenced_entity->getEntityTypeId(), $referenced_entity->bundle(), $this->currentUser);
        if (!empty($flags)) {
          /** @var \Drupal\flag\FlagInterface $flag */
          // @todo Support multiple subscription flags per-entity if there is
          // such a use-case.
          $flag = reset($flags);
          $form['subscriptions'][$referenced_entity->getEntityTypeId()][$referenced_entity->id()] = [
            '#type' => 'checkbox',
            // @todo Determine how to use the flag label/value.
            '#title' => $this->getLabel($referenced_entity),
            '#default_value' => !empty($this->flagService->getFlagging($flag, $referenced_entity, $this->currentUser)),
            '#flags' => $flags,
            '#entity' => $referenced_entity,
          ];
        }
      }
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * Get a subscription checkbox label for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The referenced entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label for the subscription checkbox.
   */
  protected function getLabel(EntityInterface $entity) {
    $label = ($entity instanceof UserInterface) ? $entity->getDisplayName() : $entity->label();
    return $this->t('Subscribe to @label', ['@label' => $label]);
  }

  /**
   * Determine if this entity has accessible entities to subscribe to.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for subscribable entities.
   *
   * @return bool
   *   Returns TRUE if there are subscribable entities found.
   */
  protected function hasSubscribableEntities(EntityInterface $entity) {
    $entities = [$entity];
    $entities += $entity->referencedEntities();
    foreach ($entities as $referenced_entity) {
      if ($referenced_entity->access('view')) {
        $flags = $this->subscribers->getFlags($referenced_entity->getEntityTypeId(), $referenced_entity->bundle(), $this->currentUser);
        if (!empty($flags)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @todo Implement validateForm() method.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('subscriptions') as $entity_type => $entities) {
      foreach ($entities as $entity_id => $subscribe) {
        /** @var \Drupal\flag\FlagInterface[] $flags */
        $flags = $form['subscriptions'][$entity_type][$entity_id]['#flags'];
        $entity = $form['subscriptions'][$entity_type][$entity_id]['#entity'];
        foreach ($flags as $flag) {
          try {
            if ($subscribe) {
              $this->flagService->flag($flag, $entity, $this->currentUser);
            }
            else {
              $this->flagService->unflag($flag, $entity, $this->currentUser);
            }
          }
          catch (\LogicException $e) {
            // User was either already unsubscribed, or subscribed.
          }
        }
      }
    }
  }

}
