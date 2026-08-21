<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_workflows\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_workflows\Form\WorkflowSettingsForm;
use Drupal\node\Entity\NodeType;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests structural vs. protocol-gated bundle assignment across workflows.
 */
#[Group('mukurtu_workflows')]
class WorkflowBundleAssignmentTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'image',
    'media',
    'mukurtu_protocol',
    'mukurtu_workflows',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installMukurtuWorkflowsConfig();

    // article/page are structural: mukurtu_protocol explicitly excludes
    // them from its catch-all protocol-controlled bundle class.
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    // landing_page must never be moderated, regardless of classification.
    NodeType::create(['type' => 'landing_page', 'name' => 'Landing Page'])->save();
    // Any other bundle picks up MukurtuNode::class via mukurtu_protocol's
    // catch-all, making it protocol-gated.
    NodeType::create(['type' => 'thing', 'name' => 'Protocol Controlled Thing'])->save();
  }

  /**
   * Installs only the workflow/settings config this test needs.
   *
   * Avoids installConfig(['mukurtu_workflows']), which would also install
   * the module's Views config (mukurtu_workflow_overview) and pull in
   * dependencies (filter, views field plugins) this test doesn't enable.
   */
  protected function installMukurtuWorkflowsConfig(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_workflows');
    $storage = new FileStorage($module_path . '/config/install');
    foreach ([
      'workflows.workflow.mukurtu_default_content_workflow',
      'workflows.workflow.mukurtu_editorial_workflow',
      'mukurtu_workflows.settings',
    ] as $name) {
      \Drupal::configFactory()->getEditable($name)->setData($storage->read($name))->save();
    }
  }

  /**
   * Submits the workflow settings form with the given active workflow.
   */
  protected function submitSettingsForm(string $active_workflow_id): void {
    $form_state = new FormState();
    $form_state->setValue('active_workflow', $active_workflow_id);
    $form = WorkflowSettingsForm::create(\Drupal::getContainer());
    $build = [];
    $form->submitForm($build, $form_state);
  }

  /**
   * Gets the node bundles currently assigned to a workflow.
   */
  protected function getBundles(string $workflow_id): array {
    $workflow = Workflow::load($workflow_id);
    $type_settings = $workflow->get('type_settings');
    return $type_settings['entity_types']['node'] ?? [];
  }

  /**
   * Default active: structural and protocol-gated bundles stay together.
   */
  public function testDefaultActiveAssignsStructuralAndProtocolGatedToDefault(): void {
    $this->submitSettingsForm('mukurtu_default_content_workflow');

    $default_bundles = $this->getBundles('mukurtu_default_content_workflow');
    $editorial_bundles = $this->getBundles('mukurtu_editorial_workflow');

    $this->assertEqualsCanonicalizing(['article', 'page', 'thing'], $default_bundles);
    $this->assertEmpty($editorial_bundles);
    $this->assertNotContains('landing_page', $default_bundles);
    $this->assertEquals('mukurtu_default_content_workflow', \Drupal::config('mukurtu_workflows.settings')->get('active_workflow'));
  }

  /**
   * Editorial active: only the protocol-gated bundle moves; structural stays.
   */
  public function testEditorialActiveMovesOnlyProtocolGatedBundles(): void {
    $this->submitSettingsForm('mukurtu_editorial_workflow');

    $default_bundles = $this->getBundles('mukurtu_default_content_workflow');
    $editorial_bundles = $this->getBundles('mukurtu_editorial_workflow');

    $this->assertEqualsCanonicalizing(['article', 'page'], $default_bundles);
    $this->assertEqualsCanonicalizing(['thing'], $editorial_bundles);
    $this->assertNotContains('landing_page', $default_bundles);
    $this->assertNotContains('landing_page', $editorial_bundles);
    $this->assertEquals('mukurtu_editorial_workflow', \Drupal::config('mukurtu_workflows.settings')->get('active_workflow'));
  }

  /**
   * Switching back to Default restores the protocol-gated bundle there.
   */
  public function testSwitchingBackToDefaultRestoresProtocolGatedBundle(): void {
    $this->submitSettingsForm('mukurtu_editorial_workflow');
    $this->submitSettingsForm('mukurtu_default_content_workflow');

    $default_bundles = $this->getBundles('mukurtu_default_content_workflow');
    $editorial_bundles = $this->getBundles('mukurtu_editorial_workflow');

    $this->assertEqualsCanonicalizing(['article', 'page', 'thing'], $default_bundles);
    $this->assertEmpty($editorial_bundles);
  }

}
