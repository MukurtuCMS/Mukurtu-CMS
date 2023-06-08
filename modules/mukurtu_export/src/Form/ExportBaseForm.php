<?php

namespace Drupal\mukurtu_export\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\mukurtu_export\FlaggedExporterSource;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\mukurtu_export\MukurtuExporterPluginManager;
use Drupal\mukurtu_export\BatchExportExecutable;

/**
 * Provides a Mukurtu Export base form.
 */
class ExportBaseForm extends FormBase
{
  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;

  protected $entityTypeManager;
  protected $entityBundleInfo;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;
  protected $exportPluginManager;
  protected $exporterId;
  protected $exporterConfig;
  protected $exporter;

  protected $source;
  protected $executable;


  /**
   * {@inheritdoc}
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_bundle_info, MukurtuExporterPluginManager $mukurtuExporterPluginManager)
  {
    $this->tempStoreFactory = $temp_store_factory;
    $this->store = $this->tempStoreFactory->get('mukurtu_import');
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityBundleInfo = $entity_bundle_info;
    $this->exportPluginManager = $mukurtuExporterPluginManager;
    $this->exporterId = $this->getExporterId();
    $this->exporterConfig = $this->getExporterConfig();
    $this->exporter = $this->exporterId ? $this->exportPluginManager->getInstance(['id' => $this->exporterId, 'configuration' => $this->exporterConfig]) : NULL;
    $this->source = new FlaggedExporterSource('node');
    $this->executable = $this->exporter ? new BatchExportExecutable($this->source, $this->exporter) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.mukurtu_exporter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'mukurtu_export_export_base';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
  }

  /**
   * Reset the export operation to a clean initial state.
   *
   * @return void
   */
  protected function reset()
  {
    $this->setExporterId(NULL);
  }


  /**
   * Get the option array for available exporter plugins.
   */
  protected function getExporterOptions()
  {
    $options = [];
    $defs = $this->exportPluginManager->getDefinitions();
    foreach ($defs as $def) {
      $options[$def['id']] = $def['label'];
    }
    return $options;
  }

  /**
   * Get the exporter plugin ID.
   */
  protected function getExporterId()
  {
    return $this->store->get('exporter_id');
  }

  /**
   * Set the exporter plugin ID.
   */
  protected function setExporterId($exporter_id)
  {
    $this->store->set('exporter_id', $exporter_id);
  }

  protected function getExporterConfig()
  {
    return $this->store->get('exporter_config') ?? [];
  }

  protected function setExporterConfig($config)
  {
    return $this->store->set('exporter_config', $config);
  }

}
