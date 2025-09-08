<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\ImportBatchExecutable;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\NodeType;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\Tests\migrate\Kernel\MigrateTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Tests Mukurtu imports.
 */
class MukurtuImportTestBase extends MigrateTestBase {
  use UserCreationTrait {
    createRole as drupalCreateRole;
    createUser as drupalCreateUser;
    setCurrentUser as drupalSetCurrentUser;
    setUpCurrentUser as drupalSetUpCurrentUser;
  }
  /**
   * The user account set as the current user in the tests.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  protected $container;

  /**
   * A community for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Community
   */
  protected $community;

  /**
   * A protocol for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Protocol
   */
  protected $protocol;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'entity_test',
    'field',
    'file',
    'filter',
    'image',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'node_access_test',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'migrate_source_csv',
    'mukurtu_import',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('node');
    $this->installEntitySchema('mukurtu_import_strategy');
    $this->installSchema('file', 'file_usage');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installConfig(['field','taxonomy', 'filter', 'og', 'system']);

    // Flag community entities as Og groups
    // so Og does its part for access control.
    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // Create a type to put in the collection.
    NodeType::create([
      'type' => 'protocol_aware_content',
      'name' => 'Protocol Aware Content',
    ])->save();

    // Create a user role for a standard authenticated user.
    $role = Role::create([
      'id' => 'authenticated',
      'label' => 'authenticated',
    ]);
    $role->grantPermission('access content');
    $role->grantPermission('create protocol_aware_content content');
    $role->save();

    // Create the protocol steward role with our
    // default Mukurtu permission set for the thing node type.
    $values = [
      'name' => 'protocol_steward',
      'label' => 'Protocol Steward',
      'permissions' => [
        'add user',
        'apply protocol',
        'administer permissions',
        'approve and deny subscription',
        'create protocol_aware_content node',
        'delete any protocol_aware_content node',
        'delete own protocol_aware_content node',
        'update any protocol_aware_content node',
        'update own protocol_aware_content node',
        'manage members',
        'update group',
      ],
    ];
    $protocolStewardRole = OgRole::create($values);
    $protocolStewardRole->setGroupType('protocol');
    $protocolStewardRole->setGroupBundle('protocol');
    $protocolStewardRole->save();

    $container = \Drupal::getContainer();
    $this->container = $container;

    // Create user and set as current user.
    $user = $this->createUser();
    $user->save();
    $this->currentUser = $user;

    $this->setCurrentUser($user);

    $community = Community::create([
      'name' => 'Community',
    ]);
    $community->save();
    $this->community = $community;
    $this->community->addMember($user);

    $protocol = Protocol::create([
      'name' => "Protocol",
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($user, ['protocol_steward']);
    $this->protocol = $protocol;
  }

  /**
   * Creates a user.
   *
   * @param array $values
   *   (optional) The values used to create the entity.
   * @param array $permissions
   *   (optional) Array of permission names to assign to user.
   *
   * @return \Drupal\user\Entity\User
   *   The created user entity.
   */
  protected function createUser($values = [], $permissions = []) {
    return $this->drupalCreateUser($permissions ?: [], NULL, FALSE, $values ?: []);
  }

  /**
   * Set the current user.
   */
  protected function setCurrentUser(AccountInterface $account) {
    $this->container
      ->get('current_user')
      ->setAccount($account);
  }

  /**
   * Create a CSV file from provided data and return its FID.
   *
   * @param array $data
   *   The data to be written to the CSV.
   *
   * @return \Drupal\file\FileInterface|null
   *   The created file, or NULL on failure.
   */
  protected function createCsvFile(array $data): ?FileInterface {
    $temp_file_path = tempnam(sys_get_temp_dir(), 'test_') . '.csv';
    $handle = fopen($temp_file_path, 'w');
    foreach ($data as $row) {
      fputcsv($handle, $row);
    }
    fclose($handle);

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_uri = $file_system->copy($temp_file_path, 'public://', FileSystemInterface::EXISTS_REPLACE);

    if ($file_uri) {
      $file = File::create([
        'uri' => $file_uri,
        'status' => 1,
      ]);
      $file->save();
      return $file;
    }

    return NULL;
  }

  /**
   * Run a Mukurtu import.
   *
   * @param FileInterface $file
   *  The CSV file to import.
   * @param array $mapping
   *  The source/target mapping array. [['target' => Target, 'source' => Source]].
   * @param string $entity_type_id
   *  The destination entity type id.
   * @param string $bundle
   *  The destination bundle.
   * @return int
   *  The MigrateExecutable result.
   */
  protected function importCsvFile(FileInterface $file, array $mapping, $entity_type_id = 'node', $bundle = 'protocol_aware_content'): int {
    $import_config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $import_config->setTargetEntityTypeId($entity_type_id);
    $import_config->setTargetBundle($bundle);
    $import_config->setMapping($mapping);
    $definition = $import_config->toDefinition($file);

    // Run the import.
    $message = new MigrateMessage();
    $migration = \Drupal::getContainer()->get('plugin.manager.migration')->createStubMigration($definition);
    $time = \Drupal::getContainer()->get('datetime.time');
    $translation = \Drupal::getContainer()->get('string_translation');
    $migrationPluginManager = \Drupal::service('plugin.manager.migration');
    $executable = new ImportBatchExecutable($migration, $message, $this->keyValue, $time, $translation, $migrationPluginManager, []);
    return $executable->import();
  }

}
