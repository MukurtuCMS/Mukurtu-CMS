<?php

/**
 * @file
 * Contains \Drupal\mukurtu_migrate\Form\ImportFromRemoteSite.
 */

namespace Drupal\mukurtu_migrate\Form;

use Drupal\file\Entity\File;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ImportFromRemoteSite extends FormBase {
  const STATE_START = 'migrateStart';
  const STATE_CONNECTION_SUCCESSFUL = 'migrateConnectionSuccessful';
  const STATE_MIGRATION_SUMMARY = 'migratePostMigrationScreen';

  protected $migrationManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_migrate_from_remote_site';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $state = $this->getMigrationState($form_state);

    // Build form for the given state.
    $form = $this->$state($form, $form_state);

    return $form;
  }

  protected function migrateStart(array $form, FormStateInterface $form_state) {
    $url = $form_state->getValue('remote_url');
    $username = $form_state->getValue('remote_username');
    $password = $form_state->getValue('remote_password');

    $form['remote_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remote Site URL'),
      '#default_value' => $url,
      '#description' => $this->t('Enter the URL for the remote site (e.g., https://www.mymukurtusite.com).'),
    ];

    $form['remote_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#default_value' => $username,
      '#description' => $this->t('It is recommended to use the site admin account (UID 1).'),
    ];

    $form['remote_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#default_value' => $password,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submitForConnect'] = [
      '#type' => 'submit',
      '#value' => $this->t('Inventory Site'),
      '#button_type' => 'primary',
      '#submit' => ['::submitFormConnect'],
    ];

    return $form;
  }

  protected function migrateConnectionSuccessful(array $form, FormStateInterface $form_state) {
    $manifest = $this->loadManifest($form_state);
    if ($manifest) {
      $summary = "";
      foreach ($manifest as $file) {
        $summary .= "<li>$file</li>";
      }
      $form['summary'] = [
        '#markup' => "<h2>Content to be Migrated</h2><ul>$summary</ul>",
      ];
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submitForMigrate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Migration'),
      '#button_type' => 'primary',
      '#submit' => ['::submitFormMigrate'],
    ];

    $form['actions']['submitStartOVer'] = [
      '#type' => 'submit',
      '#value' => $this->t('Abandon this Migration'),
      '#button_type' => 'primary',
      '#submit' => ['::submitStartOver'],
    ];

    return $form;
  }

  protected function migratePostMigrationScreen(array $form, FormStateInterface $form_state) {
    $form['summary'] = [
      '#markup' => "Display the summary of the migration here.",
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Custom submission handler for site migration connection.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitFormConnect(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('remote_url');
    $username = $form_state->getValue('remote_username');
    $password = $form_state->getValue('remote_password');

    $this->migrationManager = \Drupal::service('mukurtu_migrate.migrate_rest_manager');
    $logged_in = $this->migrationManager->login($url, $username, $password);
    if ($logged_in) {
      $batch = [
        'title' => t('Gathering Information from Remote Site'),
        'operations' => [
          [
            'mukurtu_migrate_import_summary',
            [
              [
                'migration_manager' => $this->migrationManager,
                'form_id' => $this->getFormId(),
              ],
            ],
          ],
        ],
        'finished' => 'mukurtu_migrate_summary_complete_callback',
        'file' => drupal_get_path('module', 'mukurtu_migrate') . '/mukurtu_migrate.importremote.inc',
      ];
      batch_set($batch);
      $form_state->setValue('migrate_state', ImportFromRemoteSite::STATE_CONNECTION_SUCCESSFUL);
    } else {
      $messenger = \Drupal::messenger();
      $messenger->addMessage($this->t('Could not connect to the remote site. Please double check the connection fields.'), $messenger::TYPE_WARNING);
    }

    $form_state->setRebuild();
  }

  /**
   * Custom submission handler for the start of site migration.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitFormMigrate(array &$form, FormStateInterface $form_state) {
    // Refresh auth.
    $this->migrationManager->authenticate();

    $batch = [
      'title' => t('Migrating from Remote Site'),
      'operations' => [
        [
          'mukurtu_migrate_import_from_remote',
          [
            [
              'manifest' => $this->loadManifest($form_state),
              'migration_manager' => $this->migrationManager,
              'form_id' => $this->getFormId(),
            ],
          ],
        ],
      ],
      'finished' => 'mukurtu_migrate_migration_complete_callback',
      'file' => drupal_get_path('module', 'mukurtu_migrate') . '/mukurtu_migrate.importremote.inc',
    ];
    batch_set($batch);
    //$form_state->setValue('migrate_state', ImportFromRemoteSite::STATE_MIGRATION_SUMMARY);
    $form_state->setRebuild();
  }

  /**
   * Custom submission handler for restarting migration.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitStartOver(array &$form, FormStateInterface $form_state) {
    // Delete manifest file.
    $files = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->loadByProperties(['uri' => 'private://mukurtu_migrate/manifest.json']);
    foreach ($files as $file) {
      $file->delete();
    }

    // Set state to the start.
    $form_state->setValue('migrate_state', ImportFromRemoteSite::STATE_START);

    // Rebuild the form.
    $form_state->setRebuild();
  }

  /**
   * Return the current state of the multi-step migration.
   */
  protected function getMigrationState(FormStateInterface $form_state) {
    $state = $form_state->getValue('migrate_state') ?? ImportFromRemoteSite::STATE_START;

    // If we are at the start but still have files from a previous run, give the
    // user the option to resume.
    if ($state == ImportFromRemoteSite::STATE_START) {
      $manifest = $this->loadManifest($form_state);
      if ($manifest) {
        $state = ImportFromRemoteSite::STATE_CONNECTION_SUCCESSFUL;
      }
    }
    return $state;
  }

  /**
   * Return the mainfest file contents. Return FALSE if it does not exist.
   */
  protected function loadManifest(FormStateInterface $form_state) {
    $manifest = file_get_contents(drupal_realpath('private://mukurtu_migrate/manifest.json'));
    if ($manifest) {
      $manifest_array = json_decode($manifest);
      $form_state->setValue('migrate_manifest', $manifest_array);
      return $manifest_array;
    }

    return FALSE;
  }

  protected function getImportFileContents(FormStateInterface $form_state) {
    $form_file = $form_state->getValue('import_file', 0);
    if (isset($form_file[0]) && !empty($form_file[0])) {
      $file = File::load($form_file[0]);
      if ($file) {
        $data = file_get_contents($file->getFileUri());
        $csv_array = array_map("str_getcsv", explode("\n", $data));
        //$csv_array = array_map("str_getcsv", file($file->getFileUri()));
        return $csv_array;
      }
    }

    return [];
  }

}
