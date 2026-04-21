<?php

namespace Drupal\dashboards\Plugin\Dashboard;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dashboards\Plugin\DashboardBase;
use Drupal\dashboards\Plugin\DashboardLazyBuildBase;
use Laminas\Feed\Reader\ExtensionManager;
use Laminas\Feed\Reader\ExtensionPluginManager;
use Laminas\Feed\Reader\Reader;

/**
 * Show account info.
 *
 * @Dashboard(
 *   id = "rss_news",
 *   label = @Translation("Show rss news"),
 *   category = @Translation("Dashboards: Extras")
 * )
 */
class RssNews extends DashboardLazyBuildBase {

  /**
   * Default cache time.
   *
   * @var int
   */
  const CACHE_TIME = 1800;

  /**
   * Fetch rss items.
   *
   * @param string $plugin_id
   *   Plugin id for cache.
   * @param string $uri
   *   URI to fetch.
   *
   * @return array
   *   Feed items.
   */
  public static function readSource($plugin_id, $uri): array {
    $cache = \Drupal::service('dashboards.cache');
    $cid = $plugin_id . ':' . md5($uri);
    if (!($data = $cache->get($cid))) {
      $extensions = new ExtensionPluginManager();
      Reader::setExtensionManager(new ExtensionManager($extensions));
      $client = \Drupal::httpClient();

      $response = $client->request('GET', $uri);
      $channel = Reader::importString($response->getBody()->getContents());
      $items = [];
      foreach ($channel as $item) {
        $image = NULL;
        $enclosure = $item->getEnclosure();
        if ($enclosure
            && $enclosure->type
            && $enclosure->url
            && explode('/', $enclosure->type)[0] == 'image') {
          $image = $enclosure->url;
        }
        $date = ($item->getDateModified() ?? $item->getDateCreated());
        $items[] = [
          'title' => $item->getTitle(),
          'link' => $item->getLink(),
          'image' => $image,
          'description' => $item->getDescription(),
          'date' => $date->format(DATE_ATOM),
        ];
      }
      $cache->set($cid, $items, time() + static::CACHE_TIME);
      return $items;
    }
    return $data->data;
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array $form, FormStateInterface $form_state, array $configuration): array {
    $form['uri'] = [
      '#type' => 'url',
      '#required' => TRUE,
      '#title' => $this->t('Feed URL or website url'),
      '#default_value' => (isset($configuration['uri'])) ? $configuration['uri'] : '',
    ];
    $form['max_items'] = [
      '#type' => 'number',
      '#title' => $this->t('How many items to display'),
      '#default_value' => (isset($configuration['max_items'])) ? $configuration['max_items'] : 5,
    ];
    $form['show_description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show description'),
      '#default_value' => (isset($configuration['show_description'])) ? $configuration['show_description'] : 5,
    ];
    $form['show_images'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show images'),
      '#default_value' => (isset($configuration['show_images'])) ? $configuration['show_images'] : 5,
    ];
    return $form;
  }

  /**
   * Lazy builder callback.
   *
   * @param \Drupal\dashboards\Plugin\DashboardBase $plugin
   *   Plugin id.
   * @param array $configuration
   *   Plugin configuration.
   *
   * @return array
   *   Rendering array.
   */
  public static function lazyBuild(DashboardBase $plugin, array $configuration): array {
    $url = $configuration['uri'];
    $max_items = $configuration['max_items'];
    $show_description = $configuration['show_description'];
    $show_images = $configuration['show_images'];
    /** @var \Drupal\Core\StringTranslation\TranslationManager $translation_manager */
    $translation_manager = \Drupal::service('string_translation');

    try {
      $items = static::readSource($plugin->getPluginId(), $url);
      if (count($items) > $max_items) {
        $items = array_slice($items, 0, $max_items);
      }
      $data = [];
      foreach ($items as $item) {
        $date = new DrupalDateTime($item['date'], 'UTC');
        $date = \Drupal::service('date.formatter')->format($date->getTimestamp(), 'short');
        $data[] = [
          'url' => Url::fromUri($item['link']),
          'description' => $date,
          'title' => $item['title'],
          'image' => ($show_images) ? $item['image'] : NULL,
        ];
        if ($show_description) {
          $data[count($data) - 1]['description'] = [
            '#markup' => $data[count($data) - 1]['description'] . '<br>' . strip_tags($item['description'], '<img> <a> <ul> <li> <p>'),
          ];
        }
      }
      return [
        '#theme' => 'dashboards_admin_list',
        '#list' => $data,
        '#cache' => [
          'max-age' => static::CACHE_TIME,
        ],
      ];
    }
    catch (\Exception $ex) {
      \Drupal::logger('dashboards')->error($ex);
      return ['#markup' => $translation_manager->translate('Could not read @url', ['@url' => $url])];
    }
  }

}
