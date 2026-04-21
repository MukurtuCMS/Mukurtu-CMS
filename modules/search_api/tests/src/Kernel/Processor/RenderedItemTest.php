<?php

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Entity\CommentType;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

// cspell:ignore knoten körper titel zusammenfassung
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Rendered item" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\RenderedItem
 */
#[RunTestsInSeparateProcesses]
class RenderedItemTest extends ProcessorTestBase {

  use ContentTypeCreationTrait;
  use UserCreationTrait;

  /**
   * List of nodes which are published.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'search_api',
    'search_api_db',
    'search_api_test',
    'language',
    'comment',
    'system',
    'filter',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('rendered_item');

    // Enable the optional "path_alias" entity type as well to make sure the
    // processor doesn't break for any of the default types.
    $this->installEntitySchema('path_alias');

    // Load additional configuration and needed schemas. (The necessary schemas
    // for using nodes are already installed by the parent method.)
    $this->installConfig(['system', 'filter', 'node', 'comment', 'user']);
    \Drupal::service('router.builder')->rebuild();

    // Create the default languages and a new one for translations.
    $this->installConfig(['language']);
    /** @var \Drupal\language\Entity\ConfigurableLanguage $language */
    $language = ConfigurableLanguage::create([
      'id' => 'de',
      'label' => 'German',
      'weight' => 0,
    ]);
    $language->save();

    // Creates node types for testing.
    foreach (['article', 'page'] as $type_id) {
      $this->createContentType(['type' => $type_id]);
    }
    CommentType::create([
      'id' => 'comment',
      'label' => 'comment',
      'target_entity_type_id' => 'node',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'comment',
      'type' => 'comment',
      'entity_type' => 'node',
    ])->save();
    FieldConfig::create([
      'field_name' => 'comment',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Comments',
    ])->save();

    // Insert the anonymous user into the database.
    $anonymous_user = User::create([
      'uid' => 0,
      'name' => '',
    ]);
    $anonymous_user->save();

    // Default node values for all nodes we create below.
    $node_data = [
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => '',
      'body' => ['value' => '', 'summary' => '', 'format' => 'plain_text'],
      'uid' => $anonymous_user->id(),
    ];

    // Create some test nodes with valid user on it for rendering a picture.
    $node_data['title'] = 'Title for node 1';
    $node_data['body']['value'] = 'value for node 1';
    $node_data['body']['summary'] = 'summary for node 1';
    $this->nodes[1] = Node::create($node_data);
    $this->nodes[1]->save();
    $node_data['title'] = 'Title for node 2';
    $node_data['body']['value'] = 'value for node 2';
    $node_data['body']['summary'] = 'summary for node 2';
    $this->nodes[2] = Node::create($node_data);
    $this->nodes[2]->save();
    $node_data['type'] = 'article';
    $node_data['title'] = 'Title for node 3';
    $node_data['body']['value'] = 'value for node 3';
    $node_data['body']['summary'] = 'summary for node 3';
    $this->nodes[3] = Node::create($node_data);
    $this->nodes[3]->save();

    // Add a field based on the "rendered_item" property.
    $field_info = [
      'type' => 'text',
      'property_path' => 'rendered_item',
      'configuration' => [
        'roles' => ['anonymous'],
        'view_mode' => [
          'entity:node' => [
            'page' => 'search_index',
            'article' => 'teaser',
          ],
          'entity:user' => [
            'user' => 'compact',
          ],
          'entity:comment' => [
            'comment' => 'full',
          ],
        ],
      ],
    ];
    $field = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createField($this->index, 'rendered_item', $field_info);
    $this->index->addField($field);
    $field_1 = \Drupal::getContainer()
      ->get('search_api.fields_helper')
      ->createField($this->index, 'rendered_item_1', $field_info);
    $this->index->addField($field_1);
    $datasources = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createDatasourcePlugins($this->index);
    $this->index->setDatasources($datasources);
    $this->index->save();

