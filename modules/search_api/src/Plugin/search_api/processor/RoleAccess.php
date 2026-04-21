<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\Query\QueryInterface;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds access checks based on user roles.
 */
#[SearchApiProcessor(
  id: 'role_access',
  label: new TranslatableMarkup('Role-based access'),
  description: new TranslatableMarkup('Adds an access check based on a user\'s roles. This may be sufficient for sites where access is primarily granted or denied based on roles and permissions. For grants-based access checks on "Content" or "Comment" entities the "Content access" processor may be a suitable alternative.'),
  stages: [
    'add_properties' => 0,
    'pre_index_save' => -10,
    'preprocess_query' => -30,
  ],
)]
class RoleAccess extends ProcessorPluginBase {

  use LoggerTrait;

  /**
   * The property added for the role-based access data.
   */
  protected const ROLE_ACCESS_FIELD = 'search_api_role_access';

  /**
   * The current user service used by this plugin.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface|null
   */
  protected $currentUser;

  /**
   * The entity type manager.
   */
  protected ?EntityTypeManagerInterface $entityTypeManager = NULL;

  /**
   * The last UID assigned to a dummy account.
   *
   * @var int
   */
  protected static $lastUsedUid = PHP_INT_MAX;

  /**
   * The dummy accounts created so far, keyed by role ID.
   *
   * @var \Drupal\Core\Session\AccountInterface[]
   */
  protected static $roleDummyAccounts = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->setCurrentUser($container->get('current_user'));
    $processor->setEntityTypeManager($container->get('entity_type.manager'));
    $processor->setLogger($container->get('logger.channel.search_api'));

    return $processor;
  }

  /**
   * Retrieves the current user.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The current user.
   */
  public function getCurrentUser() {
    return $this->currentUser ?: \Drupal::currentUser();
  }

  /**
   * Sets the current user.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   *
   * @return $this
   */
  public function setCurrentUser(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
    return $this;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager ?: \Drupal::entityTypeManager();
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): static {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];
    if (!$datasource) {
      $definition = [
        'label' => $this->t('Role-based access information'),
        'description' => $this->t('Data needed to apply role-based item access'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'hidden' => TRUE,
        'is_list' => TRUE,
      ];
      $properties[static::ROLE_ACCESS_FIELD] = new ProcessorProperty($definition);
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $role_has_access = function (RoleInterface $role) use ($item) {
      $transient_account = $this->createTransientAccountWithRole($role);
      return $item->getDatasource()
        ->getItemAccessResult($item->getOriginalObject(), $transient_account)
        ->isAllowed();
    };
    $roles = $this->getEntityTypeManager()->getStorage('user_role')->loadMultiple();
    $allowed_roles = array_filter($roles, $role_has_access);
    $allowed_roles = array_map(function (RoleInterface $role) {
      return $role->id();
    }, $allowed_roles);

    $fields = $item->getFields();
    $fields = $this->getFieldsHelper()->filterForPropertyPath($fields, NULL, static::ROLE_ACCESS_FIELD);
    foreach ($fields as $field) {
      $field->setValues($allowed_roles);
    }
  }

  /**
   * Creates a transient user with the given role for access checking.
   *
   * No user entity will be created or saved.
   *
   * @param \Drupal\user\RoleInterface $role
   *   The ID of the role for which to create a user session.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   A representation of a user account with the given role.
   */
  protected function createTransientAccountWithRole(RoleInterface $role): AccountInterface {
    $role_id = $role->id();
    if (empty(static::$roleDummyAccounts[$role_id])) {
      if ($role_id === AccountInterface::ANONYMOUS_ROLE) {
        $uid = 0;
      }
      else {
        $uid = --static::$lastUsedUid;
      }
      static::$roleDummyAccounts[$role_id] = new UserSession([
        'roles' => [$role_id],
        'uid' => $uid,
      ]);
    }

    return static::$roleDummyAccounts[$role_id];
  }

  /**
   * {@inheritdoc}
   */
  public function preIndexSave() {
    $this->ensureField(NULL, static::ROLE_ACCESS_FIELD, 'string')
      ->setHidden();
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    if ($query->getOption('search_api_bypass_access')) {
      return;
    }

    $account = $query->getOption('search_api_access_account', $this->getCurrentUser());
    if (is_numeric($account)) {
      $account = User::load($account);
    }

    $role_field = $this->findField(NULL, static::ROLE_ACCESS_FIELD, 'string');
    if ($role_field) {
      $query->addCondition($role_field->getFieldIdentifier(), $account->getRoles(), 'IN');
    }
    else {
      $query->abort();
      $index = $query->getIndex();
      $this->getLogger()->warning('Role-based access checks could not be added to a search query on index %index since the required field is not available. You should re-save the index.', [
        '%index' => $index->label() ?? $index->id(),
      ]);
    }
  }

}
