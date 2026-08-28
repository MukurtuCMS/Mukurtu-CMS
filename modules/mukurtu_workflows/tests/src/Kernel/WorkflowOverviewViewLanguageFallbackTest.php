<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_workflows\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_workflows_update_40007().
 *
 * Loads each view's real shipped config, strips the fields the hook adds
 * to simulate a site that installed before this fix, runs the hook, and
 * confirms it fills them back in - and that running it twice is a no-op.
 *
 * @see \mukurtu_workflows_update_40007()
 * @group mukurtu_workflows
 */
class WorkflowOverviewViewLanguageFallbackTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views', 'mukurtu_workflows', 'content_moderation', 'node', 'user', 'og', 'options', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_workflows') . '/config/install');

    $data_mukurtu_workflow_overview = $source->read('views.view.mukurtu_workflow_overview');
    unset($data_mukurtu_workflow_overview['display']['my_content_in_progress']['display_options']['filters']['default_langcode']);
    unset($data_mukurtu_workflow_overview['display']['my_content_in_progress']['display_options']['rendering_language']);
    unset($data_mukurtu_workflow_overview['display']['my_content_published']['display_options']['rendering_language']);
    unset($data_mukurtu_workflow_overview['display']['review_queue']['display_options']['rendering_language']);
    unset($data_mukurtu_workflow_overview['display']['my_content_published']['display_options']['filters']['default_langcode']);
    unset($data_mukurtu_workflow_overview['display']['review_queue']['display_options']['filters']['default_langcode']);
    \Drupal::configFactory()->getEditable('views.view.mukurtu_workflow_overview')->setData($data_mukurtu_workflow_overview)->save();

    require_once \Drupal::service('extension.list.module')->getPath('mukurtu_workflows') . '/mukurtu_workflows.install';
  }

  /**
   * The hook adds the filter/rendering_language when missing.
   */
  public function testAddsFallbackWhenMissing(): void {
    $this->assertArrayNotHasKey('default_langcode', $this->config('views.view.mukurtu_workflow_overview')->get('display.my_content_in_progress.display_options.filters') ?? []);

    mukurtu_workflows_update_40007();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_workflow_overview')->get('display.my_content_in_progress.display_options.filters'));
    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_workflow_overview')->get('display.my_content_published.display_options.filters'));
    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_workflow_overview')->get('display.review_queue.display_options.filters'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.mukurtu_workflow_overview')->get('display.my_content_in_progress.display_options.rendering_language'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.mukurtu_workflow_overview')->get('display.my_content_published.display_options.rendering_language'));
    $this->assertSame('***LANGUAGE_language_content***', $this->config('views.view.mukurtu_workflow_overview')->get('display.review_queue.display_options.rendering_language'));
  }

  /**
   * Running the hook twice does not error.
   */
  public function testIsIdempotent(): void {
    mukurtu_workflows_update_40007();
    mukurtu_workflows_update_40007();

    $this->assertArrayHasKey('default_langcode', $this->config('views.view.mukurtu_workflow_overview')->get('display.my_content_in_progress.display_options.filters'));
  }

}
