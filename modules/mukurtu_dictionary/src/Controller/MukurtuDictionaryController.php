<?php

namespace Drupal\mukurtu_dictionary\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\views\Views;

class MukurtuDictionaryController extends ControllerBase {

  protected $backend;

  public function __construct() {
    $this->backend = $this->config('mukurtu_search.settings')->get('backend') ?? 'db';
  }

  /**
   * Return the machine name of the view to use based on the search backend config.
   *
   * @return string
   *   The machine name of the view.
   */
  protected function getViewName() {
    $views = [
      'db' => 'mukurtu_dictionary',
      'solr' => 'mukurtu_dictionary_solr',
    ];

    return $views[$this->backend];
  }

  /**
   * Return the facet source ID to use based on the search backend config.
   *
   * @return string
   *   The facet source ID.
   */
  protected function getFacetSourceId() {
    $views = [
      'db' => 'search_api:views_block__mukurtu_dictionary__mukurtu_dictionary_block',
      'solr' => 'search_api:views_block__mukurtu_dictionary_solr__mukurtu_dictionary_block',
    ];

    return $views[$this->backend];
  }

  public function content() {
    // Render the browse view block.
    $browse_view_block = [
      '#type' => 'view',
      '#name' => $this->getViewName(),
      '#display_id' => 'mukurtu_dictionary_block',
      '#embed' => TRUE,
    ];

    // Load all facets configured to use our browse block as a datasource.
    $facetEntities = \Drupal::entityTypeManager()
      ->getStorage('facets_facet')
      ->loadByProperties(['facet_source_id' => $this->getFacetSourceId()]);

    // Render the facet block for each of them.
    $block_manager = \Drupal::service('plugin.manager.block');
    $facets = [];
    $glossary = NULL;
    if ($facetEntities) {
      foreach ($facetEntities as $facet_id => $facetEntity) {
        $config = [];
        $block_plugin = $block_manager->createInstance('facet_block' . PluginBase::DERIVATIVE_SEPARATOR . $facet_id, $config);
        if ($block_plugin) {
          $access_result = $block_plugin->access(\Drupal::currentUser());
          if ($access_result) {
            if (strpos($facet_id, 'glossary_entry') !== FALSE) {
              $glossary = $block_plugin->build();
            }
            else {
              $facets[$facet_id] = $block_plugin->build();
            }
          }
        }
      }
    }

    return [
      '#theme' => 'mukurtu_dictionary_page',
      '#results' => $browse_view_block,
      '#facets' => $facets,
      '#glossary' => $glossary,
    ];
  }

  /**
   * Check access for the Dictionary route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    // Check if the dictionary view is empty.
    $view = Views::getView($this->getViewName());
    if (!$view) {
      return AccessResult::forbidden();
    }
    $view->setDisplay('default');
    $view->execute();
    if (!empty($view->result)) {
      return AccessResult::allowed();
    }

    // Check if the user has permission to create dictionary words or word lists.
    $hasDictWordCreateAccess = $this->entityTypeManager()->getAccessControlHandler('node')->createAccess('dictionary_word', $account);
    $hasWordListCreateAccess = $this->entityTypeManager()->getAccessControlHandler('node')->createAccess('word_list', $account);

    if ($hasDictWordCreateAccess || $hasWordListCreateAccess) {
      return AccessResult::allowed();
    }
    // User cannot access the route if the dictionary view result is empty and
    // if they lack permission to create dictionary words and word lists.
    return AccessResult::forbidden();
  }

}
