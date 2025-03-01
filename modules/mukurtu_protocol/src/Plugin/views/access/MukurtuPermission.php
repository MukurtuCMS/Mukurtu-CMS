<?php

namespace Drupal\mukurtu_protocol\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_protocol\Access\MukurtuPermissionAccessCheck;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgRoleManagerInterface;
use Drupal\og\PermissionManagerInterface;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\RoleStorageInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that provides permission-based access control.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "mukurtu_permission",
 *   title = @Translation("Site/Mukurtu Permission(s)"),
 *   help = @Translation("Access will be granted to users with the specified site or Mukurtu permission(s).")
 * )
 */
class MukurtuPermission extends AccessPluginBase implements CacheableDependencyInterface {
  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * The site wide permission handler.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected $permissionHandler;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The role storage.
   *
   * @var \Drupal\user\RoleStorageInterface
   */
  protected $roleStorage;

  /**
   * The OG role manager.
   *
   * @var \Drupal\og\OgRoleManagerInterface
   */
  protected $roleManager;

  /**
   * The OG group membership manager.
   *
   * @var \Drupal\og\MembershipManagerInterface
   */
  protected $membershipManager;

  /**
   * The permission manager.
   *
   * @var \Drupal\og\PermissionManagerInterface
   */
  protected $permissionManager;

  /**
   * The Mukurtu permission access check.
   *
   * @var \Drupal\mukurtu_protocol\Access\MukurtuPermissionAccessCheck
   */
  protected $mukurtuPermissionAccessCheck;

  /**
   * Constructs a Role object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\user\PermissionHandlerInterface $permission_handler
   *   The site wide permission handler.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\user\RoleStorageInterface $role_storage
   *   The role storage.
   * @param \Drupal\og\OgRoleManagerInterface $role_manager
   *   The OG role manager.
   * @param \Drupal\og\MembershipManagerInterface $membership_manager
   *   The OG membership manager.
   * @param \Drupal\og\PermissionManagerInterface $permission_manager
   *   The OG permission manager.
   * @param \Drupal\mukurtu_protocol\Access\MukurtuPermissionAccessCheck $mukurtu_permission_access_check
   *   The '_mukurtu_permission' access check service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PermissionHandlerInterface $permission_handler, ModuleHandlerInterface $module_handler, RoleStorageInterface $role_storage, OgRoleManagerInterface $role_manager, MembershipManagerInterface $membership_manager, PermissionManagerInterface $permission_manager, MukurtuPermissionAccessCheck $mukurtu_permission_access_check) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->permissionHandler = $permission_handler;
    $this->moduleHandler = $module_handler;
    $this->roleStorage = $role_storage;
    $this->roleManager = $role_manager;
    $this->membershipManager = $membership_manager;
    $this->permissionManager = $permission_manager;
    $this->mukurtuPermissionAccessCheck = $mukurtu_permission_access_check;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user.permissions'),
      $container->get('module_handler'),
      $container->get('entity_type.manager')->getStorage('user_role'),
      $container->get('og.role_manager'),
      $container->get('og.membership_manager'),
      $container->get('og.permission_manager'),
      $container->get('access_check.user.mukurtu_permission'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['site'] = ['default' => []];
    $options['community'] = ['default' => []];
    $options['protocol'] = ['default' => []];
    $options['conjunction'] = ['default' => 'or'];
    return $options;
  }

  protected function getPermissions() {
    $site = array_map(fn ($p) => 'site:' . $p, $this->options['site']);
    $community = array_map(fn ($p) => 'community:' . $p, $this->options['community']);
    $protocol = array_map(fn ($p) => 'protocol:' . $p, $this->options['protocol']);
    return array_merge($site, $community, $protocol);
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $conjunction = $this->options['conjunction'] === 'or' ? 'OR' : 'AND';
    return $this->mukurtuPermissionAccessCheck->hasMukurtuPermissions($account, $this->getPermissions(), $conjunction);
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $conjunction = $this->options['conjunction'] === 'or' ? '+' : ',';
    $permissionString = implode($conjunction, $this->getPermissions());
    $route->setRequirement('_mukurtu_permission', $permissionString);
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    $site = count($this->options['site']);
    $community = count($this->options['community']);
    $protocol = count($this->options['protocol']);
    $conjunction = $this->options['conjunction'];

    $options = [
      '@site' => $site,
      '@community' => $community,
      '@protocol' => $protocol,
    ];
    if ($conjunction === 'or') {
      return $this->t('Any of @site site, @community community, @protocol protocol permissions', $options);
    }
    return $this->t('All of @site site, @community community, @protocol protocol permissions', $options);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Get list of site permissions.
    $perms = [];
    $permissions = $this->permissionHandler->getPermissions();
    foreach ($permissions as $perm => $perm_item) {
      $provider = $perm_item['provider'];
      $display_name = $this->moduleHandler->getName($provider);
      $perms[$display_name][$perm] = strip_tags($perm_item['title']);
    }

    $form['site'] = [
      '#type' => 'select',
      '#options' => $perms,
      '#multiple' => TRUE,
      '#title' => $this->t('Site Permissions'),
      '#default_value' => $this->options['site'],
      '#description' => $this->t('Select one or more site permissions.'),
      '#size' => 10,
    ];

    // Community Permissions.
    $perms = [];
    $community_permissions = $this->permissionManager->getDefaultGroupPermissions('community', 'community');
    foreach ($community_permissions as $perm => $perm_item) {
      $perms[$perm] = strip_tags($perm_item->getTitle());
    }
    $form['community'] = [
      '#type' => 'select',
      '#options' => $perms,
      '#multiple' => TRUE,
      '#title' => $this->t('Community Permissions'),
      '#default_value' => $this->options['community'],
      '#description' => $this->t('Select one or more community permissions.'),
      '#size' => 9,
    ];

    // Protocol Permissions.
    $perms = [];
    $community_permissions = $this->permissionManager->getDefaultGroupPermissions('protocol', 'protocol');
    foreach ($community_permissions as $perm => $perm_item) {
      $perms[$perm] = strip_tags($perm_item->getTitle());
    }
    $form['protocol'] = [
      '#type' => 'select',
      '#options' => $perms,
      '#multiple' => TRUE,
      '#title' => $this->t('Cultural Protocol Permissions'),
      '#default_value' => $this->options['protocol'],
      '#description' => $this->t('Select one or more cultural protocol permissions.'),
      '#size' => 13,
    ];

    $form['conjunction'] = [
      '#type' => 'select',
      '#options' => ['and' => $this->t('Require all selected permissions'), 'or' => $this->t('Require any selected permission')],
      '#title' => $this->t('Multiple Permission Handling'),
      '#default_value' => $this->options['conjunction'] ?? 'or',
      '#description' => $this->t('Require one or all permissions.'),
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    $count = 0;
    foreach (['site', 'community', 'protocol'] as $permType) {
      $count += count($form_state->getValue(['access_options', $permType]));
    }
    if ($count < 1) {
      $form_state->setError($form['site'], $this->t('You must select at least one permission.'));
      $form_state->setError($form['community'], '');
      $form_state->setError($form['protocol'], '');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user.permissions'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
