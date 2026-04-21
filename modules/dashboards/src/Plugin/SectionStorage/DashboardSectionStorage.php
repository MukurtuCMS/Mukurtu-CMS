<?php

namespace Drupal\dashboards\Plugin\SectionStorage;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\dashboards\Entity\Dashboard;
use Drupal\layout_builder\Entity\SampleEntityGeneratorInterface;
use Drupal\layout_builder\Plugin\SectionStorage\SectionStorageBase;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Dashboard section storage.
 *
 * @SectionStorage(
 *   id = "dashboards",
 *   weight = 10,
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity:dashboard")
 *   },
 *   handles_permission_check = TRUE,
 * )
 *
 * @package Drupal\dashboards\Plugin\SectionStorage
 */
class DashboardSectionStorage extends SectionStorageBase implements ContainerFactoryPluginInterface, ThirdPartySettingsInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  /**
   * The sample entity generator.
   *
   * @var \Drupal\layout_builder\Entity\SampleEntityGeneratorInterface
   */
  protected $sampleEntityGenerator;

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * UserDataInterface definition.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('layout_builder.sample_entity_generator'),
      $container->get('current_user'),
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('user.data')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EntityTypeBundleInfoInterface $entity_bundle_info,
    SampleEntityGeneratorInterface $sample_entity_generator,
    AccountInterface $current_user,
    SectionStorageManagerInterface $section_storage_manager,
    UserDataInterface $user_data,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityBundleInfo = $entity_bundle_info;
    $this->sampleEntityGenerator = $sample_entity_generator;
    $this->account = $current_user;
    $this->sectionStorageManager = $section_storage_manager;
    $this->userData = $user_data;
  }

  /**
   * Gets the dashboard entity.
   *
   * @return \Drupal\dashboards\Entity\Dashboard
   *   Dashboard entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getDashboard() {
    return $this->getContextValue(Dashboard::CONTEXT_TYPE);
  }

  /**
   * {@inheritdoc}
   */
  protected function getSectionList() {
    return $this->getDashboard();
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageId() {
    return $this->getDashboard()->id();
  }

  /**
   * Gets the dashboard entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Dashboard entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getDefaultDashboard(): EntityInterface {
    return $this->entityTypeManager->getStorage('dashboard')->load($this->getDashboard()->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getSectionListFromId($id) {
    @trigger_error('\Drupal\layout_builder\SectionStorageInterface::getSectionListFromId() is deprecated in drupal:8.7.0 and is removed from drupal:9.0.0. The section list should be derived from context. See https://www.drupal.org/node/3016262', E_USER_DEPRECATED);
    return $this->entityTypeManager->getStorage('dashboard')->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function buildRoutes(RouteCollection $collection) {
    $requirements = [];
    $this->buildLayoutRoutes(
      $collection,
      $this->getPluginDefinition(),
      'dashboards/{dashboard}/layout',
      [
        'parameters' => [
          'dashboard' => [
            'type' => 'entity:dashboard',
          ],
        ],
      ],
      $requirements,
      // This can't be an admin route because seven decides to ditch all
      // contextual links on blocks. See issue
      // https://www.drupal.org/project/drupal/issues/2487025
      ['_admin_route' => FALSE],
      '',
      'dashboard'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectUrl() {
    return Url::fromRoute('entity.dashboard.canonical', [
      'dashboard' => $this->getDashboard()->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutBuilderUrl($rel = 'view') {
    return Url::fromRoute("layout_builder.{$this->getStorageType()}.{$rel}", [
      'dashboard' => $this->getDashboard()->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function extractIdFromRoute($value, $definition, $name, array $defaults) {
    throw new \Exception(new TranslatableMarkup('This method is deprecated in 8.7.0'));
  }

  /**
   * {@inheritdoc}
   */
  public function deriveContextsFromRoute($value, $definition, $name, array $defaults) {
    $contexts = [];

    $id = !empty($value) ? $value : (!empty($defaults['dashboard']) ? $defaults['dashboard'] : NULL);
    if ($id && ($entity = $this->entityTypeManager->getStorage('dashboard')->load($id))) {
      $contexts[Dashboard::CONTEXT_TYPE] = EntityContext::fromEntity($entity);
    }
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getDashboard()->label();
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    return $this->getDashboard()->save();
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(RefinableCacheableDependencyInterface $cacheability) {
    $entity = $this->getContextValue(Dashboard::CONTEXT_TYPE);
    if (!$entity->isOverridden()) {
      $cacheability->addCacheableDependency($this);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function access($operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$account) {
      $account = $this->account;
    }
    $result = AccessResult::allowedIfHasPermission($account, 'administer dashboards');
    if ($return_as_object) {
      return $result;
    }
    return $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function setThirdPartySetting($module, $key, $value) {
    $this->getDashboard()->setThirdPartySetting($module, $key, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getThirdPartySetting($module, $key, $default = NULL) {
    return $this->getDashboard()->getThirdPartySetting($module, $key, $default);
  }

  /**
   * {@inheritdoc}
   */
  public function getThirdPartySettings($module) {
    return $this->getDashboard()->getThirdPartySettings($module);
  }

  /**
   * {@inheritdoc}
   */
  public function unsetThirdPartySetting($module, $key) {
    $this->getDashboard()->unsetThirdPartySetting($module, $key);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getThirdPartyProviders() {
    return $this->getDashboard()->getThirdPartyProviders();
  }

}
