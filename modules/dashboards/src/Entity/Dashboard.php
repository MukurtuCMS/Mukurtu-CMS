<?php

namespace Drupal\dashboards\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionListInterface;
use Drupal\layout_builder\SectionListTrait;

/**
 * Dashboard entity class.
 *
 * @ConfigEntityType(
 *   id = "dashboard",
 *   label = @Translation("Dashboard"),
 *   label_collection = @Translation("Dashboards"),
 *   label_singular = @Translation("Dashboard"),
 *   label_plural = @Translation("Dashboards"),
 *   label_count = @PluralTranslation(
 *     singular = "@count dashboard",
 *     plural = "@count dashboards",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\dashboards\Entity\DashboardStorage",
 *     "access" = "Drupal\dashboards\Entity\DashboardAccessControlHandler",
 *     "view_builder" = "Drupal\dashboards\Entity\DashboardViewBuilder",
 *     "list_builder" = "Drupal\dashboards\Entity\DashboardListBuilder",
 *     "form" = {
 *       "default" = "Drupal\dashboards\Form\DashboardForm",
 *       "view" = "Drupal\dashboards\Form\DashboardForm",
 *       "edit" = "Drupal\dashboards\Form\DashboardForm",
 *       "delete" = "Drupal\dashboards\Form\DashboardDeleteForm",
 *       "layout_builder" = "Drupal\dashboards\Form\DashboardLayoutBuilderForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *       "permissions" = "Drupal\user\Entity\EntityPermissionsRouteProvider"
 *     }
 *   },
 *   bundle_entity_type = "dashboard",
 *   bundle_of = "dashboard",
 *   links = {
 *     "canonical" = "/dashboard/{dashboard}",
 *     "delete-form" = "/admin/structure/dashboards/manage/{dashboard}/delete",
 *     "edit-form" = "/admin/structure/dashboards/manage/{dashboard}",
 *     "add-form" = "/admin/structure/dashboards/add",
 *     "collection" = "/admin/structure/dashboards",
 *     "entity-permissions-form" = "/admin/structure/dashboards/manage/{dashboard}/permissions"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "admin_label",
 *     "weight" = "weight"
 *   },
 *   admin_permission = "administer dashboards",
 *   config_export = {
 *     "id",
 *     "admin_label",
 *     "category",
 *     "sections",
 *     "frontend",
 *     "weight",
 *   }
 * )
 *
 * @package Drupal\dashboards\Entity
 */
class Dashboard extends ConfigEntityBase implements SectionListInterface {
  use SectionListTrait;

  const CONTEXT_TYPE = 'entity';

  /**
   * Admin label.
   *
   * @var string
   */
  public $admin_label;

  /**
   * Entity id.
   *
   * @var string
   */
  public $id;

  /**
   * Category.
   *
   * @var string
   */
  public $category;

  /**
   * Show this dashboard always in frontend.
   *
   * @var bool
   */
  public $frontend = FALSE;

  /**
   * Section.
   *
   * @var \Drupal\layout_builder\Section[]
   */
  public $sections = [];

  /**
   * Weight.
   *
   * @var int
   */
  public $weight = 0;

  /**
   * Is overridden.
   *
   * @var bool
   */
  public $isOverridden = FALSE;

  /**
   * Gets the layout sections.
   *
   * @return \Drupal\layout_builder\Section[]
   *   A sequentially and numerically keyed array of section objects.
   */
  public function getSections() {
    return $this->get('sections');
  }

  /**
   * Stores the information for all sections.
   *
   * Implementations of this method are expected to call array_values() to rekey
   * the list of sections.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   An array of section objects.
   *
   * @return $this
   */
  protected function setSections(array $sections) {
    $this->sections = array_values($sections);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:dashboards_list',
      'user:' . \Drupal::currentUser()->id(),
      'dashboards:' . $this->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function isLayoutBuilderEnabled() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * Should this dashboard rendered in frontend theme.
   *
   * @return bool
   *   Return true if is correct.
   */
  public function showAlwaysInFrontend() {
    return $this->frontend;
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    /**
     * @var \Drupal\user\UserDataInterface
     */
    $userData = \Drupal::service('user.data');
    foreach ($entities as $entity) {
      $userData->delete('dashboards', NULL, $entity->id());
    }
  }

  /**
   * Check if is overridden.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account to check.
   *
   * @return bool
   *   True if user data is present.
   */
  public function isOverridden(?AccountInterface $account = NULL): bool {
    if (!$account) {
      $account = \Drupal::currentUser();
    }
    /**
     * @var \Drupal\user\UserDataInterface
     */
    $dataService = \Drupal::service('user.data');
    $data = $dataService->get('dashboards', $account->id(), $this->id());
    if ($data) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Loading sections from user data.
   */
  public function loadOverrides() {
    $dataService = \Drupal::service('user.data');
    $account = \Drupal::currentUser();
    $data = $dataService->get('dashboards', $account->id(), $this->id());
    $this->set('sections', []);
    if ($data) {
      try {
        $data = unserialize($data, ['allowed_classes' => FALSE]);
        $sections = array_map([Section::class, 'fromArray'], $data);
        $this->set('sections', $sections);
      }
      catch (\Exception $ex) {
        $this->set('sections', []);
      }
    }
  }

}
