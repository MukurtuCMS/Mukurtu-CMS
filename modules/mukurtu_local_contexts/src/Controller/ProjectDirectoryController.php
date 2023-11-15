<?php

namespace Drupal\mukurtu_local_contexts\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_local_contexts\LocalContextsHubBase;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the local context project directory pages.
 */
class ProjectDirectoryController extends ControllerBase {

  /**
   * @var \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager
   */
  protected $localContextsProjectManager;

  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->localContextsProjectManager = new LocalContextsSupportedProjectManager();
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * Build the variables for the site wide LC Hub directory page.
   *
   * @return array
   */
  function siteDirectory() {
    $endpointUrl = $this->configFactory->get(LocalContextsHubBase::SETTINGS_CONFIG_KEY)->get('hub_endpoint') ?? LocalContextsHubBase::DEFAULT_HUB_URL;
    $endpointParts = parse_url($endpointUrl);
    $projectBaseUrl = $endpointParts['scheme'] . '://' . $endpointParts['host'];

    $projects = $this->localContextsProjectManager->getSiteSupportedProjects(TRUE);

    foreach ($projects as &$projectInfo) {
      $project = new LocalContextsProject($projectInfo['id']);
      $projectInfo['tk_labels'] = $project->getLabels('tk');
      $projectInfo['bc_labels'] = $project->getLabels('bc');
      $projectInfo['notices'] = $project->getNotices();
      $projectInfo['url'] = $projectBaseUrl . "/projects/{$projectInfo['id']}";
    }

    return [
      '#theme' => 'local_contexts_site_project_directory',
      '#projects' => $projects,
    ];
  }

  /**
   * Checks access for the site wide LC Hub directory page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function siteDirectoryAccess(AccountInterface $account) {
    // Only show the page if there are projects to show.
    $projects = $this->localContextsProjectManager->getSiteSupportedProjects(TRUE);
    if ($projects) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
