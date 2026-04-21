<?php

namespace Drupal\search_api\ParamConverter;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\search_api\UnsavedIndexConfiguration;
use Symfony\Component\Routing\Route;

/**
 * Converts search indexes from path parameters to a temporary copy.
 *
 * This is done so that certain pages (like the "Fields" tab) can modify indexes
 * over several page requests without permanently saving the index in between.
 *
 * The code for this is largely taken from the views_ui module.
 */
class SearchApiConverter extends EntityConverter implements ParamConverterInterface {

  /**
   * The shared temporary storage factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The currently logged-in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new SearchApiConverter.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   *   The factory for the temp store object.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    $entity_repository,
    $temp_store_factory,
    $user = NULL,
  ) {
    // For backwards-compatibility, we still support passing just
    // ($entity_manager, $temp_store_factory, $user).
    if (!$user) {
      // @todo Add autowiring once we remove this BC code, see #3528045.
      @trigger_error('Constructing \Drupal\search_api\ParamConverter\SearchApiConverter with ($entity_manager, $temp_store_factory, $user) is deprecated in search_api:8.x-1.18 and will stop working in search_api:2.0.0. Pass ($entity_type_manager, $entity_repository, $temp_store_factory, $user) instead. See https://www.drupal.org/node/3164248', E_USER_DEPRECATED);
      $user = $temp_store_factory;
      $temp_store_factory = $entity_repository;
      $entity_repository = \Drupal::getContainer()->get('entity.repository');
    }
    $type_checks = [
      2 => [$entity_repository, EntityRepositoryInterface::class],
      3 => [$temp_store_factory, SharedTempStoreFactory::class],
      4 => [$user, AccountInterface::class],
    ];
    foreach ($type_checks as $i => [$object, $expected]) {
      if (!($object instanceof $expected)) {
        $actual = get_class($object);
        throw new \TypeError("Argument $i passed to Drupal\search_api\ParamConverter\SearchApiConverter::__construct() must implement interface $expected, instance of $actual given");
      }
    }

    parent::__construct($entity_type_manager, $entity_repository);

    $this->tempStoreFactory = $temp_store_factory;
    $this->currentUser = $user;
  }

  // phpcs:enable Drupal.Commenting.FunctionComment.TypeHintMissing

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    /** @var \Drupal\search_api\IndexInterface $entity */
    try {
      $storage = $this->entityTypeManager->getStorage('search_api_index');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      return NULL;
    }
    if (!($storage instanceof ConfigEntityStorageInterface)) {
      return NULL;
    }
    if (!($entity = $storage->loadOverrideFree($value))) {
      return NULL;
    }

    // Get the temp store for this variable if it needs one. Attempt to load the
    // index from the temp store, update the currently logged-in user's ID and
    // store the lock metadata.
    $store = $this->tempStoreFactory->get('search_api_index');
    $current_user_id = $this->currentUser->id() ?: session_id();
    /** @var \Drupal\search_api\IndexInterface|\Drupal\search_api\UnsavedIndexConfiguration $index */
    $index = $store->get($value);
    if ($index) {
      $index = new UnsavedIndexConfiguration($index, $store, $current_user_id);
      $index->setLockInformation($store->getMetadata($value));
      $index->setEntityTypeManager($this->entityTypeManager);
    }
    // Otherwise, create a new temporary copy of the search index.
    else {
      $index = new UnsavedIndexConfiguration($entity, $store, $current_user_id);
    }

    return $index;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    if (parent::applies($definition, $name, $route)) {
      return !empty($definition['tempstore']) && $definition['type'] === 'entity:search_api_index';
    }
    return FALSE;
  }

}
