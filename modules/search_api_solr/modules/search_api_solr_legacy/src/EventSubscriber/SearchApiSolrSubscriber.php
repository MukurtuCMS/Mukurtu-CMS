<?php

namespace Drupal\search_api_solr_legacy\EventSubscriber;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\search_api_solr\Event\PostConfigSetTemplateMappingEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Search API Solr events subscriber.
 */
class SearchApiSolrSubscriber implements EventSubscriberInterface {

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Search API SOLR Subscriber class constructor.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   */
  public function __construct(ModuleExtensionList $module_extension_list) {
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * Adds the mapping Solr 3, 4 and 5.
   *
   * @param \Drupal\search_api_solr\Event\PostConfigSetTemplateMappingEvent $event
   *   The event.
   */
  public function postConfigSetTemplateMapping(PostConfigSetTemplateMappingEvent $event) {
    $template_path = $this->moduleExtensionList->getPath('search_api_solr_legacy') . '/solr-conf-templates/';

    $solr_configset_template_mapping = $event->getConfigSetTemplateMapping();
    $solr_configset_template_mapping += [
      '3.x' => $template_path . '3.x',
      '4.x' => $template_path . '4.x',
      '5.x' => $template_path . '5.x',
    ];
    $event->setConfigSetTemplateMapping($solr_configset_template_mapping);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[SearchApiSolrEvents::POST_CONFIG_SET_TEMPLATE_MAPPING][] = ['postConfigSetTemplateMapping'];

    return $events;
  }

}
