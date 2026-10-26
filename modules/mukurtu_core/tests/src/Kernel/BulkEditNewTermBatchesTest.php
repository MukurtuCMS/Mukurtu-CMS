<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\BulkEdit\MukurtuModifyEntityValues;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeAccessRebuild;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use Drupal\views_bulk_edit\Plugin\Action\ModifyEntityValues;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests adding a new term with Views Bulk Edit across more than one batch.
 *
 * VBO restores the action configuration fresh for every batch. A new term
 * typed into an autocreate widget is stored there unsaved, so each batch
 * after the first used to save it again and fail on its UUID.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2505
 */
#[Group('mukurtu_core')]
class BulkEditNewTermBatchesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'views_bulk_edit',
    'views_bulk_operations',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('og_membership');

    // mukurtu_protocol's node-grants cache context queries OG membership
    // tables on every access check once the module is enabled.
    \Drupal::service(NodeAccessRebuild::class)->rebuild();

    $admin = User::create(['name' => 'admin']);
    $admin->save();
    $this->container->get('current_user')->setAccount($admin);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_keywords',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_keywords',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Keywords',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['keywords' => 'keywords'], 'auto_create' => TRUE],
      ],
    ])->save();
  }

  /**
   * Creates and saves an article with the given keyword term IDs.
   */
  protected function article(array $tids = []): NodeInterface {
    $node = Node::create([
      'type' => 'article',
      'title' => $this->randomMachineName(),
      'field_keywords' => array_map(fn($tid) => ['target_id' => $tid], $tids),
    ]);
    $node->save();
    return $node;
  }

  /**
   * Returns the configuration VBE stores for "add a new term".
   *
   * VBE reads values from a blank entity built from the form, so a typed new
   * term arrives unsaved. The configuration is then serialized, as VBO does
   * between batches.
   */
  protected function addNewTermConfiguration(string $name): string {
    $blank = Node::create(['type' => 'article']);
    $blank->set('field_keywords', [['entity' => Term::create(['vid' => 'keywords', 'name' => $name])]]);
    return serialize([
      'node' => [
        'article' => [
          'values' => ['field_keywords' => $blank->get('field_keywords')->getValue()],
          'change_method' => ['field_keywords' => 'new'],
          'revision_information' => [],
        ],
      ],
    ]);
  }

  /**
   * Runs the action over the batches, restoring the configuration each time.
   */
  protected function runBatches(string $configuration, array $batches, ?string $class = NULL): void {
    $manager = $this->container->get('plugin.manager.views_bulk_operations_action');
    foreach ($batches as $batch) {
      if ($class) {
        $action = $class::create($this->container, [], 'views_bulk_edit', $manager->getDefinition('views_bulk_edit'));
      }
      else {
        $action = $manager->createInstance('views_bulk_edit');
      }
      // The test serialized this itself. An unsaved term object graph spans
      // too many classes to list.
      $action->setConfiguration(unserialize($configuration, ['allowed_classes' => TRUE]));
      foreach ($batch as $node) {
        $action->execute($node);
      }
    }
  }

  /**
   * Returns the IDs of keyword terms with the given name.
   */
  protected function termIds(string $name): array {
    return array_keys($this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties(['name' => $name]));
  }

  /**
   * Returns a node's keyword term IDs, reloaded from storage.
   */
  protected function keywordIds(NodeInterface $node): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    return array_map('intval', array_column($storage->load($node->id())->get('field_keywords')->getValue(), 'target_id'));
  }

  /**
   * Mukurtu's class replaces VBE's for the "Modify field values" action.
   */
  public function testActionUsesMukurtuClass(): void {
    $definition = $this->container->get('plugin.manager.views_bulk_operations_action')->getDefinition('views_bulk_edit');
    $this->assertSame(MukurtuModifyEntityValues::class, $definition['class']);
  }

  /**
   * One new term is created and attached across two batches.
   */
  public function testNewTermAcrossBatches(): void {
    $existing = Term::create(['vid' => 'keywords', 'name' => 'Existing']);
    $existing->save();
    $nodes = [
      $this->article([(int) $existing->id()]),
      $this->article(),
      $this->article(),
      $this->article(),
    ];

    $this->runBatches($this->addNewTermConfiguration('Brand new'), array_chunk($nodes, 2));

    $tids = $this->termIds('Brand new');
    $this->assertCount(1, $tids);
    $new = (int) reset($tids);
    $this->assertSame([(int) $existing->id(), $new], $this->keywordIds($nodes[0]));
    foreach (array_slice($nodes, 1) as $node) {
      $this->assertSame([$new], $this->keywordIds($node));
    }
  }

  /**
   * Without the fix, the second batch fails on the term's UUID.
   *
   * Guards the test above: if VBE ever stops storing the unsaved term, this
   * fails and the workaround can be removed.
   */
  public function testUnpatchedActionFailsOnSecondBatch(): void {
    $nodes = [$this->article(), $this->article()];

    $this->expectException(EntityStorageException::class);
    $this->runBatches($this->addNewTermConfiguration('Brand new'), array_chunk($nodes, 1), ModifyEntityValues::class);
  }

}
