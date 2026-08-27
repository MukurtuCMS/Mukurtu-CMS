<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows a "not yet translated" indicator when a node has no translation
 * into the visitor's active content language, so its default-language
 * version renders instead of silently disappearing or showing untagged
 * (docs/content-language-policy.md).
 *
 * Written into $variables['title_suffix'] via hook_preprocess_node(), not
 * $build via hook_node_view_alter() - the theme's browse/grid/map-browse
 * template suggestions (node--*--browse.html.twig etc.) cherry-pick
 * specific $content fields around a separately-rendered {{ label }}, so a
 * plain $build key would only ever surface on full-page node templates
 * that print {{ content }} wholesale. title_suffix is a standard Drupal
 * template variable already printed immediately after the title by every
 * one of those template suggestions (confirmed by inspecting all of
 * them), so this single implementation reaches every context - browse
 * rows, map popups, and full node pages - with no per-template markup
 * needed.
 */
class NotYetTranslatedIndicatorHooks implements ContainerInjectionInterface {

  public function __construct(protected LanguageManagerInterface $languageManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('language_manager'));
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'mukurtu_not_yet_translated_indicator' => [
        'variables' => [
          'language_name' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_preprocess_HOOK() for node.html.twig.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    if (!$this->languageManager->isMultilingual()) {
      return;
    }

    $entity = $variables['elements']['#node'] ?? NULL;
    if (!$entity instanceof TranslatableInterface) {
      return;
    }

    $active_langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    if ($entity->hasTranslation($active_langcode)) {
      return;
    }

    $indicator = [
      '#theme' => 'mukurtu_not_yet_translated_indicator',
      '#language_name' => $entity->language()->getName(),
    ];
    CacheableMetadata::createFromRenderArray($indicator)
      ->addCacheableDependency($entity)
      ->addCacheContexts(['languages:language_content'])
      ->applyTo($indicator);

    $variables['title_suffix']['mukurtu_not_yet_translated'] = $indicator;
  }

}
