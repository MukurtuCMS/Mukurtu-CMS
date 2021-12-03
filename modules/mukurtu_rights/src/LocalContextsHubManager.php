<?php

namespace Drupal\mukurtu_rights;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\core\Entity\EntityTypeManagerInterface;
use Drupal\mukurtu_rights\Entity\LocalContextsProject;
use Drupal\mukurtu_rights\Entity\LocalContextsLabel;
use Exception;

/**
 * Interact with the Local Contexts Label Hub API.
 */
class LocalContextsHubManager implements LocalContextsHubManagerInterface {

  /**
   * The OG settings configuration key.
   *
   * @var string
   */
  const SETTINGS_CONFIG_KEY = 'mukurtu_rights.label_hub.settings';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The API endpoint URL.
   *
   * @var string
   */
  protected $endpointUrl;

  /**
   * Constructs a LocalContextsHubManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $endpointUrl = $config_factory->get(self::SETTINGS_CONFIG_KEY)->get('hub_endpoint') ?? 'https://anth-ja77-lc-dev-42d5.uc.r.appspot.com/api/v1/';

    if (str_ends_with($endpointUrl, '/')) {
      $endpointUrl = substr($endpointUrl, 0, -1);
    }
    $this->endpointUrl = $endpointUrl;
  }

  /**
   * {@inheritdoc}
   */
  public function getProject(string $uuid) {
    $project = NULL;

    // Query the Hub.
    $data = $this->makeCall("/projects/{$uuid}");

    // Populate the project entity.
    if (isset($data['unique_id'])) {
      // Check if this project entity already exists.
      $projects = $this->entityTypeManager->getStorage('lcproject')->loadByProperties(['uuid' => $uuid]);
      if (empty($projects)) {
        $project = LocalContextsProject::create(['uuid' => $uuid]);
      }
      else {
        /**
          * @var \Drupal\mukurtu_rights\LocalContextsProjectInterface
         */
        $project = $projects[array_key_first($projects)];
      }

      // Title.
      $project->set('title', $data['title']);

      // Labels.
      $labelRefs = [];
      foreach (['tk_labels', 'bc_labels'] as $labelClassKey) {
        if (!empty($data[$labelClassKey])) {
          $labelStorage = $this->entityTypeManager->getStorage('lclabel');
          $labelClass = str_replace('_labels', '', $labelClassKey);
          foreach ($data[$labelClassKey] as $hubLabel) {
            // Try to get existing label entity.
            $labels = $labelStorage->loadByProperties([
              'label_type' => $hubLabel['label_type'],
              'project_uuid' => $uuid,
            ]);

            if (empty($labels)) {
              // No existing label, create one.
              $label = LocalContextsLabel::create([
                'label_type' => $hubLabel['label_type'],
                'project_uuid' => $uuid,
              ]);
            }
            else {
              // Use existing.
              /**
               * @var \Drupal\mukurtu_rights\LocalContextsLabelInterface
               */
              $label = $labels[array_key_first($labels)];
            }

            // Populate label fields.
            $label->set('project_title', $data['title'] ?? '');
            $label->set('name', $hubLabel['name'] ?? '');
            $label->set('text', $hubLabel['default_text'] ?? '');
            $label->set('community', $hubLabel['community'] ?? '');
            $label->set('image_url', $hubLabel['img_url'] ?? '');
            $label->set('label_class', $labelClass);

            if (isset($hubLabel['created'])) {
              $label->set('hub_created', substr($hubLabel['created'], 0, -8));
            }

            if (isset($hubLabel['updated'])) {
              $label->set('hub_updated', substr($hubLabel['updated'], 0, -8));
            }

            try {
              $label->save();
              // Add the label to the list of references.
              $labelRefs[] = $label->id();
            }
            catch (Exception $e) {
              // Intentionally left blank.
            }

          }
        }
        $project->set('labels', $labelRefs);
      }
    }

    return $project;
  }

  /**
   * Make a call to the API.
   *
   * @param string $path
   *   The API method.
   *
   * @return mixed
   *   The result of the API call.
   */
  protected function makeCall($path) {
    $curl = curl_init();

    if (!str_starts_with($path, '/')) {
      $path = '/' . $path;
    }

    $url = $this->endpointUrl . $path;

    curl_setopt($curl, CURLOPT_URL, $url);

    // The hub uses 301 redirects.
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

    // Send the request.
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($http_code == '200') {
      $result = json_decode($response, TRUE);
    }
    else {
      $result = [];
    }

    curl_close($curl);

    return $result;
  }

}
