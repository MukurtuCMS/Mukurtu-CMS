<?php

declare(strict_types=1);

namespace Drupal\mukurtu_multilingual\Plugin\views\field;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Links a status row straight to its language's translation form.
 *
 * Opens the translation's edit form when it exists, and the add form when
 * it doesn't. Every row shows "Translate", so the item and language are added
 * as visually hidden text to give each link a unique accessible name (WCAG
 * 2.4.4). Shown only to people who can use the form.
 */
#[ViewsField('mukurtu_translation_status_translate')]
class TranslationStatusTranslateLink extends FieldPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityRepositoryInterface $entityRepository,
    protected LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.repository'),
      $container->get('language_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init($view, $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->additional_fields['langcode'] = 'langcode';
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity ?? NULL;
    $language = $this->languageManager->getLanguage((string) $this->getValue($values, 'langcode'));
    if (!$entity instanceof ContentEntityInterface || !$language) {
      return '';
    }

    $entity_type_id = $entity->getEntityTypeId();
    if ($entity->hasTranslation($language->getId())) {
      // The translation's own edit form, in its language, as the
      // translations overview links it. Core denies the separate
      // content_translation_edit route for entity types like nodes whose
      // edit form handles translations itself.
      $url = $entity->getTranslation($language->getId())->toUrl('edit-form');
    }
    else {
      $url = Url::fromRoute("entity.$entity_type_id.content_translation_add", [
        $entity_type_id => $entity->id(),
        'source' => $entity->getUntranslated()->language()->getId(),
        'target' => $language->getId(),
      ]);
    }

    $access = $url->access(NULL, TRUE);
    $build = [];
    if ($access->isAllowed()) {
      $title = $this->entityRepository->getTranslationFromContext($entity)->label();
      $text = $this->t('Translate<span class="visually-hidden"> @title into @language</span>', [
        '@title' => $title,
        '@language' => $language->getName(),
      ]);
      $build = Link::fromTextAndUrl($text, $url)->toRenderable();
    }
    $build['#cache']['contexts'] = $access->getCacheContexts();
    $build['#cache']['tags'] = $access->getCacheTags();
    return $build;
  }

}
