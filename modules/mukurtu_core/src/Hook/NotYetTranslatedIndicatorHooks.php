<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows a "not yet translated" indicator when a node has no translation
 * into the visitor's active content language, so its default-language
 * version renders instead of silently disappearing or showing untagged
 * (docs/content-language-policy.md). For the same nodes it also rebuilds
 * the template's {{ url }} in the active language, so links to them keep
 * the visitor's language prefix.
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

  public function __construct(
    protected LanguageManagerInterface $languageManager,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('language_manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * Implements hook_preprocess_HOOK() for node.html.twig.
   *
   * The 'mukurtu_not_yet_translated_indicator' theme hook this renders is
   * declared in mukurtu_core_theme() in mukurtu_core.module - a module
   * can't implement hook_theme() a second time here without Drupal
   * throwing a LogicException.
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

    $active_language = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT);
    if ($entity->hasTranslation($active_language->getId())) {
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

    // Core builds {{ url }} with $node->toUrl(), which pins the link to the
    // node's own language. For an untranslated node that is the original
    // language, so following a card from /fr/browse would drop the /fr
    // prefix and switch the whole page out of French. Link in the active
    // content language instead, so the fallback version opens with this
    // indicator showing. The render array already varies by
    // languages:language_content via $indicator above.
    if (isset($variables['url']) && !$entity->isNew()) {
      $variables['url'] = $entity->toUrl('canonical', ['language' => $active_language])->toString();
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for page-title.html.twig.
   *
   * On an untranslated node's own page the visitor now stays in their
   * language (see preprocessNode()), so the page's lang no longer matches
   * the original-language title in the <h1>. Mark it with the node's
   * language so screen readers pronounce it correctly (WCAG 3.1.2).
   */
  #[Hook('preprocess_page_title')]
  public function preprocessPageTitle(array &$variables): void {
    if ($language = $this->untranslatedRouteNodeLanguage()) {
      $variables['title_attributes']['lang'] = $language->getId();
      $variables['title_attributes']['dir'] = $language->getDirection();
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for breadcrumb.html.twig.
   *
   * The theme appends the page title as the last breadcrumb item after
   * module preprocessing runs, so this only exposes the language. The
   * theme's breadcrumb template applies it to that item.
   */
  #[Hook('preprocess_breadcrumb')]
  public function preprocessBreadcrumb(array &$variables): void {
    if ($language = $this->untranslatedRouteNodeLanguage()) {
      $variables['current_page_language'] = [
        'langcode' => $language->getId(),
        'direction' => $language->getDirection(),
      ];
    }
  }

  /**
   * Returns the routed node's language if it has no translation to show.
   *
   * @return \Drupal\Core\Language\LanguageInterface|null
   *   The language of the version being shown instead, or NULL when this
   *   isn't a node page or the node has a translation in the active content
   *   language.
   */
  protected function untranslatedRouteNodeLanguage(): ?LanguageInterface {
    if (!$this->languageManager->isMultilingual() || $this->routeMatch->getRouteName() !== 'entity.node.canonical') {
      return NULL;
    }
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface) {
      return NULL;
    }
    $active_langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    return $node->hasTranslation($active_langcode) ? NULL : $node->language();
  }

}
