<?php

namespace Drupal\Tests\message\Kernel;

use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\MessageTemplate;

/**
 * Tests message templates dependencies.
 *
 * @coversDefaultClass \Drupal\message\Entity\MessageTemplate
 * @group message
 */
class MessageTemplateDependenciesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'message',
    'user',
  ];

  /**
   * Tests filter format dependencies.
   *
   * @covers ::calculateDependencies
   * @covers ::onDependencyRemoval
   */
  public function testFilterFormatDependency() {
    $config_factory = \Drupal::configFactory();

    // Create a fallback text format.
    FilterFormat::create(['format' => 'fallback', 'name' => 'Filter format test'])->save();
    $config_factory
      ->getEditable('filter.settings')
      ->set('fallback_format', 'fallback')
      ->save();

    FilterFormat::create(['format' => 'test_format1', 'name' => 'Filter format test 2'])->save();
    FilterFormat::create(['format' => 'test_format2', 'name' => 'Filter format test 3'])->save();
    MessageTemplate::create([
      'template' => 'foo',
      'text' => [
        [
          'value' => 'text...',
          'format' => 'test_format1',
        ],
        [
          'value' => 'other text',
          'format' => 'test_format2',
        ],
      ],
    ])->save();

    /** @var \Drupal\message\MessageTemplateInterface $template */
    $template = MessageTemplate::load('foo');
    $dependencies = $template->getDependencies() + ['config' => []];

    // Check that proper dependencies were calculated.
    $this->assertSame([
      'filter.format.test_format1',
      'filter.format.test_format2',
    ], $dependencies['config']);

    // Remove the 2nd filter format.
    FilterFormat::load('test_format2')->delete();

    $template = MessageTemplate::load('foo');
    $dependencies = $template->getDependencies() + ['config' => []];

    // Check that 'test_format2' has been replaced with 'fallback'.
    $this->assertSame('fallback', $template->get('text')[1]['format']);
    $this->assertSame([
      'filter.format.fallback',
      'filter.format.test_format1',
    ], $dependencies['config']);
  }

}
