<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Routing;

use Drupal\admin_theme\Routing\AdminThemeAdminContext;
use Symfony\Component\Routing\Route;

/**
 * Admin Theme's admin context, made safe for empty path lists.
 *
 * Admin Theme checks its "Exclude" and "Include" path lists with core's
 * request_path condition, and that condition treats an empty list as matching
 * every page. So an empty "Exclude" list marks every page as not an admin
 * page, and the site stops using the administration theme everywhere. The
 * module ships a placeholder path to avoid this, but anything that saves the
 * lists empty (the Appearance form, drush, a config import) puts the site back
 * in that state.
 *
 * This version treats an empty list as matching no pages, which is what an
 * empty list on the Appearance form means to the person who saved it.
 *
 * @see \Drupal\mukurtu_core\MukurtuCoreServiceProvider::alter()
 * @see \Drupal\system\Plugin\Condition\RequestPath::evaluate()
 */
class AdminThemePathsAdminContext extends AdminThemeAdminContext {

  /**
   * {@inheritdoc}
   */
  public function isAdminRoute(?Route $route = NULL) {
    $settings = $this->configFactory->get('admin_theme.settings');

    if ($this->pathsMatchCurrentRequest((string) $settings->get('exclude_paths'))) {
      return FALSE;
    }

    if ($this->pathsMatchCurrentRequest((string) $settings->get('paths'))) {
      return TRUE;
    }

    return $this->subject->isAdminRoute($route);
  }

  /**
   * Checks whether the current request matches a list of paths.
   *
   * @param string $paths
   *   Paths, one per line, in the request_path condition's format.
   *
   * @return bool
   *   TRUE if the current request matches one of the paths. FALSE if it
   *   matches none of them, or the list is empty.
   */
  protected function pathsMatchCurrentRequest(string $paths): bool {
    if (trim($paths) === '') {
      return FALSE;
    }

    /** @var \Drupal\Core\Condition\ConditionInterface $condition */
    $condition = $this->conditionManager->createInstance('request_path', ['pages' => $paths]);
    return (bool) $this->conditionManager->execute($condition);
  }

}
