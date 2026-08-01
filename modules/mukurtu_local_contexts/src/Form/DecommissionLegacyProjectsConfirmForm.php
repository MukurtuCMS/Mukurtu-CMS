<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for decommissioning legacy Local Contexts projects.
 *
 * The set of projects to decommission (already validated as legacy and
 * zero-reference by ManageSupportedProjectsBase::submitDecommission()) is
 * passed here via private tempstore, not route parameters, so this single
 * class covers both site and group scope.
 */
class DecommissionLegacyProjectsConfirmForm extends ConfirmFormBase {

  /**
   * The pending decommission request loaded from tempstore.
   *
   * @var array|null
   */
  protected ?array $pending = NULL;

  /**
   * Constructs the form with dependencies.
   *
   * @param \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager $supportedProjectManager
   *   The Local Contexts supported project manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private tempstore factory.
   */
  public function __construct(
    protected LocalContextsSupportedProjectManager $supportedProjectManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mukurtu_local_contexts.supported_project_manager'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_local_contexts_decommission_legacy_projects_confirm';
  }

  /**
   * Route access callback.
   *
   * Requires both the dedicated permission and an actual pending
   * decommission request in tempstore - this form has nothing meaningful
   * to show otherwise.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    if (!$account->hasPermission('administer local contexts legacy projects')) {
      return AccessResult::forbidden();
    }
    $pending = $this->tempStoreFactory->get('mukurtu_local_contexts.decommission')->get($account->id());
    return AccessResult::allowedIf(!empty($pending['project_ids']));
  }

  /**
   * Route title callback.
   */
  public function title() {
    return $this->getQuestion();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->pending = $this->tempStoreFactory->get('mukurtu_local_contexts.decommission')->get($this->currentUser()->id());

    $form = parent::buildForm($form, $form_state);

    $items = [];
    foreach ($this->pending['project_ids'] as $id) {
      $project = new LocalContextsProject($id);
      $label_count = count($project->getLabels('tk')) + count($project->getLabels('bc'));
      $notice_count = count($project->getNotices());
      $labels_text = $this->formatPlural($label_count, '1 label', '@count labels');
      $notices_text = $this->formatPlural($notice_count, '1 notice', '@count notices');
      $items[] = $this->t('@title (@labels, @notices)', [
        '@title' => $project->getTitle(),
        '@labels' => $labels_text,
        '@notices' => $notices_text,
      ]);
    }

    $form['items'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Selected items'),
      '#items' => $items,
      '#weight' => -10,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->formatPlural(count($this->pending['project_ids'] ?? []), 'Decommission 1 legacy project?', 'Decommission @count legacy projects?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This permanently deletes the cached labels and notices for the selected project(s). No content currently references them, so nothing on the site will change — only the underlying legacy label/notice data is removed. This cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Decommission');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $scope = $this->pending['scope'] ?? 'site';
    $group_id = $this->pending['group_id'] ?? NULL;

    if ($scope === 'community' && $group_id) {
      return Url::fromRoute('mukurtu_local_contexts.manage_community_supported_projects', ['group' => $group_id]);
    }
    if ($scope === 'protocol' && $group_id) {
      return Url::fromRoute('mukurtu_local_contexts.manage_protocol_supported_projects', ['group' => $group_id]);
    }
    return Url::fromRoute('mukurtu_local_contexts.manage_site_supported_projects');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pending = $this->pending ?? [];
    $project_ids = $pending['project_ids'] ?? [];
    $scope = $pending['scope'] ?? 'site';
    $group_id = $pending['group_id'] ?? NULL;

    $group = NULL;
    if ($scope !== 'site' && $group_id) {
      $group = \Drupal::entityTypeManager()->getStorage($scope)->load($group_id);
    }

    $decommissioned = 0;
    foreach ($project_ids as $id) {
      // Re-validate immediately before deleting: content could have started
      // referencing this project between selection and confirmation.
      $project = new LocalContextsProject($id);
      if ($project->inUse()) {
        $this->messenger()->addError($this->t('The project %project was not decommissioned because it is now referenced by content. No changes were made to it.', ['%project' => $project->getTitle()]));
        continue;
      }

      // The community/protocol could have been deleted in the same window.
      // Skip rather than misapplying the removal to the wrong scope.
      if ($scope !== 'site' && !$group) {
        continue;
      }

      if ($group) {
        $this->supportedProjectManager->removeGroupProject($group, $id);
      }
      else {
        $this->supportedProjectManager->removeSiteProject($id);
      }
      $decommissioned++;
    }

    if ($decommissioned) {
      $this->messenger()->addStatus($this->formatPlural($decommissioned, '1 legacy project decommissioned.', '@count legacy projects decommissioned.'));
    }

    $this->tempStoreFactory->get('mukurtu_local_contexts.decommission')->delete($this->currentUser()->id());
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
