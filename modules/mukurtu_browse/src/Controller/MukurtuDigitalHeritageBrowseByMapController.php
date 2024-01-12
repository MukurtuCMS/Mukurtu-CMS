<?php

namespace Drupal\mukurtu_browse\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\views\Views;

class MukurtuDigitalHeritageBrowseByMapController extends ControllerBase
{
    protected $bundles;

    protected $backend;

    public function __construct()
    {
        $this->backend = $this->config('mukurtu_search.settings')->get('backend') ?? 'db';
    }

    /**
     * Return the machine name of the view to use based on the search backend config.
     *
     * @return string
     *   The machine name of the view.
     */
    protected function getViewName()
    {
        $views = [
        'db' => 'mukurtu_digital_heritage_browse_by_map',
        'solr' => 'mukurtu_digital_heritage_browse_by_map_solr',
        ];

        return $views[$this->backend];
    }

    /**
     * Return the facet source ID to use based on the search backend config.
     *
     * @return string
     *   The facet source ID.
     */
    protected function getFacetSourceId()
    {
        $views = [
        'db' => 'search_api:views_block__mukurtu_digital_heritage_browse_by_map__digital_heritage_map_block',
        'solr' => 'search_api:views_block__mukurtu_digital_heritage_browse_by_map_solr__digital_heritage_map_block',
        ];

        return $views[$this->backend];
    }

    public function getBundles()
    {
        if (!$this->bundles) {
            $this->bundles = array_keys(\Drupal::service('entity_type.bundle.info')->getBundleInfo('node'));
        }
        return $this->bundles;
    }

    public function access(AccountInterface $account)
    {
        if (!$account->hasPermission('access content')) {
            return AccessResult::forbidden();
        }

        $view = Views::getView($this->getViewName());
        if (!$view) {
            return AccessResult::forbidden();
        }

        // Does the digital heritage content type exist?
        $siteBundles = $this->getBundles();
        if (!in_array('digital_heritage', $siteBundles)) {
            return AccessResult::forbidden();
        }

        // Do we have at least one published content item with location data?
        $query = $this->entityTypeManager()->getStorage('node')->getQuery();
        $query->condition('field_coverage', "Feature", 'CONTAINS')
            ->condition('type', 'digital_heritage')
            ->condition('status', 1)
            ->range(0, 1)
            ->accessCheck(true);

        $results = $query->execute();

        return empty($results) ? AccessResult::forbidden() : AccessResult::allowed();
    }

    public function content()
    {
        // Browse link.
        $options = ['attributes' => ['id' => 'mukurtu-browse-list-switch-link'],
                    'query' => ['active' => 'list']];
        $map_browse_link = Link::createFromRoute(t('List'), 'mukurtu_browse.browse_digital_heritage_page', [], $options);

        // Render the map browse view block.
        $map_browse_view_block = [
        '#type' => 'view',
        '#name' => $this->getViewName(),
        '#display_id' => 'digital_heritage_map_block',
        '#embed' => true,
        ];

        $teasers = [
        '#type' => 'container',
        '#attributes' => [
        'id' => 'mukurtu-map-browse-teasers',
        ],
        ];

        // Load all facets configured to use our browse block as a datasource.
        $facetEntities = \Drupal::entityTypeManager()
            ->getStorage('facets_facet')
            ->loadByProperties(['facet_source_id' => $this->getFacetSourceId()]);

        // Render the facet block for each of them.
        $facets = [];
        if ($facetEntities) {
            $block_manager = \Drupal::service('plugin.manager.block');
            foreach ($facetEntities as $facet_id => $facetEntity) {
                $config = [];
                $block_plugin = $block_manager->createInstance('facet_block' . PluginBase::DERIVATIVE_SEPARATOR . $facet_id, $config);
                if ($block_plugin) {
                    $access_result = $block_plugin->access(\Drupal::currentUser());
                    if ($access_result) {
                        $facets[$facet_id] = $block_plugin->build();
                    }
                }
            }
        }

        return [
        '#theme' => 'mukurtu_map_browse',
        '#browselink' => $map_browse_link,
        '#teasers' => $teasers,
        '#map' => $map_browse_view_block,
        '#facets' => $facets,
        '#attached' => [
        'library' => [
          'mukurtu_browse/mukurtu-browse-view-switch',
          'mukurtu_browse/map-browse-teasers',
        ],
        ],
        ];
    }

    public function getEntityTeaserAjax(NodeInterface $node)
    {
        $view_builder = $this->entityTypeManager()->getViewBuilder('node');
        $response = new AjaxResponse();
        $content['mukurtu_map_browse_teasers'] = [
        '#type' => 'container',
        '#attributes' => [
        'id' => 'mukurtu-map-browse-teasers',
        ],
        ];

        $content['mukurtu_map_browse_teasers']['teaser'] = [];
        if ($node->access('view')) {
            $content['mukurtu_map_browse_teasers']['teaser'] = $view_builder->view($node, 'map_browse');
        }
        $response->addCommand(new ReplaceCommand('#mukurtu-map-browse-teasers', $content));
        return $response;
    }

    public function getTeasersAjax($nodes)
    {
        $renderer = \Drupal::service('renderer');
        $response = new AjaxResponse();

        $content['mukurtu_map_browse_teasers'] = [
        '#type' => 'container',
        '#attributes' => [
        'id' => 'mukurtu-map-browse-teasers',
        ],
        ];

        $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');

        $content['mukurtu_map_browse_teasers']['items'] = [];
        foreach ($nodes as $node) {
            $pre_render = $view_builder->view($node, 'teaser');
            $content['mukurtu_map_browse_teasers']['items'][] = $pre_render;
        }
        $content['mukurtu_map_browse_teasers']['items'][] = [
        '#type' => 'pager',
        '#element' => 1,
        '#quantity' => 1,
        ];

        $response->addCommand(new ReplaceCommand('#mukurtu-map-browse-teasers', $content));

        return $response;
    }
}
