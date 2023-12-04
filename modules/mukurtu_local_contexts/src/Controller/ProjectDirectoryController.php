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
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;
use Drupal\Core\Entity\ContentEntityInterface;

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
    $description = $this->t($this->config('mukurtu_local_contexts.settings')->get('mukurtu_local_contexts_manage_site_projects_directory_description')) ?? '';

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
      '#description'=> $description,
      '#cache' => [
        'max-age' => 0,
      ],
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

  /**
   * Build the variables for the community LC Hub directory page.
   *
   * @return array
   */
  function communityDirectory(CommunityInterface $group)
  {
    $endpointUrl = $this->configFactory->get(LocalContextsHubBase::SETTINGS_CONFIG_KEY)->get('hub_endpoint') ?? LocalContextsHubBase::DEFAULT_HUB_URL;
    $endpointParts = parse_url($endpointUrl);
    $projectBaseUrl = $endpointParts['scheme'] . '://' . $endpointParts['host'];

    $projects = $this->localContextsProjectManager->getGroupSupportedProjects($group);
    if ($projects) {
      $description = $this->config('mukurtu_local_contexts.settings')->get('mukurtu_local_contexts_manage_community_' . $group->id() . '_projects_directory_description');
      // Exception throws if translated string is null, so check for this.
      $description = ($description == NULL ? '' : $this->t($description));
    }
    else {
      $description = $this->t("There are currently no Local Contexts projects for this community.");
    }
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
      '#description' => $description,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Build the variables for the protocol LC Hub directory page.
   *
   * @return array
   */
  function protocolDirectory(ProtocolInterface $group)
  {
    $endpointUrl = $this->configFactory->get(LocalContextsHubBase::SETTINGS_CONFIG_KEY)->get('hub_endpoint') ?? LocalContextsHubBase::DEFAULT_HUB_URL;
    $endpointParts = parse_url($endpointUrl);
    $projectBaseUrl = $endpointParts['scheme'] . '://' . $endpointParts['host'];

    $projects = $this->localContextsProjectManager->getGroupSupportedProjects($group);
    if ($projects) {
      $description = $this->config('mukurtu_local_contexts.settings')->get('mukurtu_local_contexts_manage_protocol_' . $group->id() . '_projects_directory_description');
      // Exception throws if translated string is null, so check for this.
      $description = ($description == NULL ? '' : $this->t($description));
    } else {
      $description = $this->t("There are currently no Local Contexts projects for this cultural protocol.");
    }
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
      '#description' => $description,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Checks access for the community or protocol Local Contexts Hub directory page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @param Drupal\Core\Entity\ContentEntityInterface $group
   *   Run access checks for this group.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function groupDirectoryAccess(AccountInterface $account, ContentEntityInterface $group = NULL)
  {
    // Only show the page if the group exists.
    if ($group) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  public function title(ContentEntityInterface $group = NULL)
  {
    return $this->t("Local Contexts Project Directory for %group", ['%group' => $group ? $group->getName() : 'Unknown Group']);
  }

}
