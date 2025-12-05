<?php

declare(strict_types=1);

namespace Drupal\mukurtu_content_warnings\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;
use Drupal\mukurtu_core\Entity\PeopleInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Adds media content warnings to media entities.
 */
class MukurtuMediaContentWarnings {

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
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AdminContext $adminContext,
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
      '#warnings' => array_merge($people_warnings, $taxonomy_warnings),
    ]);
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
    $people_terms = $this->getDeceasedPeopleTerms($entity);
    if (empty($people_terms)) {
      return [];
    }
    $names = array_map(fn(TermInterface $people_term) => $people_term->getName(), $people_terms);
    if (count($people_terms) > 1) {
      $warning_template = $this->contentWarningSettings->get('people_warnings.warning_multiple');
      $warning_text = str_replace("[names]", implode(' ', $names), $warning_template);
    }
    else {
      $warning_template = $this->contentWarningSettings->get('people_warnings.warning_single');
      $warning_text = str_replace("[name]", implode(' ', $names), $warning_template);
    }
    // Incorporate cacheability metadata from the people terms.
    $build = [
      '#theme' => 'mukurtu_content_warning',
      '#warning' => $warning_text,
    ];
    $build = array_reduce($people_terms, function ($carry, TermInterface $people_term) {
      CacheableMetadata::createFromRenderArray($carry)
        ->addCacheableDependency($people_term)
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
   * Get the people terms associated with the entity that have deceased content.
   *
   * @param \Drupal\mukurtu_core\Entity\PeopleInterface $entity
   *   A content entity that has people associated with it.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The people terms associated with the entity that have deceased content.
   */
  protected function getDeceasedPeopleTerms(PeopleInterface $entity): array {
    $names = [];
    $people_terms = $entity->getPeopleTerms();
    if (empty($people_terms)) {
      return $names;
    }

    foreach ($people_terms as $person) {
      if ($this->hasDeceasedPersonContent($person)) {
        $names[] = $person;
      }
    }
    return $names;
  }

  /**
   * Check if the given term has deceased content.
   *
   * @param \Drupal\taxonomy\TermInterface $entity
   *   The term to check.
   *
   * @return bool
   *   TRUE if the term has deceased content, FALSE otherwise.
   */
  protected function hasDeceasedPersonContent(TermInterface $entity): bool {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', 'person')
      ->condition('field_deceased', TRUE)
      ->condition('field_other_names.entity:taxonomy_term.tid', $entity->id())
      ->accessCheck(FALSE);
    return (bool) $query->count()->execute();
  }

}
