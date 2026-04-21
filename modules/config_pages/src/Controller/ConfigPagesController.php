<?php

namespace Drupal\config_pages\Controller;

use Drupal\config_pages\ConfigPagesInterface;
use Drupal\config_pages\ConfigPagesTypeInterface;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\config_pages\Form\ConfigPagesClearConfirmationForm;
use Drupal\config_pages\Form\ConfigPagesImportConfirmationForm;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for ConfigPage entity.
 */
class ConfigPagesController extends ControllerBase {

  /**
   * The config page storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $configPagesStorage;

  /**
   * The config page type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $configPagesTypeStorage;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type_manager->getStorage('config_pages'),
      $entity_type_manager->getStorage('config_pages_type'),
      $container->get('theme_handler'),
      $entity_type_manager,
      $container->get('form_builder')
    );
  }

  /**
   * Constructs a ConfigPages object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $config_pages_storage
   *   The config page storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $config_pages_type_storage
   *   The config page type storage.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(
    EntityStorageInterface $config_pages_storage,
    EntityStorageInterface $config_pages_type_storage,
    ThemeHandlerInterface $theme_handler,
    EntityTypeManagerInterface $entity_type_manager,
    FormBuilderInterface $form_builder,
  ) {
    $this->configPagesStorage = $config_pages_storage;
    $this->configPagesTypeStorage = $config_pages_type_storage;
    $this->themeHandler = $theme_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * Presents the config page creation form.
   *
   * @param \Drupal\config_pages\ConfigPagesTypeInterface $config_pages_type
   *   The config page type to add.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return array
   *   A form array as expected by drupal_render().
   */
  public function addForm(ConfigPagesTypeInterface $config_pages_type, Request $request) {
    $config_page = $this->configPagesStorage->create(
      [
        'type' => $config_pages_type->id(),
      ]);
    return $this->entityFormBuilder()->getForm($config_page);
  }

  /**
   * Provides the page title for this controller.
   *
   * @param \Drupal\config_pages\ConfigPagesTypeInterface $config_pages_type
   *   The config page type being added.
   *
   * @return string
   *   The page title.
   */
  public function getAddFormTitle(ConfigPagesTypeInterface $config_pages_type) {
    $config_pages_types = ConfigPagesType::loadMultiple();
    $config_pages_type = $config_pages_types[$config_pages_type->id()];
    return $this->t('Add %type config page', ['%type' => $config_pages_type->label()]);
  }

  /**
   * Presents the config page creation/edit form.
   *
   * @param \Drupal\config_pages\ConfigPagesTypeInterface|null $config_pages_type
   *   The config page type to add.
   *
   * @return array
   *   A form array as expected by drupal_render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function classInit(?ConfigPagesTypeInterface $config_pages_type = NULL) {
    $config_page = $this->getConfigPage($config_pages_type);

    return $this->entityFormBuilder()->getForm($config_page);
  }

  /**
   * Load or create a config page entity for the given type.
   *
   * @param \Drupal\config_pages\ConfigPagesTypeInterface $config_pages_type
   *   The config page type.
   *
   * @return \Drupal\config_pages\ConfigPagesInterface
   *   The config page entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getConfigPage(ConfigPagesTypeInterface $config_pages_type) {
    $cp_type = $config_pages_type->id();
    $typeEntity = ConfigPagesType::load($cp_type);

    if (empty($typeEntity)) {
      throw new NotFoundHttpException();
    }

    $contextData = $typeEntity->getContextData();

    $config_page_ids = $this->entityTypeManager
      ->getStorage('config_pages')
      ->getQuery()
      ->accessCheck()
      ->condition('type', $cp_type)
      ->condition('context', $contextData)
      ->execute();

    // Fallback: try loading with empty context hash.
    // This handles the case where context was recently enabled but existing
    // entities still have the old no-context hash.
    if (empty($config_page_ids)) {
      $emptyContextHash = serialize([]);
      if ($contextData !== $emptyContextHash) {
        $config_page_ids = $this->entityTypeManager
          ->getStorage('config_pages')
          ->getQuery()
          ->accessCheck()
          ->condition('type', $cp_type)
          ->condition('context', $emptyContextHash)
          ->execute();
      }
    }

    if (!empty($config_page_ids)) {
      $config_page_id = array_shift($config_page_ids);
      $entityStorage = $this->entityTypeManager->getStorage('config_pages');
      /** @var \Drupal\config_pages\ConfigPagesInterface $config_page */
      $config_page = $entityStorage->load($config_page_id);
    }
    else {
      /** @var \Drupal\config_pages\ConfigPagesInterface $config_page */
      $config_page = $this->configPagesStorage->create([
        'type' => $cp_type,
      ]);
    }

    return $config_page;
  }

  /**
   * Custom access check for config page routes.
   *
   * @param \Drupal\config_pages\ConfigPagesTypeInterface $config_pages_type
   *   The config page type.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(ConfigPagesTypeInterface $config_pages_type) {
    $config_page = $this->getConfigPage($config_pages_type);

    // Get the access result.
    $access_result = $config_page->access('update', NULL, TRUE);

    // Ensure we have an AccessResult object.
    if (!$access_result instanceof AccessResultInterface) {
      // Convert boolean to AccessResult.
      $access_result = $access_result ?
        AccessResult::allowed() :
        AccessResult::forbidden();
    }

    return $access_result->cachePerPermissions();
  }

  /**
   * Presents the config page confirmation form.
   *
   * @param \Drupal\config_pages\ConfigPagesInterface $config_pages
   *   Config Page.
   *
   * @return array
   *   A form array as expected by drupal_render().
   */
  public function clearConfirmation(ConfigPagesInterface $config_pages) {
    return $this->formBuilder->getForm(ConfigPagesClearConfirmationForm::class, $config_pages->id());
  }

  /**
   * Presents the config page import confirmation form.
   *
   * @param \Drupal\config_pages\ConfigPagesInterface $config_pages
   *   Config Page.
   * @param string $imported_entity_id
   *   The ID of the entity to import from.
   *
   * @return array
   *   A form array as expected by drupal_render().
   */
  public function importConfirmation(ConfigPagesInterface $config_pages, $imported_entity_id) {
    return $this->formBuilder->getForm(ConfigPagesImportConfirmationForm::class, $config_pages->id(), $imported_entity_id);
  }

  /**
   * Page title callback for config page edit forms.
   *
   * @param string|null $label
   *   Label of entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translatable page title.
   */
  public function getPageTitle($label = NULL) {
    return $this->t('<em>Edit config page</em> @label', [
      '@label' => $label,
    ]);
  }

}
