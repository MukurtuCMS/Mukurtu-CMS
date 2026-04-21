<?php

namespace Drupal\search_api\Plugin\views\argument;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a contextual filter searching through all indexed taxonomy fields.
 *
 * Note: The plugin annotation below is not misspelled. Due to dependency
 * problems, the plugin is not defined here but in
 * search_api_views_plugins_argument_alter().
 *
 * @ingroup views_argument_handlers
 *
 * ViewsArgument("search_api_term")
 *
 * @see search_api_views_plugins_argument_alter()
 */
class SearchApiTerm extends SearchApiStandard {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $plugin */
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $plugin->setEntityRepository($container->get('entity.repository'));
    $plugin->setTermStorage($container->get('entity_type.manager')
      ->getStorage('taxonomy_term'));

    return $plugin;
  }

  /**
   * Retrieves the entity repository.
   *
   * @return \Drupal\Core\Entity\EntityRepositoryInterface
   *   The entity repository.
   */
  public function getEntityRepository() {
    return $this->entityRepository ?: \Drupal::service('entity.repository');
  }

  /**
   * Sets the entity repository.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   *
   * @return $this
   */
  public function setEntityRepository(EntityRepositoryInterface $entity_repository) {
    $this->entityRepository = $entity_repository;
    return $this;
  }

  /**
   * Retrieves the term storage.
   *
   * @return \Drupal\taxonomy\TermStorageInterface
   *   The term storage.
   */
  public function getTermStorage() {
    return $this->termStorage ?: \Drupal::service('entity_type.manager')
      ->getStorage('taxonomy_term');
  }

  /**
   * Sets the term storage.
   *
   * @param \Drupal\taxonomy\TermStorageInterface $term_storage
   *   The term storage.
   *
   * @return $this
   */
  public function setTermStorage(TermStorageInterface $term_storage) {
    $this->termStorage = $term_storage;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function title() {
    if (!empty($this->argument)) {
      $this->fillValue();
      $terms = [];
      foreach ($this->value as $tid) {
        $taxonomy_term = $this->getTermStorage()->load($tid);
        if ($taxonomy_term) {
          $terms[] = $this->getEntityRepository()
            ->getTranslationFromContext($taxonomy_term)
            ->label();
        }
      }

      return $terms ? implode(', ', $terms) : $this->argument;
    }
    else {
      return $this->argument;
    }
  }

}