    // Enable the Claro and Stable 9 themes as the tests rely on markup from
    // that. Set Claro as the active theme, but make Stable 9 the default. The
    // processor should switch to Stable 9 to perform the rendering.
    \Drupal::service('theme_installer')->install(['stable9']);
    \Drupal::service('theme_installer')->install(['claro']);
    \Drupal::configFactory()->getEditable('system.theme')->set('default', 'stable9')->save();
    \Drupal::theme()->setActiveTheme(\Drupal::service('theme.initialization')->initTheme('claro'));
  }

  /**
   * Tests whether the rendered_item field is correctly filled by the processor.
   */
  public function testAddFieldValues() {
    $this->nodes[4] = $this->nodes[3]->addTranslation('de');
    $this->nodes[4]->set('title', 'Titel für Knoten 4');
    $this->nodes[4]->set('body', [
      'value' => 'Körper für Knoten 4',
      'summary' => 'Zusammenfassung für Knoten 4',
    ]);
    $this->nodes[4]->save();

    $this->assertEquals('en', $this->nodes[1]->language()->getId());
    $this->assertEquals('en', $this->nodes[2]->language()->getId());
    $this->assertEquals('en', $this->nodes[3]->language()->getId());
    $this->assertEquals('de', $this->nodes[4]->language()->getId());

    $items = [];
    foreach ($this->nodes as $i => $node) {
      $items[] = [
        'datasource' => 'entity:node',
        'item' => $node->getTypedData(),
        'item_id' => $i,
      ];
    }
    $user = User::create([
      'uid' => 2,
      'name' => 'User #2',
    ]);
    $items[] = [
      'datasource' => 'entity:user',
      'item' => $user->getTypedData(),
      'item_id' => $user->id(),
    ];
    $comment = Comment::create([
      'entity_type' => 'node',
      'entity_id' => 1,
      'field_name' => 'comment',
      'cid' => 1,
      'comment_type' => 'comment',
      'subject' => 'Subject of comment 1',
    ]);
    $comment->save();
    $items[] = [
      'datasource' => 'entity:comment',
      'item' => $comment->getTypedData(),
      'item_id' => $comment->id(),
    ];
    $items = $this->generateItems($items);

    // Add the processor's field values to the items.
    $admin_account = $this->setUpCurrentUser([], [], TRUE);
    foreach ($items as $item) {
      $this->assertEquals($admin_account->id(), \Drupal::currentUser()->id());
      $this->processor->addFieldValues($item);
      $this->assertEquals($admin_account->id(), \Drupal::currentUser()->id());
    }
    \Drupal::currentUser()->setAccount(User::load(0));
    $this->assertEquals(0, \Drupal::currentUser()->id());

    foreach ($items as $key => $item) {
      [$datasource_id, $entity_id] = Utility::splitCombinedId($key);
      $type = $this->index->getDatasource($datasource_id)->label();

      $field = $item->getField('rendered_item');
      $this->assertEquals('text', $field->getType(), "$type item $entity_id rendered value is identified as text.");
      /** @var \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface[] $values */
      $values = $field->getValues();
      // Test that the value is properly wrapped in a
      // \Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface
      // object, which contains a string (not, for example, some markup object).
      $this->assertInstanceOf(TextValueInterface::class, $values[0], "$type item $entity_id rendered value is properly wrapped in a text value object.");
      $field_value = $values[0]->getText();
      $this->assertIsString($field_value, "$type item $entity_id rendered value is a string.");
      $this->assertCount(1, $values, "$type item $entity_id rendered value is a single value.");

      switch ($datasource_id) {
        case 'entity:node':
          $this->checkRenderedNode($this->nodes[$entity_id], $field_value);
          break;

        case 'entity:user':
          $this->checkRenderedUser($user, $field_value);
          break;

        case 'entity:comment':
          $this->checkRenderedComment($comment, $field_value);
          break;

        default:
          $this->fail();
      }
    }
  }

  /**
   * Verifies that a certain node has been rendered correctly.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $field_value
   *   The rendered field value.
   */
  protected function checkRenderedNode(NodeInterface $node, $field_value) {
    // These tests rely on the template not changing. However, if we'd only
    // check whether the field values themselves are included, there could more
    // easily be false positives. For example, the title text was present even
    // when the processor was broken, because the schema metadata was also
    // adding it to the output.
    $nid = $node->id();
    $this->assertStringContainsString('<article>', $field_value, 'Node item ' . $nid . ' not rendered in theme Stable.');
    if ($node->bundle() === 'page') {
      $this->assertStringNotContainsString('>Read more<', $field_value, 'Node item ' . $nid . " rendered in view-mode \"search_index\".");
      $this->assertStringContainsString('>' . $node->get('body')->getValue()[0]['value'] . '<', $field_value, 'Node item ' . $nid . ' does not have rendered body inside HTML-Tags.');
    }
    else {
      $this->assertStringContainsString('>Read more<', $field_value, 'Node item ' . $nid . " rendered in view-mode \"teaser\".");
      $this->assertStringContainsString('>' . $node->get('body')->getValue()[0]['summary'] . '<', $field_value, 'Node item ' . $nid . ' does not have rendered summary inside HTML-Tags.');
    }
    $this->assertStringContainsString('<h2>', $field_value, 'Node item ' . $nid . ' does not have a rendered title field.');
    $this->assertStringContainsString('>' . $node->label() . '<', $field_value, 'Node item ' . $nid . ' does not have a rendered title inside HTML-Tags.');
  }

  /**
   * Verifies that a certain user has been rendered correctly.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   * @param string $field_value
   *   The rendered field value.
   */
  protected function checkRenderedUser(UserInterface $user, $field_value) {
    $this->assertStringContainsString('>Member for<', $field_value);
  }

  /**
   * Verifies that a certain comment has been rendered correctly.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *   The comment.
   * @param string $field_value
   *   The rendered field value.
   */
  protected function checkRenderedComment(CommentInterface $comment, $field_value) {
    $this->assertStringContainsString('>' . $comment->label() . '<', $field_value);
  }

  /**
   * Tests that hiding a rendered item works.
   */
  public function testHideRenderedItem() {
    // Change the processor configuration to make sure that that the rendered
    // item content will be empty.
    $field = $this->index->getField('rendered_item');
    $config = $field->getConfiguration();
    $config['view_mode'] = [
      'entity:node' => [
        'page' => '',
        'article' => '',
      ],
    ];
    $field->setConfiguration($config);

    // Create items that we can index.
    $items = [];
    foreach ($this->nodes as $node) {
      $items[] = [
        'datasource' => 'entity:node',
        'item' => $node->getTypedData(),
        'item_id' => $node->id(),
        'text' => 'text for ' . $node->id(),
      ];
    }
    $items = $this->generateItems($items);

    // Add the processor's field values to the items.
    foreach ($items as $item) {
      $this->processor->addFieldValues($item);
    }

    // Verify that no field values were added.
    foreach ($items as $item) {
      $rendered_item = $item->getField('rendered_item');
      $this->assertEmpty($rendered_item->getValues(), 'No rendered_item field value added when disabled for content type.');
    }
  }

  /**
   * Tests that the "Search excerpt" field in entity displays works correctly.
   */
  public function testSearchExcerptField() {
    \Drupal::getContainer()->get('module_installer')
      ->install(['search_api_test_excerpt_field']);
    $this->installEntitySchema('entity_view_mode');

    $view_mode = EntityViewDisplay::load('node.article.teaser');
    $view_mode->set('content', [
      'search_api_excerpt' => [
        'weight' => 0,
        'region' => 'content',
      ],
    ]);
    $view_mode->save();

    $item = $this->generateItem([
      'datasource' => 'entity:node',
      'item' => $this->nodes[3]->getTypedData(),
      'item_id' => 3,
    ]);
    $test_value = 'This is the test excerpt value';
    $item->setExcerpt($test_value);

    $this->processor->addFieldValues($item);
    $rendered_item = $item->getField('rendered_item');

    $values = $rendered_item->getValues();
    $this->assertCount(1, $values);
    $this->assertInstanceOf(TextValueInterface::class, $values[0]);
    $this->assertStringContainsString($test_value, (string) $values[0]);
  }

  /**
   * Tests whether the property is correctly added by the processor.
   */
  public function testAlterPropertyDefinitions() {
    // Check for added properties when no datasource is given.
    $properties = $this->processor->getPropertyDefinitions(NULL);
    $this->assertArrayHasKey('rendered_item', $properties, 'The Properties where modified with the "rendered_item".');
    $this->assertInstanceOf('Drupal\search_api\Plugin\search_api\processor\Property\RenderedItemProperty', $properties['rendered_item'], 'Added property has the correct class.');
    $this->assertInstanceOf(DataDefinitionInterface::class, $properties['rendered_item'], 'The "rendered_item" contains a valid DataDefinition instance.');
    $this->assertEquals('search_api_html', $properties['rendered_item']->getDataType(), 'Correct DataType set in the DataDefinition.');

    // Verify that there are no properties if a datasource is given.
    $properties = $this->processor->getPropertyDefinitions($this->index->getDatasource('entity:node'));
    $this->assertEquals([], $properties, '"render_item" property not added when datasource is given.');
  }

  /**
   * Tests whether the processor reacts correctly to removed dependencies.
   */
  public function testDependencyRemoval() {
    $expected = [
      'config' => [
        'core.entity_view_mode.comment.full',
        'core.entity_view_mode.node.search_index',
        'core.entity_view_mode.node.teaser',
        'core.entity_view_mode.user.compact',
      ],
    ];
    $this->assertEquals($expected, $this->processor->calculateDependencies());

    EntityViewMode::load('node.teaser')->delete();
    $expected = [
      'entity:node' => [
        'page' => 'search_index',
      ],
      'entity:user' => [
        'user' => 'compact',
      ],
      'entity:comment' => [
        'comment' => 'full',
      ],
    ];
    // We need to reload the index.
    $index = Index::load($this->index->id());
    $field_config = $index->getField('rendered_item')->getConfiguration();
    $this->assertEquals($expected, $field_config['view_mode']);
  }

  /**
   * Tests default (global) view mode set for all bundles in datasource.
   */
  public function testDefaultViewModeSet() {
    foreach (['rendered_item', 'rendered_item_1'] as $field_name) {
      // Change the field configuration to make sure that all bundles except for
      // "page" have rendered item content in view mode teaser.
      $field = $this->index->getField($field_name);
      $config = $field->getConfiguration();
      $config['view_mode'] = [
        'entity:node' => [
          ':default' => 'teaser',
          'page' => 'search_index',
        ],
      ];
      $field->setConfiguration($config);
    }

    // Create new content type, to make sure that default view mode works also
    // in case of new bundles that didn't exist before index update.
    $this->createContentType(['type' => 'event']);
    $node_data['type'] = 'event';
    $node_data['title'] = 'Title for node 5';
    $node_data['body']['value'] = 'value for node 5';
    $node_data['body']['summary'] = 'summary for node 5';
    $this->nodes[5] = Node::create($node_data);
    $this->nodes[5]->save();

    // Create items that we can index.
    $items = [];
    foreach ($this->nodes as $node) {
      $items[] = [
        'datasource' => 'entity:node',
        'item' => $node->getTypedData(),
        'item_id' => $node->id(),
        'text' => 'text for ' . $node->id(),
      ];
    }
    $items = $this->generateItems($items);

    // Add the processor's field values to the items.
    foreach ($items as $item) {
      $this->processor->addFieldValues($item);
    }

    // Verify that all rendered items use the correct view mode.
    foreach ($items as $item) {
      $field = $item->getField('rendered_item');
      $values = $field->getValues();
      $field_value = $values[0]->getText();
      // Nodes of type "page" should use the "search_index" view mode while all
      // others should use the "teaser" view mode.
      if ($item->getOriginalObject()->getEntity()->bundle() === 'page') {
        $this->assertStringNotContainsString('>Read more<', $field_value, "Node item {$item->getId()} rendered in view-mode \"teaser\".");
      }
      else {
        $this->assertStringContainsString('>Read more<', $field_value, "Node item {$item->getId()} rendered in view-mode \"search_index\".");
      }
    }
  }

  /**
   * Tests that exceptions during rendering are handled correctly.
   *
   * @see search_api_test_preprocess_node()
   */
  public function testExceptionDuringRendering(): void {
    // As the nodes were just saved, they are all already queued for
    // post-request indexing. Since we want to test with just a single one,
    // remove all others and then re-save our test node to make extra-sure that
    // it's queued.
    $post_request_indexing = \Drupal::getContainer()->get('search_api.post_request_indexing');
    $other_item_ids = [
      'entity:node/2:en',
      'entity:node/3:en',
      'entity:user/0:en',
    ];
    $post_request_indexing->removeFromIndexing($this->index->id(), $other_item_ids);
    $this->index->getTrackerInstance()->trackItemsIndexed($other_item_ids);
    $node = $this->nodes[1];
    $node->save();

    // First index and make sure there is no error and the "rendered_item" field
    // values gets indexed correctly.
    $post_request_indexing->destruct();
    $remaining = $this->index->getTrackerInstance()->getRemainingItems();
    $this->assertEquals([], $remaining);
    $get_indexed_values = function (): array {
      return \Drupal::database()->select("search_api_db_{$this->index->id()}", 't')
        ->fields('t', ['rendered_item', 'rendered_item_1'])
        ->condition('item_id', 'entity:node/1:en')
        ->execute()
        ->fetchAssoc();
    };
    $values = $get_indexed_values();
    $this->assertStringContainsString('node 1', $values['rendered_item']);
    $this->assertStringContainsString('node 1', $values['rendered_item_1']);

    // Now make search_api_test_preprocess_node() throw an exception.
    \Drupal::keyValue('search_api_test')->set('preprocess_node_error', TRUE);

    // Re-save the node again which should queue it for post-request indexing.
    $node->save();
    $remaining = $this->index->getTrackerInstance()->getRemainingItems();
    $this->assertEquals(['entity:node/1:en'], $remaining);

    // Trigger post-request indexing: Two errors should be logged (one for each
    // field) and the two "rendered_item" fields should now be empty.
    $this->logger->setExpectedErrors(2);
    $post_request_indexing->destruct();
    $this->logger->assertAllExpectedErrorsEncountered();
    $this->assertFalse($post_request_indexing->isIndexingActive());
    $values = $get_indexed_values();
    $this->assertEquals(['rendered_item' => NULL, 'rendered_item_1' => NULL], $values);

    // Even though the field values were sent to the server, due to the warnings
    // set on the item it should not have been marked as successfully indexed.
    $remaining = $this->index->getTrackerInstance()->getRemainingItems();
    $this->assertEquals(['entity:node/1:en'], $remaining);

    // Running cron should still produce the errors but mark the item as
    // indexed.
    $this->logger->setExpectedErrors(2);
    search_api_cron();
    $this->logger->assertAllExpectedErrorsEncountered();
    $remaining = $this->index->getTrackerInstance()->getRemainingItems();
    $this->assertEquals([], $remaining);
  }

}
