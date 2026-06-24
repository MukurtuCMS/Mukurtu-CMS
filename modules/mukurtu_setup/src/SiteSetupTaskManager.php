<?php

declare(strict_types=1);

namespace Drupal\mukurtu_setup;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Manages site setup tasks: definition, completion detection, and state.
 */
class SiteSetupTaskManager {

  use StringTranslationTrait;

  const STATE_DISMISSED = 'mukurtu_setup.dismissed_tasks';
  const STATE_COMPLETED = 'mukurtu_setup.completed_tasks';

  const GROUP_REQUIRED = 'required';
  const GROUP_RECOMMENDED = 'recommended';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected StateInterface $state,
  ) {}

  /**
   * Returns all defined setup tasks.
   *
   * @return SiteSetupTask[]
   */
  public function getTasks(): array {
    return [
      new SiteSetupTask(
        'create_community',
        (string) $this->t('Create a community'),
        (string) $this->t('Communities are the core organizational unit of Mukurtu. Create at least one to get started.'),
        self::GROUP_REQUIRED,
        TRUE,
        '/admin/content/communities/add',
        (string) $this->t('Add community'),
      ),
      new SiteSetupTask(
        'create_protocol',
        (string) $this->t('Create a cultural protocol'),
        (string) $this->t('Cultural protocols control who can access your content. Create at least one to assign to digital heritage items.'),
        self::GROUP_REQUIRED,
        TRUE,
        '/admin/content/protocols/add',
        (string) $this->t('Add cultural protocol'),
      ),
      new SiteSetupTask(
        'create_category',
        (string) $this->t('Create a category'),
        (string) $this->t('Categories help organize your digital heritage content. Add at least one category to your taxonomy.'),
        self::GROUP_REQUIRED,
        TRUE,
        '/admin/structure/taxonomy/manage/category/add',
        (string) $this->t('Add category'),
      ),
      new SiteSetupTask(
        'site_name_email',
        (string) $this->t('Set your site name and email'),
        (string) $this->t('Update your site name and contact email address in Site Information.'),
        self::GROUP_RECOMMENDED,
        TRUE,
        '/admin/config/system/site-information',
        (string) $this->t('Edit site information'),
      ),
      new SiteSetupTask(
        'site_logo',
        (string) $this->t('Upload a site logo'),
        (string) $this->t("Add your organization's logo to personalize your Mukurtu site."),
        self::GROUP_RECOMMENDED,
        TRUE,
        '/admin/appearance/settings/mukurtu_v4',
        (string) $this->t('Edit theme settings'),
      ),
      new SiteSetupTask(
        'site_footer',
        (string) $this->t('Set up your site footer'),
        (string) $this->t('Add footer content such as contact information, copyright, or links.'),
        self::GROUP_RECOMMENDED,
        TRUE,
        '/admin/content/block-content',
        (string) $this->t('Edit footer content'),
      ),
      new SiteSetupTask(
        'front_page',
        (string) $this->t('Customize your front page'),
        (string) $this->t('Edit the default homepage to reflect your organization and collection.'),
        self::GROUP_RECOMMENDED,
        FALSE,
        '/',
        (string) $this->t('View front page'),
      ),
      new SiteSetupTask(
        'about_page',
        (string) $this->t('Create an About page'),
        (string) $this->t('Add a page that describes your organization and its mission.'),
        self::GROUP_RECOMMENDED,
        TRUE,
        '/node/add/basic_page',
        (string) $this->t('Create a page'),
      ),
      new SiteSetupTask(
        'content_warnings',
        (string) $this->t('Configure media content warnings'),
        (string) $this->t('Set up content warnings for sensitive media, such as images of deceased individuals.'),
        self::GROUP_RECOMMENDED,
        TRUE,
        '/admin/config/mukurtu/content-warnings',
        (string) $this->t('Configure content warnings'),
      ),
    ];
  }

  /**
   * Returns tasks grouped by group key.
   *
   * @return array<string, SiteSetupTask[]>
   */
  public function getTaskGroups(): array {
    $groups = [
      self::GROUP_REQUIRED => [],
      self::GROUP_RECOMMENDED => [],
    ];
    foreach ($this->getTasks() as $task) {
      $groups[$task->getGroup()][$task->getId()] = $task;
    }
    return $groups;
  }

  /**
   * Returns whether a task is complete (auto-detected or manually marked).
   */
  public function isComplete(string $taskId): bool {
    if (in_array($taskId, $this->getManuallyCompletedTaskIds(), TRUE)) {
      return TRUE;
    }
    try {
      return match ($taskId) {
        'create_community' => $this->entityExists('community'),
        'create_protocol' => $this->entityExists('protocol'),
        'create_category' => $this->taxonomyTermExists('category'),
        'site_name_email' => $this->isSiteNameSet(),
        'site_logo' => $this->isSiteLogoSet(),
        'site_footer' => $this->isFooterSet(),
        'about_page' => $this->aboutPageExists(),
        'content_warnings' => $this->taxonomyTermExists('media_tag'),
        default => FALSE,
      };
    }
    catch (\Exception) {
      return FALSE;
    }
  }

  /**
   * Returns whether a task has been dismissed.
   */
  public function isDismissed(string $taskId): bool {
    return in_array($taskId, $this->getDismissedTaskIds(), TRUE);
  }

  /**
   * Dismisses a task.
   */
  public function dismiss(string $taskId): void {
    $dismissed = $this->getDismissedTaskIds();
    if (!in_array($taskId, $dismissed, TRUE)) {
      $dismissed[] = $taskId;
      $this->state->set(self::STATE_DISMISSED, $dismissed);
    }
  }

  /**
   * Restores a dismissed task.
   */
  public function restore(string $taskId): void {
    $dismissed = array_values(array_filter(
      $this->getDismissedTaskIds(),
      fn($id) => $id !== $taskId,
    ));
    $this->state->set(self::STATE_DISMISSED, $dismissed);
  }

  /**
   * Manually marks a task as complete.
   */
  public function markComplete(string $taskId): void {
    $completed = $this->getManuallyCompletedTaskIds();
    if (!in_array($taskId, $completed, TRUE)) {
      $completed[] = $taskId;
      $this->state->set(self::STATE_COMPLETED, $completed);
    }
  }

  /**
   * Removes a manual completion mark.
   */
  public function markIncomplete(string $taskId): void {
    $completed = array_values(array_filter(
      $this->getManuallyCompletedTaskIds(),
      fn($id) => $id !== $taskId,
    ));
    $this->state->set(self::STATE_COMPLETED, $completed);
  }

  /**
   * Returns counts of complete vs. visible (non-dismissed) tasks.
   *
   * @return array{complete: int, total: int}
   */
  public function getCounts(): array {
    $total = 0;
    $complete = 0;
    foreach ($this->getTasks() as $task) {
      if ($this->isDismissed($task->getId())) {
        continue;
      }
      $total++;
      if ($this->isComplete($task->getId())) {
        $complete++;
      }
    }
    return ['complete' => $complete, 'total' => $total];
  }

  private function getDismissedTaskIds(): array {
    return $this->state->get(self::STATE_DISMISSED, []);
  }

  private function getManuallyCompletedTaskIds(): array {
    return $this->state->get(self::STATE_COMPLETED, []);
  }

  private function entityExists(string $entityType): bool {
    $ids = $this->entityTypeManager
      ->getStorage($entityType)
      ->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    return !empty($ids);
  }

  private function taxonomyTermExists(string $vid): bool {
    $ids = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('vid', $vid)
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    return !empty($ids);
  }

  private function isSiteNameSet(): bool {
    $config = $this->configFactory->get('system.site');
    $mail = $config->get('mail') ?? '';
    // Consider set if the contact email is not an example.com placeholder.
    return !empty($mail) && !str_contains($mail, 'example.com');
  }

  private function isSiteLogoSet(): bool {
    $config = $this->configFactory->get('mukurtu_v4.settings');
    return !(bool) ($config->get('logo.use_default') ?? TRUE);
  }

  private function isFooterSet(): bool {
    $ids = $this->entityTypeManager
      ->getStorage('block_content')
      ->getQuery()
      ->condition('type', 'mukurtu_footer')
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    if (empty($ids)) {
      return FALSE;
    }
    $footer = $this->entityTypeManager->getStorage('block_content')->load(reset($ids));
    if (!$footer) {
      return FALSE;
    }
    $body = $footer->get('body')->first();
    if ($body && !empty($body->value)) {
      return TRUE;
    }
    return !$footer->get('field_footer_logos')->isEmpty()
      || !$footer->get('field_footer_social_links')->isEmpty()
      || !$footer->get('field_footer_other_links')->isEmpty();
  }

  private function aboutPageExists(): bool {
    $ids = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'basic_page')
      ->condition('title', '%about%', 'LIKE')
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    return !empty($ids);
  }

}
