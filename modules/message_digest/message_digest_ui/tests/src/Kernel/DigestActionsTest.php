<?php

namespace Drupal\Tests\message_digest_ui\Kernel;

use Drupal\flag\FlagInterface;
use Drupal\Tests\message_subscribe_email\Kernel\MessageSubscribeEmailTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Test message digest actions.
 *
 * @group message_digest
 */
class DigestActionsTest extends MessageSubscribeEmailTestBase {

  use TaxonomyTestTrait;

  /**
   * The action manager service.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $actionManager;

  /**
   * Action config storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $actionStorage;

  /**
   * Test terms.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected $terms;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'message_digest',
    'options',
    'field',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['message_digest']);

    // Clear out the cached definitions since message digest needs to provide
    // its config-based ones.
    $this->container->get('plugin.message_notify.notifier.manager')->clearCachedDefinitions();

    $this->actionManager = $this->container->get('plugin.manager.action');
    $this->actionStorage = $this->container->get('entity_type.manager')->getStorage('action');

    // Install the module here, otherwise message_digest attempts to install
    // message_digest_ui config.
    $this->container->get('module_installer')->install(['message_digest_ui']);

    $this->actionManager->clearCachedDefinitions();
    $this->installConfig(['message_digest_ui']);

    // Fake user 2 login.
    $this->container->get('account_switcher')->switchTo($this->users[2]);

    // Verify our flagging field is installed.
    assert($this->container->get('entity_type.manager')->getStorage('field_storage_config')->load('flagging.message_digest'));

    // Add some terms.
    $vocabulary = $this->createVocabulary();
    $this->terms[] = $this->createTerm($vocabulary);
    $this->terms[] = $this->createTerm($vocabulary);
  }

  /**
   * Test actions.
   *
   * @dataProvider providerTestActionsNode
   */
  public function testActionsNode($action_id, $interval_plugin, $flag_id) {
    /** @var \Drupal\system\Entity\Action $action */
    $action = $this->actionStorage->load($action_id);
    $plugin = $action->getPlugin();
    $flag = $this->flagService->getFlagById($flag_id);
    $entity = $this->getTestEntity($flag);

    // Flag the entity on behalf of user 2.
    $this->flagService->flag($flag, $entity, $this->users[2]);

    // Trigger plugin.
    $plugin->execute($entity);

    $flagging = $this->flagService->getFlagging($flag, $entity, $this->users[2]);
    $this->assertEquals($interval_plugin, $flagging->message_digest->value);
  }

  /**
   * Data provider for testActionsNode.
   */
  public static function providerTestActionsNode() {
    $cases = [];

    $flags = [
      'email_node',
      'email_user',
      'email_term',
    ];
    foreach (['immediate', 'daily', 'weekly'] as $interval) {
      foreach ($flags as $flag_id) {
        $cases[] = [
          'message_digest_interval.' . $flag_id . '.' . $interval,
          $interval === 'immediate' ? '0' : 'message_digest:' . $interval,
          $flag_id,
        ];
      }
    }

    return $cases;
  }

  /**
   * Helper method to retrieve a test entity given a flag.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The test flag.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The test entity.
   */
  protected function getTestEntity(FlagInterface $flag) {
    switch ($flag->getFlaggableEntityTypeId()) {
      case 'node':
        return $this->nodes[1];

      case 'taxonomy_term':
        return $this->terms[1];

      case 'user':
        return $this->users[1];
    }
  }

}
