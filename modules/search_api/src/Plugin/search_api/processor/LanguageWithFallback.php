<?php

namespace Drupal\search_api\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the item's language (with fallbacks) to the indexed data.
 */
#[SearchApiProcessor(
  id: 'language_with_fallback',
  label: new TranslatableMarkup('Language (with fallback)'),
  description: new TranslatableMarkup("Adds the item's language to the indexed data, and considers language fallbacks."),
  stages: [
    'add_properties' => 0,
  ],
  locked: TRUE,
  hidden: TRUE,
)]
class LanguageWithFallback extends ProcessorPluginBase {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->setEntityRepository($container->get('entity.repository'));
    $processor->setLanguageManager($container->get('language_manager'));

    return $processor;
  }

  /**
   * Retrieves the entity repository.
   *
   * @return \Drupal\Core\Entity\EntityRepositoryInterface
   *   The entity repository.
   */
  public function getEntityRepository() {
    return $this->entityRepository;
  }

  /**
   * Sets the entity repository.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The new entity repository.
   *
   * @return $this
   */
  public function setEntityRepository(EntityRepositoryInterface $entityRepository) {
    $this->entityRepository = $entityRepository;
    return $this;
  }

  /**
   * Retrieves the language manager.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  public function getLanguageManager() {
    return $this->languageManager;
  }

  /**
   * Sets the language manager.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The new language manager.
   *
   * @return $this
   */
  public function setLanguageManager(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Language (with fallback)'),
        'description' => $this->t('The item language, or a language the item is a fallback for.'),
        'type' => 'string',
        'settings' => [
          'views_type' => 'language',
        ],
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ];
      $properties['language_with_fallback'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    try {
      $entity = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException) {
      return;
    }
    if (!($entity instanceof ContentEntityInterface)) {
      return;
    }
    $langcodes = $this->getReverseLanguageFallbacks($entity);

    $fields = $item->getFields();
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($fields, NULL, 'language_with_fallback');
    foreach ($fields as $field) {
      foreach ($langcodes as $langcode) {
        $field->addValue($langcode);
      }
    }
  }

  /**
   * Retrieves all langcodes that fall back to the given entity translation.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity translation.
   *
   * @return string[]
   *   The codes of the languages for which the given entity is the fallback.
   */
  protected function getReverseLanguageFallbacks(ContentEntityInterface $entity) {
    $entityLangcode = $entity->language()->getId();

    $reverseFallbackLangcodes = [$entityLangcode];
    foreach ($this->languageManager->getLanguages() as $langcode => $language) {
      if ($langcode === $entityLangcode) {
        continue;
      }
      $context = [
        // The fallback_to_passed_entity is recognized by language_fallback_fix
        // module and does not change anything if that is not installed.
        // It allows to have languages without fallback and will hopefully be
        // fixed in core this way or another.
        // @see https://www.drupal.org/node/2951294#comment-13127796
        'fallback_to_passed_entity' => FALSE,
        // We use the entity_upcast operation here, as for the entity_view
        // operation, content_translation removes fallbacks that the current
        // user does not have access, which would lead to indexing dependent of
        // user access.
        // @see content_translation_language_fallback_candidates_entity_view_alter()
        'operation' => 'entity_upcast',
      ];
      $fallback = $this->entityRepository->getTranslationFromContext($entity, $langcode, $context);
      if ($fallback && $fallback->language()->getId() === $entityLangcode) {
        $reverseFallbackLangcodes[] = $langcode;
      }
    }

    return $reverseFallbackLangcodes;
  }

}
