<?php

namespace Drupal\mukurtu_protocol\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleStorageInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\Session\AccountInterface;
use Drupal\og\Entity\OgRole;
use Drupal\og\OgRoleManagerInterface;
use Drupal\og\MembershipManagerInterface;

/**
 * Access plugin that provides permission-based access control.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "site_and_mukurtu_role",
 *   title = @Translation("Site and Mukurtu Role"),
 *   help = @Translation("Access will be granted to users with the specified site or Mukurtu community/protocol role.")
 * )
 */
class SiteAndMukurtuRole extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

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
   * Constructs a Role object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\user\RoleStorageInterface $role_storage
   *   The role storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RoleStorageInterface $role_storage, OgRoleManagerInterface $role_manager, MembershipManagerInterface $membership_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->roleStorage = $role_storage;
    $this->roleManager = $role_manager;
    $this->membershipManager = $membership_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('user_role'),
      $container->get('og.role_manager'),
      $container->get('og.membership_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    // Check site roles first.
    $requiredRoles['site'] = array_filter($this->options['site-role']);
    if (!empty($requiredRoles['site'])) {
      if (!empty(array_intersect($requiredRoles['site'], $account->getRoles()))) {
        return TRUE;
      }
    }

    // Get required community and protocol roles.
    $requiredRoles['community'] = array_filter($this->options['community-role']);
    $requiredRoles['protocol'] = array_filter($this->options['protocol-role']);

    // None required and the user already passed the site role check.
    if (empty($requiredRoles['community']) && empty($requiredRoles['protocol'])) {
      return TRUE;
    }

    // Some required, user needs to have at least 1 of listed roles.
    $memberships = $this->membershipManager->getMemberships($account->id());
    foreach (['community', 'protocol'] as $type) {
      foreach ($memberships as $membership) {
        if(!empty(array_intersect($requiredRoles[$type], $membership->getRolesIds()))) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    //$route->setRequirement('_custom_access', 'site_and_mukurtu_role.access_handler::access');
    $allRoleOptions = [];
    if ($this->options['site-role']) {
      $allRoleOptions = array_merge($allRoleOptions, $this->options['site-role']);
    }
    if ($this->options['community-role']) {
      $allRoleOptions = array_merge($allRoleOptions, $this->options['community-role']);
    }
    if ($this->options['protocol-role']) {
      $allRoleOptions = array_merge($allRoleOptions, $this->options['protocol-role']);
    }
    $route->setRequirement('_mukurtu_role', (string) implode('+', $allRoleOptions));
  }

  public function summaryTitle() {
    $count = count($this->options['site-role']);
    $count += count($this->options['community-role']);
    $count += count($this->options['protocol-role']);
    if ($count < 1) {
      return $this->t('No role(s) selected');
    }

    return $this->formatPlural($count, '1 role selected', '@count roles selected', ['@count' => $count]);
  }

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['site-role'] = ['default' => []];
    $options['community-role'] = ['default' => []];
    $options['protocol-role'] = ['default' => []];

    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $roles = Role::loadMultiple();
    $role_names = array_map(function ($item) {
      return $item->label();
    }, $roles);
    $form['site-role'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Site Role'),
      '#default_value' => $this->options['site-role'],
      '#options' => $role_names,
      '#description' => $this->t('Only the checked roles will be able to access this display.'),
    ];


    foreach (['community', 'protocol'] as $type) {
      $roles = $this->roleManager->getRolesByBundle($type, $type);
      $options = [];
      /** @var \Drupal\og\Entity\OgRole $role */
      foreach ($roles as $role) {
        $options[$role->id()] = $role->label();
      }

      $form["$type-role"] = [
        '#type' => 'checkboxes',
        '#title' => $type == 'community' ? $this->t('Community Role') : $this->t('Protocol Role'),
        '#default_value' => $this->options["$type-role"],
        '#options' => array_map('\Drupal\Component\Utility\Html::escape', $options),
        '#description' => $this->t('Only the checked roles will be able to access this display.'),
      ];
    }

  }

  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    $count = 0;
    foreach (['site-role', 'community-role', 'protocol-role'] as $optionType) {
      $role = $form_state->getValue(['access_options', $optionType]);
      $role = array_filter($role);
      $count += count($role);
      $form_state->setValue(['access_options', $optionType], $role);
    }

    if ($count < 1) {
      $form_state->setError($form['site-role'], $this->t('You must select at least one role.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    foreach (array_keys($this->options['site-role']) as $rid) {
      if ($role = $this->roleStorage->load($rid)) {
        $dependencies[$role->getConfigDependencyKey()][] = $role->getConfigDependencyName();
      }
    }

    // Community/Protocol roles.
    foreach (['community', 'protocol'] as $type) {
      foreach (array_keys($this->options["$type-role"]) as $rid) {
        if ($role = OgRole::getRole($type, $type, $rid)) {
          /** @var \Drupal\og\Entity\OgRole $role */
          $dependencies[$role->getConfigDependencyKey()][] = $role->getConfigDependencyName();
        }
      }
    }

    return $dependencies;
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
    return ['user.roles'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}

