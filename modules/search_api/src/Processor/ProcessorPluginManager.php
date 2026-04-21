<?php

namespace Drupal\search_api\Processor;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\search_api\Annotation\SearchApiProcessor as SearchApiProcessorAnnotation;
use Drupal\search_api\Attribute\SearchApiProcessor as SearchApiProcessorAttribute;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiPluginManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Manages processor plugins.
 *
 * @see \Drupal\search_api\Attribute\SearchApiProcessor
 * @see \Drupal\search_api\Processor\ProcessorInterface
 * @see \Drupal\search_api\Processor\ProcessorPluginBase
 * @see plugin_api
 */
class ProcessorPluginManager extends SearchApiPluginManager {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'container.namespaces')]
    \Traversable $namespaces,
    #[Autowire(service: 'cache.discovery')]
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $eventDispatcher,
    TranslationInterface $translation,
  ) {
    parent::__construct(
      'Plugin/search_api/processor',
      $namespaces,
      $module_handler,
      $eventDispatcher,
      ProcessorInterface::class,
      SearchApiProcessorAttribute::class,
      SearchApiProcessorAnnotation::class,
    );

    $this->setCacheBackend($cache_backend, 'search_api_processors');
    $this->alterInfo('search_api_processor_info');
    $this->alterEvent(SearchApiEvents::GATHERING_PROCESSORS);
    $this->setStringTranslation($translation);
  }

  /**
   * Retrieves information about the available processing stages.
   *
   * These are then used by processors in their "stages" definition to specify
   * in which stages they will run.
   *
   * @return array
   *   An associative array mapping stage identifiers to information about that
   *   stage. The information itself is an associative array with the following
   *   keys:
   *   - label: The translated label for this stage.
   */
  public function getProcessingStages() {
    return [
      ProcessorInterface::STAGE_PREPROCESS_INDEX => [
        'label' => $this->t('Preprocess index'),
      ],
      ProcessorInterface::STAGE_PREPROCESS_QUERY => [
        'label' => $this->t('Preprocess query'),
      ],
      ProcessorInterface::STAGE_POSTPROCESS_QUERY => [
        'label' => $this->t('Postprocess query'),
      ],
    ];
  }

}
