<?php

namespace Drupal\search_api_solr\Controller;

use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\SolrFieldTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use ZipStream\Option\Archive;

/**
 * Provides different listings of SolrFieldType.
 */
class SolrFieldTypeController extends AbstractSolrEntityController {

  /**
   * Entity type id.
   *
   * @var string
   */
  protected $entityTypeId = 'solr_field_type';

  /**
   * Provides a zip archive containing a complete Solr configuration.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The Search API server entity.
   *
   * @return array|void
   *   A render array as expected by drupal_render().
   */
  public function getConfigZip(ServerInterface $search_api_server) {
    try {
      $archive_options = NULL;
      if (class_exists('\ZipStream\Option\Archive')) {
        // Version 2.x. Version 3.x uses named parameters instead of options.
        $archive_options = new Archive();
        $archive_options->setSendHttpHeaders(TRUE);
      }
      @ob_clean();
      // If you are using nginx as a webserver, it will try to buffer the
      // response. We have to disable this with a custom header.
      // @see https://github.com/maennchen/ZipStream-PHP/wiki/nginx
      header('X-Accel-Buffering: no');
      /** @var SolrConfigSetController $solrConfigSetController */
      $solrConfigSetController = $this->getListBuilder($search_api_server);
      $zip = $solrConfigSetController->getConfigZip($archive_options);
      $zip->finish();
      @ob_end_flush();

      exit();
    }
    catch (\Exception $e) {
      $this->logException($e);
      $this->messenger->addError($this->t('An error occured during the creation of the config.zip. Look at the logs for details.'));
    }

    return [];
  }

  /**
   * Disables a Solr Field Type on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   Search API server.
   * @param \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type
   *   Solr entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function disableOnServer(ServerInterface $search_api_server, SolrFieldTypeInterface $solr_field_type): RedirectResponse {
    return $this->doDisableOnServer($search_api_server, $solr_field_type);
  }

  /**
   * Enables a Solr Field Type on this server.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   Search API server.
   * @param \Drupal\search_api_solr\SolrFieldTypeInterface $solr_field_type
   *   Solr field type.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enableOnServer(ServerInterface $search_api_server, SolrFieldTypeInterface $solr_field_type): RedirectResponse {
    return $this->doEnableOnServer($search_api_server, $solr_field_type);
  }

}
