<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Update\UpdateHookRegistry;

/**
 * Reports a site that reached 4.0.1 without finishing the 4.0.0 updates.
 *
 * 4.0.1 removed every update hook the 4.0.x betas accumulated and replaced them
 * with hook_update_last_removed() baselines.
 *
 * This is the half core does not cover. Core's own
 * SystemRequirementsHooks::checkRequirements() does compare each module's
 * installed schema version against its hook_update_last_removed() and reports
 * an error for any that are behind, so the update phase is already handled
 * without us. But that check is gated on $phase == 'update', so it is invisible
 * on the status report.
 *
 * That gap matters because running updates is a separate act from deploying the
 * code. An operator who updates the code and never runs updb sees nothing at
 * all: no pending updates to run, no warning anywhere in the admin UI, and a
 * site quietly operating against stale configuration. This surfaces it there,
 * and keeps surfacing it until the site is actually brought up to date.
 *
 * Worth knowing when reasoning about the update-phase counterpart: an
 * error-severity requirement does not hard-stop drush. It is raised as a
 * confirmable prompt, so `drush updb -y` prints the error and proceeds. See
 * docs/update-hooks.md for the verified behaviour.
 *
 * @see mukurtu_core_update_requirements()
 * @see \Drupal\system\Hook\SystemRequirementsHooks::checkRequirements()
 */
final class UpdatePathRequirements {

  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly UpdateHookRegistry $updateHookRegistry,
  ) {}

  /**
   * Finds Mukurtu modules whose schema predates the updates 4.0.1 removed.
   *
   * Generic over hook_update_last_removed() rather than carrying a table of
   * version numbers, so it needs no maintenance when a later release removes
   * hooks again.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Update\UpdateHookRegistry $registry
   *   The update hook registry.
   *
   * @return array<string, array{installed: int, required: int}>
   *   Modules that are behind, keyed by module name. Empty when the site is
   *   up to date, which is the case for every fresh install.
   */
  public static function modulesBehind(ModuleHandlerInterface $moduleHandler, UpdateHookRegistry $registry): array {
    $behind = [];

    foreach (array_keys($moduleHandler->getModuleList()) as $module) {
      // The profile itself is a module and had update hooks of its own.
      if ($module !== 'mukurtu' && !str_starts_with($module, 'mukurtu_')) {
        continue;
      }

      $moduleHandler->loadInclude($module, 'install');
      $function = $module . '_update_last_removed';
      if (!function_exists($function)) {
        continue;
      }

      $installed = $registry->getInstalledVersion($module);
      // A module that is not installed has nothing to catch up on.
      if ($installed === UpdateHookRegistry::SCHEMA_UNINSTALLED) {
        continue;
      }

      $required = (int) $function();
      if ($installed < $required) {
        $behind[$module] = ['installed' => $installed, 'required' => $required];
      }
    }

    return $behind;
  }

  /**
   * Renders the module list shown to the operator.
   *
   * The schema numbers are included deliberately. This is the message that
   * gets pasted into a support conversation, and the numbers are what
   * identify how far behind a site actually is.
   *
   * @param array<string, array{installed: int, required: int}> $behind
   *   Modules that are behind, as returned by static::modulesBehind().
   *
   * @return string
   *   A comma separated list, for example "mukurtu_core (at 40118, needs
   *   40123)".
   */
  public static function formatModuleList(array $behind): string {
    $parts = [];
    foreach ($behind as $module => $versions) {
      $parts[] = sprintf('%s (at %d, needs %d)', $module, $versions['installed'], $versions['required']);
    }

    return implode(', ', $parts);
  }

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $behind = static::modulesBehind($this->moduleHandler, $this->updateHookRegistry);
    if (empty($behind)) {
      return [];
    }

    return [
      'mukurtu_core_update_path' => [
        'title' => $this->t('Mukurtu update path'),
        'value' => $this->t('Update to 4.0.0 was not completed'),
        'description' => $this->t('This site is running Mukurtu 4.0.1, but never ran database updates that Mukurtu 4.0.0 shipped. Mukurtu 4.0.1 removed them, so they can no longer run and some configuration may be out of date. To resolve this: install Mukurtu 4.0.0, run all database updates, then install Mukurtu 4.0.1 again. Modules affected: @modules.', [
          '@modules' => static::formatModuleList($behind),
        ]),
        'severity' => RequirementSeverity::Error,
      ],
    ];
  }

}
