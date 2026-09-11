<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Flags development modules left over from earlier Mukurtu releases.
 *
 * Devel and Devel Generate used to be listed in the install profile's
 * dependencies, so every site installed before that changed still has them
 * enabled. Mukurtu does not uninstall them, because a site owner may be using
 * them deliberately, but it is worth surfacing on the status report: 9 of
 * devel's routes are gated on core's 'administer site configuration' rather
 * than on a devel permission, and the Mukurtu Manager role holds that
 * permission for unrelated reasons.
 */
class DevelopmentModuleRequirements {

  use StringTranslationTrait;

  /**
   * Modules to report on, keyed by module name with their human-readable name.
   */
  protected const DEVELOPMENT_MODULES = [
    'devel' => 'Devel',
    'devel_generate' => 'Devel Generate',
    'devel_php' => 'Devel PHP',
  ];

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $installed = [];
    foreach (static::DEVELOPMENT_MODULES as $module => $name) {
      if ($this->moduleHandler->moduleExists($module)) {
        $installed[] = $name;
      }
    }

    if (empty($installed)) {
      return [];
    }

    return [
      'mukurtu_core_development_modules' => [
        'title' => $this->t('Development modules'),
        'value' => $this->t('@modules enabled', [
          '@modules' => implode(', ', $installed),
        ]),
        'description' => $this->t('These modules are development tools and are no longer installed with Mukurtu. They let anyone with the Administer site configuration permission edit raw configuration and state values and reinstall modules, which includes the Mukurtu Manager role. Uninstall them on the <a href=":url">Extend page</a> unless you are actively developing on this site.', [
          ':url' => '/admin/modules/uninstall',
        ]),
        'severity' => RequirementSeverity::Warning,
      ],
    ];
  }

}
