<?php

declare(strict_types=1);

namespace Drupal\mukurtu_setup;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Site\Settings;
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
  const GROUP_SITE_OPERATIONS = 'site_operations';

  /**
   * Consider cron healthy if it has run within this many seconds.
   */
  const CRON_MAX_AGE = 86400;

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
        FALSE,
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
      new SiteSetupTask(
        'set_cron',
        (string) $this->t('Set up automated cron'),
        (string) $this->t("Cron runs scheduled background work: search indexing, notifications, and cleanup. On a live site, have your server run Mukurtu's cron every 15 to 60 minutes instead of relying on visitor traffic."),
        self::GROUP_SITE_OPERATIONS,
        TRUE,
        '/admin/config/system/cron',
        (string) $this->t('Configure cron'),
      ),
      new SiteSetupTask(
        'web_analytics',
        (string) $this->t('Set up web analytics'),
        (string) $this->t('Connect Google Analytics or Google Tag Manager to see how visitors use your site. Mukurtu also includes a built-in Visitors report.'),
        self::GROUP_SITE_OPERATIONS,
        TRUE,
        '/admin/config/services/google-tag/containers',
        (string) $this->t('Set up Google Tag'),
      ),
      new SiteSetupTask(
        'private_files',
        (string) $this->t('Set the private file system path'),
        (string) $this->t('Protected media and restricted downloads are served from the private file system. If the private file path is not set, protected files cannot be served safely. This is set in settings.php by whoever hosts your site.'),
        self::GROUP_SITE_OPERATIONS,
        TRUE,
        '/admin/config/media/file-system',
        (string) $this->t('View file system settings'),
      ),
      new SiteSetupTask(
        'trusted_hosts',
        (string) $this->t('Set trusted host patterns'),
        (string) $this->t("Trusted host patterns tell Drupal which domains may serve your site, blocking Host header spoofing. Set this in settings.php to match your site's real domain instead of leaving it open."),
        self::GROUP_SITE_OPERATIONS,
        TRUE,
        '/admin/reports/status',
        (string) $this->t('View status report'),
      ),
      new SiteSetupTask(
        'cookie_consent',
        (string) $this->t('Review the cookie consent banner'),
        (string) $this->t('Mukurtu uses Klaro to ask visitors for consent before loading third-party embeds and trackers. Review the consent categories and services so the banner matches what your site actually loads.'),
        self::GROUP_SITE_OPERATIONS,
        FALSE,
        '/admin/config/user-interface/klaro',
        (string) $this->t('Open Klaro settings'),
      ),
      new SiteSetupTask(
        'bot_protection',
        (string) $this->t('Review spam and bot protection'),
        (string) $this->t('Mukurtu ships with CAPTCHA and Honeypot enabled. Review the settings and add reCAPTCHA or Cloudflare Turnstile keys if you want a stronger challenge on public forms.'),
        self::GROUP_SITE_OPERATIONS,
        FALSE,
        '/admin/config/people/captcha/mukurtu-bot-protection',
        (string) $this->t('Open bot protection settings'),
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
      self::GROUP_SITE_OPERATIONS => [],
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
        'create_category' => $this->taxonomyTermExists('category'),
        'dictionary_language' => $this->taxonomyTermExists('language'),
        'create_mukurtu_manager' => $this->mukurtuManagerExists(),
        'site_logo' => $this->isSiteLogoSet(),
        'site_footer' => $this->isFooterSet(),
        'set_cron' => $this->isCronRecent(),
        'web_analytics' => $this->isAnalyticsConfigured(),
        'private_files' => $this->isPrivateFilePathSet(),
        'trusted_hosts' => $this->areTrustedHostsSet(),
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
      Cache::invalidateTags(['mukurtu_setup:tasks']);
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
    Cache::invalidateTags(['mukurtu_setup:tasks']);
  }

  /**
   * Manually marks a task as complete.
   */
  public function markComplete(string $taskId): void {
    $completed = $this->getManuallyCompletedTaskIds();
    if (!in_array($taskId, $completed, TRUE)) {
      $completed[] = $taskId;
      $this->state->set(self::STATE_COMPLETED, $completed);
      Cache::invalidateTags(['mukurtu_setup:tasks']);
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
    Cache::invalidateTags(['mukurtu_setup:tasks']);
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

  /**
   * Whether cron has run within self::CRON_MAX_AGE seconds.
   */
  private function isCronRecent(): bool {
    $last = (int) $this->state->get('system.cron_last', 0);
    return $last > 0 && (time() - $last) < self::CRON_MAX_AGE;
  }

  /**
   * Whether at least one Google Tag container is configured.
   */
  private function isAnalyticsConfigured(): bool {
    if (!$this->entityTypeManager->hasDefinition('google_tag_container')) {
      return FALSE;
    }
    $count = $this->entityTypeManager
      ->getStorage('google_tag_container')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    return $count > 0;
  }

  /**
   * Whether the private file system path is configured in settings.php.
   */
  private function isPrivateFilePathSet(): bool {
    return trim((string) Settings::get('file_private_path', '')) !== '';
  }

  /**
   * Whether trusted host patterns are set to something other than a catch-all.
   */
  private function areTrustedHostsSet(): bool {
    $patterns = array_filter(array_map('trim', (array) Settings::get('trusted_host_patterns', [])));
    if (empty($patterns)) {
      return FALSE;
    }
    $catch_all = ['.*', '^.*$', '^.+$', '.+'];
    return (bool) array_diff($patterns, $catch_all);
  }

}
