<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_local_contexts\Batch\LegacyProjectRemapBatch;
use Drupal\mukurtu_local_contexts\LegacyProjectRemapPreviewBuilder;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Local Contexts form for remapping a legacy project.
 *
 * A 3-step wizard: select the legacy project and its real-project target,
 * optionally map individual legacy labels/notices to real ones, preview the
 * exact content that will change, then confirm and run the batch.
 */
abstract class RemapLegacyProjectBase extends FormBase {

  /**
   * Constructs the form with dependencies.
   *
   * @param \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager $supportedProjectManager
   *   The Local Contexts supported project manager.
   * @param \Drupal\mukurtu_local_contexts\LegacyProjectRemapPreviewBuilder $previewBuilder
   *   The legacy project remap preview builder.
   */
  public function __construct(
    protected LocalContextsSupportedProjectManager $supportedProjectManager,
    protected LegacyProjectRemapPreviewBuilder $previewBuilder,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mukurtu_local_contexts.supported_project_manager'),
      $container->get('mukurtu_local_contexts.legacy_project_remap_preview_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;
    $step = $form_state->get('step') ?? 'select';

    switch ($step) {
      case 'map':
        return $this->buildMapStep($form, $form_state);

      case 'preview':
        return $this->buildPreviewStep($form, $form_state);

      default:
        return $this->buildSelectStep($form, $form_state);
    }
  }

  /**
   * Step 1: pick the legacy project to reassign and its real-project target.
   */
  protected function buildSelectStep(array $form, FormStateInterface $form_state) {
    $group = $form_state->get('group');

    $legacyProjects = $group
      ? $this->supportedProjectManager->getGroupSupportedProjects($group)
      : $this->supportedProjectManager->getSiteSupportedProjects();
    $legacyProjects = array_filter(
      $legacyProjects,
      fn ($id) => $this->supportedProjectManager->isLegacyProjectId((string) $id),
      ARRAY_FILTER_USE_KEY
    );

    $targetProjects = array_filter(
      $this->supportedProjectManager->getAllProjects(),
      fn ($id) => !$this->supportedProjectManager->isLegacyProjectId((string) $id),
      ARRAY_FILTER_USE_KEY
    );

    $legacyOptions = [];
    foreach ($legacyProjects as $id => $project) {
      $legacyOptions[$id] = $project['title'];
    }

    $targetOptions = [];
    foreach ($targetProjects as $id => $project) {
      $targetOptions[$id] = $this->t('@title (@scope)', [
        '@title' => $project['title'],
        '@scope' => $this->describeScope($project['type'], (int) $project['group_id']),
      ]);
    }

    $form['legacy_project_id'] = [
      '#type' => 'radios',
      '#title' => $this->t('Legacy project to reassign'),
      '#description' => $this->t("Legacy projects come from your v3 migration. 'Default' labels were never customized; 'Sitewide' labels were customized at the site level; a community-specific project appears only if that community customized its own labels in v3."),
      '#options' => $legacyOptions,
      '#default_value' => $form_state->get('legacy_project_id'),
      '#required' => TRUE,
    ];

    $form['target_project_id'] = [
      '#type' => 'radios',
      '#title' => $this->t('Target Local Contexts Hub project'),
      '#options' => $targetOptions,
      '#default_value' => $form_state->get('target_project_id'),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['next'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next: Map Labels'),
      '#button_type' => 'primary',
      '#submit' => ['::submitSelect'],
    ];

    return $form;
  }

  /**
   * Describes the scope a supported project belongs to, for display.
   */
  protected function describeScope(string $type, int $group_id) {
    if ($type === 'site') {
      return $this->t('Site');
    }
    $entity = \Drupal::entityTypeManager()->getStorage($type)->load($group_id);
    return $entity ? $entity->label() : $this->t('@type #@id', ['@type' => $type, '@id' => $group_id]);
  }

  /**
   * Submit handler for the "select" step.
   */
  public function submitSelect(array &$form, FormStateInterface $form_state) {
    $form_state->set('legacy_project_id', $form_state->getValue('legacy_project_id'));
    $form_state->set('target_project_id', $form_state->getValue('target_project_id'));
    $form_state->set('step', 'map');
    $form_state->setRebuild();
  }

  /**
   * Step 2: optionally map individual legacy labels/notices to real ones.
   */
  protected function buildMapStep(array $form, FormStateInterface $form_state) {
    $legacy = new LocalContextsProject($form_state->get('legacy_project_id'));
    $target = new LocalContextsProject($form_state->get('target_project_id'));

    $form['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Map legacy labels and notices to their real Hub equivalents (optional)'),
    ];

    $form['mapping'] = [
      '#type' => 'table',
      '#header' => [$this->t('Legacy label/notice'), $this->t('Map to')],
    ];

    $skipOption = ['' => $this->t('— Skip (leave unmapped) —')];

    foreach (['tk', 'bc'] as $kind) {
      foreach ($legacy->getLabels($kind) as $id => $label) {
        $this->addMappingRow($form, $form_state, $id, $label['name'], $skipOption + $this->labelOptions($target->getLabels($kind)));
      }
    }

    $targetNoticeOptions = [];
    foreach ($target->getNotices() as $notice) {
      $targetNoticeOptions[$notice['notice_type']] = $notice['name'];
    }
    foreach ($legacy->getNotices() as $notice) {
      $this->addMappingRow($form, $form_state, $notice['notice_type'], $notice['name'], $skipOption + $targetNoticeOptions);
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#submit' => ['::submitMapBack'],
      '#limit_validation_errors' => [],
    ];
    $form['actions']['preview'] = [
      '#type' => 'submit',
      '#value' => $this->t('Preview'),
      '#button_type' => 'primary',
      '#submit' => ['::submitMap'],
    ];

    return $form;
  }

  /**
   * Adds one row to the label/notice mapping table.
   */
  protected function addMappingRow(array &$form, FormStateInterface $form_state, string $id, string $name, array $options): void {
    $form['mapping'][$id]['name'] = ['#markup' => $name];
    $form['mapping'][$id]['target'] = [
      '#type' => 'select',
      '#title' => $this->t('Map @name to', ['@name' => $name]),
      '#title_display' => 'invisible',
      '#options' => $options,
      '#default_value' => $form_state->get(['label_mapping', $id]) ?? '',
    ];
  }

  /**
   * Flattens a label/notice list (keyed by ID) into an options array.
   */
  protected function labelOptions(array $labels): array {
    $options = [];
    foreach ($labels as $id => $label) {
      $options[$id] = $label['name'];
    }
    return $options;
  }

  /**
   * Submit handler for the "map" step.
   */
  public function submitMap(array &$form, FormStateInterface $form_state) {
    $mapping = [];
    foreach ($form_state->getValue('mapping', []) as $id => $row) {
      if ($row['target'] !== '') {
        $mapping[$id] = $row['target'];
      }
    }
    $form_state->set('label_mapping', $mapping);
    $form_state->set('step', 'preview');
    $form_state->setRebuild();
  }

  /**
   * Submit handler to go back from the "map" step to "select".
   */
  public function submitMapBack(array &$form, FormStateInterface $form_state) {
    $form_state->set('step', 'select');
    $form_state->setRebuild();
  }

  /**
   * Step 3: preview the affected content, then confirm and run the batch.
   */
  protected function buildPreviewStep(array $form, FormStateInterface $form_state) {
    $legacyId = $form_state->get('legacy_project_id');
    $targetId = $form_state->get('target_project_id');
    $mapping = $form_state->get('label_mapping') ?? [];

    $preview = $this->previewBuilder->build($legacyId, $targetId, $mapping);

    $form['heading'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Preview: content that will be reassigned'),
    ];

    $rows = [];
    foreach ($preview['rows'] as $row) {
      $rows[] = [
        $row['id'] === NULL ? $this->t('Whole-project reference') : $row['label'],
        count($row['nids']),
        $row['mapped'] ? $this->t('Will be reassigned') : $this->t('Will be skipped — map this label to include it'),
      ];
    }

    $form['preview'] = [
      '#type' => 'table',
      '#header' => [$this->t('Reference'), $this->t('Nodes affected'), $this->t('Outcome')],
      '#rows' => $rows,
      '#empty' => $this->t('No content currently references this legacy project.'),
    ];

    $form['summary'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('@count distinct nodes will be updated. (Row counts may overlap since a node can be referenced more than one way.)', ['@count' => $preview['total']]),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back to mapping'),
      '#submit' => ['::submitPreviewBack'],
      '#limit_validation_errors' => [],
    ];
    $form['actions']['confirm'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm & Run Batch'),
      '#button_type' => 'primary',
      '#submit' => ['::submitConfirm'],
      '#access' => $preview['total'] > 0,
    ];

    return $form;
  }

  /**
   * Submit handler to go back from the "preview" step to "map".
   */
  public function submitPreviewBack(array &$form, FormStateInterface $form_state) {
    $form_state->set('step', 'map');
    $form_state->setRebuild();
  }

  /**
   * Submit handler that dispatches the batch operation.
   */
  public function submitConfirm(array &$form, FormStateInterface $form_state) {
    $legacyId = $form_state->get('legacy_project_id');
    $targetId = $form_state->get('target_project_id');
    $mapping = $form_state->get('label_mapping') ?? [];

    $batch = (new BatchBuilder())
      ->setTitle($this->t('Reassigning legacy Local Contexts project…'))
      ->setProgressMessage('')
      ->addOperation([LegacyProjectRemapBatch::class, 'run'], [$legacyId, $targetId, $mapping])
      ->setFinishCallback([LegacyProjectRemapBatch::class, 'finished']);
    batch_set($batch->toArray());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Submission is fully handled by the step-specific #submit handlers
    // above; this exists only to satisfy FormBase's abstract method.
  }

}
