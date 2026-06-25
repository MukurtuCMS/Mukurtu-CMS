<?php

declare(strict_types=1);

namespace Drupal\mukurtu_setup;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
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
        'create_mukurtu_manager',
        (string) $this->t('Create a Mukurtu Manager account'),
        Markup::create((string) $this->t('The administrator account created when installing Mukurtu has full access to the Drupal administrator tools which are usually only necessary for maintenance and troubleshooting. Administrator accounts should be used with caution. <strong>We strongly recommend creating a separate Mukurtu Manager account for day to day use.</strong> Learn more at <a href="https://docs.mukurtu.org/users/user-role-types/">User Roles</a>.')),
        self::GROUP_REQUIRED,
        TRUE,
        '/admin/people/create',
        (string) $this->t('Create account'),
      ),
      new SiteSetupTask(
        'create_community',
        (string) $this->t('Create a community and cultural protocol'),
        Markup::create((string) $this->t('To create any content, at least one community and cultural protocol must be created. Communities represent the groups responsible for creating and stewarding content, and cultural protocols are the means of providing appropriate access to content. This will also direct you to create a cultural protocol. Learn more at <a href="https://docs.mukurtu.org/communities-cultural-protocols-categories/UnderstandingCommunitiesAndCulturalProtocols/">Understanding Communities and Cultural Protocols</a>.')),
        self::GROUP_REQUIRED,
        TRUE,
        '/communities/community/add',
        (string) $this->t('Add community'),
      ),
      new SiteSetupTask(
        'create_category',
        (string) $this->t('Create a category'),
        Markup::create((string) $this->t('To create digital heritage items, at least one category must be added. Learn more at <a href="https://docs.mukurtu.org/communities-cultural-protocols-categories/UnderstandingCategories/">Understanding Categories</a>.')),
        self::GROUP_REQUIRED,
        TRUE,
        '/admin/structure/taxonomy/manage/category/add',
        (string) $this->t('Add category'),
      ),
      new SiteSetupTask(
        'dictionary_language',
        (string) $this->t('Add a dictionary language'),
        (string) $this->t('To create dictionary words, at least one language must be added.'),
        self::GROUP_REQUIRED,
        TRUE,
        '/admin/structure/taxonomy/manage/language/add',
        (string) $this->t('Add language'),
        dismissible: TRUE,
      ),
      new SiteSetupTask(
        'site_name_email',
        (string) $this->t('Update site name and email'),
        Markup::create((string) $this->t('If not already set during site installation, update your site name and administrative email. Learn more at <a href="https://docs.mukurtu.org/site-settings/ConfigureBasicSettings/#configure-site-name-and-email">Configure Basic Site Settings</a>.')),
        self::GROUP_RECOMMENDED,
        FALSE,
        '/admin/config/system/site-information',
        (string) $this->t('Edit site information'),
      ),
      new SiteSetupTask(
        'site_logo',
        (string) $this->t('Change site logo'),
        Markup::create((string) $this->t('Replace the Mukurtu logo with your organization or community logo. Learn more at <a href="https://docs.mukurtu.org/look-and-feel/ConfigureLogo/#configure-your-logo">Configure Logos</a>.')),
        self::GROUP_RECOMMENDED,
        TRUE,
        '/admin/appearance/settings/mukurtu_v4',
        (string) $this->t('Edit theme settings'),
      ),
      new SiteSetupTask(
        'front_page',
        (string) $this->t('Configure landing page'),
        Markup::create((string) $this->t('Update the front/landing page to welcome and orient your users. Learn more at <a href="https://docs.mukurtu.org/look-and-feel/ConfigureLandingPage/">Configure Landing Page</a>.')),
        self::GROUP_RECOMMENDED,
        FALSE,
        '/node/1/layout',
        (string) $this->t('View front page'),
      ),
      new SiteSetupTask(
        'about_page',
        (string) $this->t('Create an about page'),
        Markup::create((string) $this->t('Add a page that provides more information about the site. See below for adding a new page to the navigation menu. Learn more at <a href="https://docs.mukurtu.org/look-and-feel/CreateBasicPage/">Create Basic Pages</a>.')),
        self::GROUP_RECOMMENDED,
        TRUE,
        '/node/add/page',
        (string) $this->t('Create a page'),
      ),
      new SiteSetupTask(
        'navigation_menu',
        (string) $this->t('Configure navigation menu'),
        Markup::create((string) $this->t('Add, remove, rename, and reorder your main navigation menu. Learn more at <a href="https://docs.mukurtu.org/look-and-feel/ConfigureSiteNavigation/">Configure Site Navigation</a>.')),
        self::GROUP_RECOMMENDED,
        FALSE,
        '/admin/structure/menu/manage/main',
        (string) $this->t('Edit menu'),
      ),
      new SiteSetupTask(
        'site_footer',
        (string) $this->t('Configure site footer'),
        (string) $this->t('Update your site footer with contact information, logos, links, and other information. Learn more at LINK TBD.'),
        self::GROUP_RECOMMENDED,
        TRUE,
        '/admin/content/block/1',
        (string) $this->t('Edit footer content'),
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
        'dictionary_language' => $this->taxonomyTermExists('language'),
        'create_mukurtu_manager' => $this->mukurtuManagerExists(),
        'site_logo' => $this->isSiteLogoSet(),
        'site_footer' => $this->isFooterSet(),
        'about_page' => $this->aboutPageExists(),
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

  private function mukurtuManagerExists(): bool {
    $ids = $this->entityTypeManager
      ->getStorage('user')
      ->getQuery()
      ->condition('roles', 'mukurtu_manager')
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    return !empty($ids);
  }

  private function aboutPageExists(): bool {
    $ids = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'basic_page')
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->execute();
    return !empty($ids);
  }

}
