<?php

namespace Drupal\mukurtu_migrate\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_drupal\MigrationState;
use Drupal\migrate_drupal_ui\Batch\MigrateUpgradeImportBatch;
use Drupal\mukurtu_migrate\Batch\MukurtuMigrateImportBatch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

/**
 * Mukurtu migrate review form.
 *
 * Heavily borrows from migrate_drupal_ui's ReviewForm.
 *
 * @internal
 */
class ReviewForm extends MukurtuMigrateFormBase {

  /**
   * The migrations.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface[]
   */
  protected $migrations;

  /**
   * Migration state service.
   *
   * @var \Drupal\migrate_drupal\MigrationState
   */
  protected $migrationState;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Source system data set in buildForm().
   *
   * @var array
   */
  protected $systemData;

  /**
   * ReviewForm constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   The migration plugin manager service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstore_private
   *   The private tempstore factory service.
   * @param \Drupal\migrate_drupal\MigrationState $migrationState
   *   Migration state service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(StateInterface $state, MigrationPluginManagerInterface $migration_plugin_manager, PrivateTempStoreFactory $tempstore_private, MigrationState $migrationState, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler = NULL) {
    parent::__construct($config_factory, $migration_plugin_manager, $state, $tempstore_private);
    $this->migrationState = $migrationState;
    if (!$module_handler) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $module_handler argument is deprecated in drupal:9.1.0 and will be required in drupal:10.0.0. See https://www.drupal.org/node/3136769', E_USER_DEPRECATED);
      $module_handler = \Drupal::service('module_handler');
    }
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('plugin.manager.migration'),
      $container->get('tempstore.private'),
      $container->get('migrate_drupal.migration_state'),
      $container->get('config.factory'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_migrate_review_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get all the data needed for this form.
    $version = $this->store->get('version');
    $this->migrations = $this->store->get('migrations');
    // Fetch the source system data at the first opportunity.
    $this->systemData = $this->store->get('system_data');

    // If data is missing or this is the wrong step, start over.
    if (!$version || !$this->migrations || !$this->systemData ||
      ($this->store->get('step') != 'review')) {
      return $this->restartUpgradeForm();
    }

    $form = parent::buildForm($form, $form_state);

    $form['#title'] = $this->t('Migration Steps');

    $migrations = $this->migrationPluginManager->createInstances(array_keys($this->store->get('migrations')));

    $form['migrations'] = [
      '#type' => 'table',
      '#header' => array(
        $this->t('Step'),
        $this->t('Items to migrate'),
      ),
    ];

    foreach ($migrations as $migration) {
      try {
        $count = count($migration->getSourcePlugin());
      } catch(Exception $e) {
        $count = "";
      }

      $form['migrations'][$migration->id()]['step'] = [
        '#type' => 'processed_text',
        '#text' => $migration->label(),
      ];
      $form['migrations'][$migration->id()]['count'] = [
        '#type' => 'processed_text',
        '#text' =>$count,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config['source_base_path'] = $this->store->get('source_base_path');
    $config['source_private_file_path'] = $this->store->get('source_private_file_path');
    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Running migration'))
      ->setProgressMessage('')
      ->addOperation([
        MukurtuMigrateImportBatch::class, 'run',
      ], [array_keys($this->migrations), $config])
      ->setFinishCallback([MukurtuMigrateImportBatch::class, 'finished']);
    batch_set($batch_builder->toArray());
    $this->store->set('step', 'results');
    $this->store->set('mukurtu_migrate.performed', REQUEST_TIME);
    $form_state->setRedirect('mukurtu_migrate.results');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Begin migration');
  }

  /**
   * Prepare the migration state data for output.
   *
   * Each source and destination module_name is changed to the human-readable
   * name, the destination modules are put into a CSV format, and everything is
   * sorted.
   *
   * @param string[] $migration_state
   *   An array where the keys are machine names of modules on
   *   the source site. Values are lists of machine names of modules on the
   *   destination site, in CSV format.
   *
   * @return string[][]
   *   An indexed array of arrays that contain module data, sorted by the source
   *   module name. Each sub-array contains the source module name, the source
   *   module machine name, and the destination module names in a sorted CSV
   *   format.
   */
  protected function prepareOutput(array $migration_state) {
    $output = [];
    foreach ($migration_state as $source_machine_name => $destination_modules) {
      $data = NULL;
      if (isset($this->systemData['module'][$source_machine_name]['info'])) {
        $data = unserialize($this->systemData['module'][$source_machine_name]['info']);
      }
      $source_module_name = $data['name'] ?? $source_machine_name;
      // Get the names of all the destination modules.
      $destination_module_names = [];
      if (!empty($destination_modules)) {
        $destination_modules = explode(', ', $destination_modules);
        foreach ($destination_modules as $destination_module) {
          if ($destination_module === 'core') {
            $destination_module_names[] = 'Core';
          }
          else {
            try {
              $destination_module_names[] = $this->moduleHandler->getName($destination_module);
            }
            catch (UnknownExtensionException $e) {
              $destination_module_names[] = $destination_module;
            }
          }
        }
      }
      sort($destination_module_names);
      $output[$source_machine_name] = [
        'source_module_name' => $source_module_name,
        'source_machine_name' => $source_machine_name,
        'destination' => implode(', ', $destination_module_names),
      ];
    }
    usort($output, function ($a, $b) {
      return strcmp($a['source_module_name'], $b['source_module_name']);
    });
    return $output;
  }

}
