<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Extension\ProceduralCall;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a related content preview to the sidebar of full item pages.
 *
 * Each content type's setting on the Related content settings page picks
 * what the preview shows: nothing, a link to the related content section, or
 * a collapsible list of thumbnails. The full related content section always
 * stays in the main content.
 *
 * @see \Drupal\mukurtu_core\Form\MukurtuSettingsForm
 */
class RelatedContentPreviewHooks implements ContainerInjectionInterface {

  /**
   * The preview is turned off.
   */
  public const MODE_OFF = 'off';

  /**
   * A link that jumps to the related content section.
   */
  public const MODE_JUMP_LINK = 'jump_link';

  /**
   * A collapsible list of thumbnails and titles.
   */
  public const MODE_THUMBNAILS = 'thumbnails';

  /**
   * The most items the thumbnail list shows.
   */
  public const THUMBNAIL_LIMIT = 5;

  /**
   * Where the preview goes in each content type's sidebar.
   *
   * 'group' is the field group the preview joins, or NULL when the sidebar
   * has no group and the template prints the preview itself. 'after' lists
   * the fields the preview follows. With none of them shown, the preview
   * goes first in the group.
   */
  public const PLACEMENT = [
    'digital_heritage' => [
      'group' => 'group_sidebar_mukurtu_fields',
      'after' => ['field_title', 'field_category'],
    ],
    'dictionary_word' => [
      'group' => 'group_metadata',
      'after' => ['field_dictionary_word_language'],
    ],
    'collection' => [
      'group' => 'group_sidebar_mukurtu_fields',
      'after' => [],
    ],
    'word_list' => [
      'group' => NULL,
      'after' => [],
    ],
  ];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityRepositoryInterface $entityRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('entity.repository'),
    );
  }

  /**
   * Returns the preview mode a content type is set to.
   */
  public static function getMode(StorableConfigBase $config, string $bundle): string {
    return $config->get('related_content_preview.' . $bundle) ?? self::MODE_OFF;
  }

  /**
   * Returns the id of the related content section on a node's page.
   *
   * Community records can show several full nodes on one page, so the id
   * includes the node ID.
   */
  public static function targetId(NodeInterface $node): string {
    return 'related-content-' . $node->id();
  }

  /**
   * Implements hook_entity_view_alter().
   *
   * Runs after field_group has attached its groups, so the preview can join
   * the sidebar group.
   */
  #[Hook('entity_view_alter', order: new OrderAfter(
    modules: ['field_group'],
    classesAndMethods: [[ProceduralCall::class, 'field_group_entity_view_alter']],
  ))]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof NodeInterface || ($build['#view_mode'] ?? NULL) !== 'full' || !isset(self::PLACEMENT[$entity->bundle()])) {
      return;
    }

    $config = $this->configFactory->get('mukurtu.settings');
    // Turning the preview on or off must reach pages already cached.
    $cacheability = CacheableMetadata::createFromRenderArray($build)->addCacheableDependency($config);
    $mode = static::getMode($config, $entity->bundle());

    if ($mode === self::MODE_OFF || !$entity->hasField('field_all_related_content') || empty($build['field_all_related_content'])) {
      $cacheability->applyTo($build);
      return;
    }

    // Items that start referencing this one change the list too.
    $cacheability->addCacheTags(['node_list']);
    $items = [];
    foreach ($entity->get('field_all_related_content')->referencedEntities() as $related) {
      $access = $related->access('view', NULL, TRUE);
      $cacheability->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $items[] = $this->entityRepository->getTranslationFromContext($related);
      }
    }
    if (!$items) {
      $cacheability->applyTo($build);
      return;
    }

    $preview = [
      '#theme' => 'mukurtu_related_content_preview',
      '#mode' => $mode,
      '#count' => count($items),
      '#target' => static::targetId($entity),
      '#items' => [],
    ];
    if ($mode === self::MODE_THUMBNAILS) {
      foreach (array_slice($items, 0, self::THUMBNAIL_LIMIT) as $related) {
        $url = $related->toUrl()->toString(TRUE);
        $cacheability->addCacheableDependency($related)->addCacheableDependency($url);
        $thumbnail = [];
        if ($related->hasField('field_representative_media') && !$related->get('field_representative_media')->isEmpty()) {
          $thumbnail = $related->get('field_representative_media')->view([
            'type' => 'entity_reference_entity_view',
            'label' => 'hidden',
            'settings' => ['view_mode' => 'carousel_thumbnail'],
          ]);
        }
        $preview['#items'][] = [
          'title' => $related->label(),
          'url' => $url->getGeneratedUrl(),
          'thumbnail' => $thumbnail,
        ];
      }
    }

    $this->place($build, $preview, $entity->bundle());
    $build['related_content_preview'] = $preview;

    // The field's wrapper takes the id the preview links to. The tabindex
    // lets the jump move keyboard focus to it.
    $build['field_all_related_content']['#attributes']['id'] = static::targetId($entity);
    $build['field_all_related_content']['#attributes']['tabindex'] = '-1';
    $cacheability->applyTo($build);
  }

  /**
   * Sets the preview's weight and puts it in the sidebar group, if any.
   */
  protected function place(array &$build, array &$preview, string $bundle): void {
    $group_name = self::PLACEMENT[$bundle]['group'];
    $group = $group_name ? ($build['#fieldgroups'][$group_name] ?? NULL) : NULL;
    if (!$group) {
      // The template prints the preview after Communities and Protocols.
      return;
    }

    $shown = array_filter($group->children, fn($child) => isset($build[$child]['#weight']));
    $after = array_intersect(self::PLACEMENT[$bundle]['after'], $shown);
    if ($after) {
      $preview['#weight'] = max(array_map(fn($child) => $build[$child]['#weight'], $after)) + 0.5;
    }
    else {
      $preview['#weight'] = $shown ? min(array_map(fn($child) => $build[$child]['#weight'], $shown)) - 1 : 0;
    }

    $group->children[] = 'related_content_preview';
    $build['#group_children']['related_content_preview'] = $group_name;
  }

}
