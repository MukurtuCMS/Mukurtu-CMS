<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Sketchfab embeds in Full HTML, and mukurtu_core_update_40232().
 *
 * Sketchfab's embed code puts allow and allowfullscreen on its iframe. Full
 * HTML used to strip both, so External Embed media lost fullscreen and AR
 * (#336).
 *
 * @see mukurtu_core_update_40232()
 */
#[Group('mukurtu_core')]
class SketchfabEmbedUpdateTest extends KernelTestBase {

  /**
   * The iframe entry before the update.
   */
  private const OLD = '<iframe width height frameborder scrolling marginheight marginwidth title src>';

  /**
   * The iframe entry after the update.
   */
  private const NEW = '<iframe width height frameborder scrolling marginheight marginwidth title src allow allowfullscreen>';

  /**
   * A Sketchfab embed code, trimmed to the iframe.
   */
  private const EMBED = '<iframe title="Bowl" frameborder="0" allowfullscreen allow="autoplay; fullscreen; xr-spatial-tracking" src="https://sketchfab.com/models/0123456789abcdef/embed"></iframe>';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'filter', 'editor', 'ckeditor5'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Required directly rather than via loadInclude(), for the same reason as
    // RevokeAuthenticatedFullHtmlUpdateTest: mukurtu_core is not enabled here.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';
  }

  /**
   * Reads one of the profile's shipped config files.
   */
  private function shipped(string $name): array {
    return Yaml::decode(file_get_contents(dirname(__DIR__, 5) . "/config/install/$name.yml"));
  }

  /**
   * Runs filter_html with the given allowed tags over the embed code.
   */
  private function filter(string $allowed_html): string {
    $filter = \Drupal::service('plugin.manager.filter')->createInstance('filter_html', [
      'settings' => ['allowed_html' => $allowed_html, 'filter_html_nofollow' => FALSE],
    ]);
    return $filter->process(self::EMBED, 'en')->getProcessedText();
  }

  /**
   * Writes a pre-update Full HTML filter and editor as raw config.
   */
  private function seedOldConfig(): void {
    \Drupal::configFactory()->getEditable('filter.format.full_html')->setData([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'filters' => [
        'filter_html' => [
          'id' => 'filter_html',
          'provider' => 'filter',
          'status' => TRUE,
          'weight' => -10,
          'settings' => [
            'allowed_html' => '<p> ' . self::OLD . ' <a href>',
            'filter_html_help' => TRUE,
            'filter_html_nofollow' => FALSE,
          ],
        ],
      ],
    ])->save();

    \Drupal::configFactory()->getEditable('editor.editor.full_html')->setData([
      'format' => 'full_html',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => ['items' => ['sourceEditing']],
        'plugins' => [
          'ckeditor5_sourceEditing' => [
            'allowed_tags' => ['<div>', self::OLD, '<embed type src height width>'],
          ],
        ],
      ],
    ])->save();
  }

  /**
   * The shipped Full HTML keeps the attributes, and the editor matches it.
   */
  public function testShippedConfigKeepsSketchfabAttributes(): void {
    $filter = $this->shipped('filter.format.full_html');
    $allowed = $filter['filters']['filter_html']['settings']['allowed_html'];
    $this->assertStringContainsString(self::NEW, $allowed);

    $editor = $this->shipped('editor.editor.full_html');
    $this->assertContains(self::NEW, $editor['settings']['plugins']['ckeditor5_sourceEditing']['allowed_tags']);

    $output = $this->filter($allowed);
    $this->assertStringContainsString('allow="autoplay; fullscreen; xr-spatial-tracking"', $output);
    $this->assertStringContainsString('allowfullscreen', $output);
    $this->assertStringContainsString('src="https://sketchfab.com/models/0123456789abcdef/embed"', $output);

    // Precondition: the old entry really did strip them, so the assertions
    // above are not passing for some other reason.
    $old_output = $this->filter(self::OLD);
    $this->assertStringNotContainsString('allow', $old_output);
  }

  /**
   * The update adds the attributes to both configs, and runs only once.
   */
  public function testUpdateAddsAttributes(): void {
    $this->seedOldConfig();

    $this->assertNotNull(mukurtu_core_update_40232());

    $allowed = \Drupal::config('filter.format.full_html')->get('filters.filter_html.settings.allowed_html');
    $this->assertSame('<p> ' . self::NEW . ' <a href>', $allowed);
    $tags = \Drupal::config('editor.editor.full_html')->get('settings.plugins.ckeditor5_sourceEditing.allowed_tags');
    $this->assertSame(['<div>', self::NEW, '<embed type src height width>'], $tags);

    $this->assertNull(mukurtu_core_update_40232(), 'A second run should change nothing.');
    $this->assertSame($allowed, \Drupal::config('filter.format.full_html')->get('filters.filter_html.settings.allowed_html'));
  }

  /**
   * A site that rewrote its iframe entry is left alone.
   */
  public function testCustomizedIframeLeftAlone(): void {
    $this->seedOldConfig();
    $custom = '<p> <iframe src width height> <a href>';
    \Drupal::configFactory()->getEditable('filter.format.full_html')
      ->set('filters.filter_html.settings.allowed_html', $custom)
      ->save();
    \Drupal::configFactory()->getEditable('editor.editor.full_html')
      ->set('settings.plugins.ckeditor5_sourceEditing.allowed_tags', ['<iframe src width height>'])
      ->save();

    $this->assertNull(mukurtu_core_update_40232());
    $this->assertSame($custom, \Drupal::config('filter.format.full_html')->get('filters.filter_html.settings.allowed_html'));
    $this->assertSame(['<iframe src width height>'], \Drupal::config('editor.editor.full_html')->get('settings.plugins.ckeditor5_sourceEditing.allowed_tags'));
  }

  /**
   * The Sketchfab Klaro service is added once, from the shipped config.
   */
  public function testAddsKlaroService(): void {
    \Drupal::service('module_installer')->install(['klaro']);
    $storage = \Drupal::entityTypeManager()->getStorage('klaro_app');
    $this->assertNull($storage->load('sketchfab'), 'Precondition: klaro does not ship a Sketchfab service.');

    $this->assertNotNull(mukurtu_core_update_40232());

    $app = $storage->load('sketchfab');
    $this->assertNotNull($app);
    $this->assertSame('Sketchfab', $app->label());
    $this->assertSame(['sketchfab.com'], $app->get('javascripts'));
    $this->assertSame(['external_content'], $app->get('purposes'));

    $this->assertNull(mukurtu_core_update_40232(), 'A second run should change nothing.');
  }

  /**
   * A site with nothing to change returns no message.
   */
  public function testNoOpWithoutConfigOrKlaro(): void {
    $this->assertFalse(\Drupal::entityTypeManager()->hasDefinition('klaro_app'), 'Precondition: klaro is not installed.');
    $this->assertNull(mukurtu_core_update_40232());
  }

}
