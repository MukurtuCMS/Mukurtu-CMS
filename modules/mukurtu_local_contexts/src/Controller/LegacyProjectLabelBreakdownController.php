<?php

namespace Drupal\mukurtu_local_contexts\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows, per legacy project, which labels/notices still have referencing
 * content, so an admin can drill into reviewing/removing them.
 */
class LegacyProjectLabelBreakdownController extends ControllerBase {

  /**
   * Constructs the controller with dependencies.
   *
   * @param \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager $supportedProjectManager
   *   The Local Contexts supported project manager.
   */
  public function __construct(protected LocalContextsSupportedProjectManager $supportedProjectManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mukurtu_local_contexts.supported_project_manager'),
    );
  }

  /**
   * Route access callback.
   */
  public function access(AccountInterface $account, string $project_id): AccessResultInterface {
    if (!$account->hasPermission('administer local contexts legacy projects')) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowedIf($this->supportedProjectManager->isLegacyProjectId($project_id));
  }

  /**
   * Route title callback.
   */
  public function title(string $project_id) {
    $project = new LocalContextsProject($project_id);
    return $this->t('References for @title', ['@title' => $project->getTitle()]);
  }

  /**
   * Builds the breakdown page.
   */
  public function build(string $project_id): array {
    $project = new LocalContextsProject($project_id);
    $referencing = $project->getReferencingNodeIds();

    $build = [];

    if (!empty($referencing['project'])) {
      $build['project_reference'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->formatPlural(
          count($referencing['project']),
          'Whole-project reference (1 node) — resolved automatically whenever this project is remapped to a real Hub project.',
          'Whole-project reference (@count nodes) — resolved automatically whenever this project is remapped to a real Hub project.'
        ),
      ];
    }

    $rows = [];
    foreach ($referencing['labels_and_notices'] as $id => $nids) {
      $ref_type = $this->resolveRefType($project, (string) $id);
      $name = $project->resolveLabelOrNoticeName($ref_type, (string) $id) ?? $id;
      $rows[] = [
        $name,
        $ref_type === 'notice' ? $this->t('Notice') : $this->t('Label'),
        $this->formatPlural(count($nids), '1 node', '@count nodes'),
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Review & remove'),
            '#url' => Url::fromRoute('mukurtu_local_contexts.legacy_label_removal_review', [
              'project_id' => $project_id,
              'ref_type' => $ref_type,
              'ref_id' => $id,
            ]),
          ],
        ],
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Type'),
        $this->t('Referenced by'),
        ['data' => $this->t('Actions'), 'class' => ['visually-hidden']],
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No individual labels or notices are still referenced.'),
    ];

    return $build;
  }

  /**
   * Determine whether a referenced id is a label or a notice.
   */
  protected function resolveRefType(LocalContextsProject $project, string $id): string {
    foreach ($project->getNotices() as $notice) {
      if ($notice['notice_type'] === $id) {
        return 'notice';
      }
    }
    return 'label';
  }

}
