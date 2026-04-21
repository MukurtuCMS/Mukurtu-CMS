<?php

namespace Drupal\search_api_solr\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\LoggerTrait;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend;
use Drupal\search_api_solr\Utility\Utility as SearchApiSolrUtility;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A basic form with a passed entity with an interface.
 */
class SolrConfigForm extends FormBase {

  use LoggerTrait {
    getLogger as getSearchApiLogger;
  }

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a SolrConfigForm object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'solr_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ServerInterface $search_api_server = NULL) {
    $form['#title'] = $this->t('List of configuration files found');

    try {
      // Retrieve the list of available files.
      $files_list = $search_api_server ? SearchApiSolrUtility::getServerFiles($search_api_server) : [];

      if (empty($files_list)) {
        $form['info']['#markup'] = $this->t('No files found.');
        return $form;
      }

      $form['files_tabs'] = [
        '#type' => 'vertical_tabs',
      ];

      // Generate a fieldset for each file.
      foreach ($files_list as $file_name => $file_info) {
        $escaped_file_name = Html::escape($file_name);

        $form['files'][$file_name] = [
          '#type'  => 'details',
          '#title' => $escaped_file_name,
          '#group' => 'files_tabs',
        ];

        $data = '<h3>' . $escaped_file_name . '</h3>';
        if (isset($file_info['modified'])) {
          $data .= '<p><em>' . $this->t('Last modified: @time.', ['@time' => $this->dateFormatter->format(strtotime($file_info['modified']))]) . '</em></p>';
        }

        if ($file_info['size'] > 0) {
          /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
          $backend = $search_api_server->getBackend();
          $file_data = $backend->getSolrConnector()->getFile($file_name);
          $data .= '<pre><code>' . Html::escape($file_data->getBody()) . '</code></pre>';
        }
        else {
          $data .= '<p><em>' . $this->t('The file is empty.') . '</em></p>';
        }

        $form['files'][$file_name]['data']['#markup'] = $data;
      }
    }
    catch (SearchApiException $e) {
      $this->logException($e, '%type while retrieving config files of Solr server @server: @message in %function (line %line of %file).', ['@server' => $search_api_server->label()]);
      $form['info']['#markup'] = $this->t('An error occurred while trying to load the list of files.');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Checks access for the Solr config form.
   *
   * @param \Drupal\search_api\ServerInterface $search_api_server
   *   The server for which access should be tested.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  public function access(ServerInterface $search_api_server) {
    return AccessResult::allowedIf($search_api_server->hasValidBackend() && $search_api_server->getBackend() instanceof SearchApiSolrBackend)->addCacheableDependency($search_api_server);
  }

  /**
   * Get Logger.
   *
   * @param string $channel
   *   The log channel.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger.
   */
  protected function getLogger($channel = '') {
    return $this->getSearchApiLogger();
  }

}
