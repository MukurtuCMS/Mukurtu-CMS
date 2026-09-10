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
 * 4.0.1 removed every update hook the 4.0.x betas accumulated and replaced
 * them with hook_update_last_removed(). Core reads that value in only two
 * places, neither of which stops a site that is too far behind:
 * ModuleInstaller::install() uses it to seed a fresh install, and
 * _update_fix_missing_schema() uses it to repair a missing schema entry.
 * update_get_update_list() simply lists updates above the installed version,
 * and once the hooks are deleted there is nothing left to list.
 *
 * The practical consequence is that a site several releases behind reports
 * "no pending updates" and carries on running against stale configuration,
 * rather than being stopped. Detecting that is left entirely to us.
 *
 * Two implementations cover the two ways an operator arrives here. The update
 * phase one in mukurtu_core.install blocks update.php and drush updb before
 * anything runs. This one covers the site that never ran updates at all, and
 * so would never have seen that message.
 *
 * @see mukurtu_core_update_requirements()
 * @see \Drupal\Core\Extension\ModuleInstaller::install()
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
