<?php

declare(strict_types=1);

namespace Drupal\mukurtu_content_warnings\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_core\Entity\PeopleInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Adds media content warnings to media entities.
 */
class MukurtuMediaContentWarnings {

  use StringTranslationTrait;

  /**
   * The Mukurtu content warnings settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $contentWarningSettings;

  /**
   * Constructs a new MukurtuMediaContentWarnings object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   The admin context.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AdminContext $adminContext,
    protected EntityRepositoryInterface $entityRepository,
  ) {
    $this->contentWarningSettings = $configFactory->get('mukurtu_content_warnings.settings');
  }

  /**
   * Implements hook_ENTITY_TYPE_view_alter().
   */
  #[Hook('media_view_alter')]
  public function mediaViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof PeopleInterface) {
      return;
    }
    // If this is an admin route, or the view mode is either token or
    // media_library, don't display the content warnings.
    if ($this->adminContext->isAdminRoute() || in_array($display->getMode(), [
      'token',
      'media_library',
    ])) {
      return;
    }
    // Always declare a cache dependency on the settings config so that if a
    // view mode is re-enabled the cached render for that mode is automatically
    // invalidated (without a manual cache clear).
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($this->contentWarningSettings)
      ->applyTo($build);

    // null = not yet configured = all modes allowed (backwards compatible default).
    // [] = admin explicitly unchecked all modes = no warnings anywhere.
    $allowed_modes = $this->contentWarningSettings->get('warning_view_modes');
    if ($allowed_modes !== NULL && !in_array($build['#view_mode'], $allowed_modes)) {
      return;
    }
    // Add cache dependency on every people term associated with this entity so
    // that changes to any person (e.g. marked as deceased) immediately
    // invalidate this render, even when no warning was previously shown.
    foreach ($entity->getPeopleTerms() as $term) {
      CacheableMetadata::createFromRenderArray($build)
        ->addCacheableDependency($term)
        ->applyTo($build);
    }
    if ($entity->hasField('field_contributor')) {
      foreach ($entity->get('field_contributor')->referencedEntities() as $term) {
        CacheableMetadata::createFromRenderArray($build)
          ->addCacheableDependency($term)
          ->applyTo($build);
      }
    }
    $build['media_content_warnings'] = $this->buildMediaContentWarnings($entity);
  }

  /**
   * Implements template_preprocess_mukurtu_content_warnings().
   */
  #[Hook('preprocess_mukurtu_content_warnings')]
  public function preprocessMukurtuContentWarnings(&$variables): void {
    $variables['#attached']['library'][] = 'mukurtu_content_warnings/content-warnings';
  }

  /**
   * Build a render array of media content warnings for the given entity.
   *
   * @param \Drupal\mukurtu_core\Entity\PeopleInterface $entity
   *   A content entity that has people associated with it.
   *
   * @return array
   *   Render array of media content warnings.
   */
  protected function buildMediaContentWarnings(PeopleInterface $entity): array {
    $build = [];
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($this->contentWarningSettings)->addCacheableDependency($entity)
       // For deceased content warnings, they are shown based on the presence of
       // a person node with the same people tag as the media item, so we're
       // potentially dependent on any change to a person node.
      ->addCacheTags(['node_list:person'])
      ->applyTo($build);
    $people_warnings = $this->buildPeopleWarnings($entity);
    $taxonomy_warnings = $this->buildTaxonomyWarnings($entity);
    if (empty($people_warnings) && empty($taxonomy_warnings)) {
      return $build;
    }
    return array_merge($build, [
      '#theme' => 'mukurtu_content_warnings',
      '#media' => $entity,
      '#warnings' => $this->removeDuplicateWarnings(array_merge($people_warnings, $taxonomy_warnings)),
    ]);
  }

  /**
   * Remove warnings whose text matches a warning earlier in the list.
   *
   * Two media tags can be configured with the same warning text, and showing
   * the same sentence twice only makes the overlay longer.
   *
   * @param array $warnings
   *   Render arrays of individual warnings.
   *
   * @return array
   *   The warnings, keeping the first of each distinct text.
   */
  protected function removeDuplicateWarnings(array $warnings): array {
    $seen = [];
    $unique = [];
    foreach ($warnings as $warning) {
      $text = trim((string) $warning['#warning']);
      if (isset($seen[$text])) {
        continue;
      }
      $seen[$text] = TRUE;
      $unique[] = $warning;
    }
    return $unique;
  }

  /**
   * Build a render array of people warnings for the given entity.
   *
   * @param \Drupal\mukurtu_core\Entity\PeopleInterface $entity
   *   A content entity that has people associated with it.
   *
   * @return array
   *   Render array of people warnings.
   */
  protected function buildPeopleWarnings(PeopleInterface $entity): array {
    if (!$this->contentWarningSettings->get('people_warnings.enabled')) {
      return [];
    }
    $person_nodes = $this->getDeceasedPersonNodes($entity);
    if (empty($person_nodes)) {
      return [];
    }
    $names = array_map(fn(NodeInterface $node) => $this->entityRepository->getTranslationFromContext($node)->label(), $person_nodes);
    if (count($person_nodes) > 1) {
      $warning_template = $this->contentWarningSettings->get('people_warnings.warning_multiple');
      // The names are plain text and Twig escapes the warning when it prints
      // it, so decode the escaping the placeholders add.
      $list = array_shift($names);
      foreach ($names as $name) {
        $list = Html::decodeEntities((string) $this->t('@list, @name', [
          '@list' => $list,
          '@name' => $name,
        ], ['context' => 'Content warning list of names']));
      }
      $warning_text = str_replace("[names]", $list, $warning_template);
    }
    else {
      $warning_template = $this->contentWarningSettings->get('people_warnings.warning_single');
      $warning_text = str_replace("[name]", reset($names), $warning_template);
    }
    // Incorporate cacheability metadata from the person nodes.
    $build = [
      '#theme' => 'mukurtu_content_warning',
      '#warning' => $warning_text,
    ];
    $build = array_reduce($person_nodes, function ($carry, NodeInterface $node) {
      CacheableMetadata::createFromRenderArray($carry)
        ->addCacheableDependency($node)
        ->applyTo($carry);
      return $carry;
    }, $build);

    return [$build];
  }

  /**
   * Build a render array of taxonomy warnings for the given entity.
   *
   * @param \Drupal\mukurtu_core\Entity\PeopleInterface $entity
   *   A content entity that has people associated with it.
   *
   * @return array
   *   Render array of taxonomy warnings.
   */
  protected function buildTaxonomyWarnings(PeopleInterface $entity): array {
    $taxonomy_warnings = $this->contentWarningSettings->get('taxonomy_warnings');
    if (!$taxonomy_warnings) {
      return [];
    }
    // If we have taxonomy warnings in config, we need to iterate through them
    // all and see if the media item's media tags field matches any of the terms
    //we see here.
    $media_tags = $entity->get('field_media_tags')->referencedEntities();
    // Make a quick list of tids that the media tags field has.
    $tids = array_map(fn(TermInterface $media_tag) => $media_tag->id(), $media_tags);

    $warnings = [];
    foreach ($taxonomy_warnings as $warning) {
      if (in_array($warning['term'], $tids)) {
        $warnings[] = [
          '#theme' => 'mukurtu_content_warning',
          '#warning' => $warning['warning'],
        ];
      }
    }
    return $warnings;
  }

  /**
   * Get the deceased person nodes associated with the entity's people terms.
   *
   * @param \Drupal\mukurtu_core\Entity\PeopleInterface $entity
   *   A content entity that has people associated with it.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Person nodes marked deceased that reference the entity's people terms.
   */
  protected function getDeceasedPersonNodes(PeopleInterface $entity): array {
    $people_terms = $entity->getPeopleTerms();
    $contributor_terms = $entity->hasField('field_contributor')
      ? $entity->get('field_contributor')->referencedEntities()
      : [];
    $all_terms = array_merge($people_terms, $contributor_terms);
    if (empty($all_terms)) {
      return [];
    }
    $tids = array_map(fn(TermInterface $term) => $term->id(), $all_terms);
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $nids = $query->condition('type', 'person')
      ->condition('field_deceased', TRUE)
      ->condition('field_other_names.entity:taxonomy_term.tid', $tids, 'IN')
      ->accessCheck(FALSE)
      ->execute();
    if (empty($nids)) {
      return [];
    }
    return $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
  }

}
