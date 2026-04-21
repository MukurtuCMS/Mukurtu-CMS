<?php

namespace Drupal\Tests\twig_tweak\Kernel;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\Tests\TestFileCreationTrait;

/**
 * A base class of URL and URI extractor tests.
 */
abstract class AbstractExtractorTestCase extends KernelTestBase {

  use TestFileCreationTrait;

  /**
   * A node to test.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'twig_tweak',
    'twig_tweak_test',
    'system',
    'views',
    'node',
    'block',
    'image',
    'field',
    'text',
    'media',
    'file',
    'user',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['node', 'twig_tweak_test']);
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');

    $test_files = $this->getTestFiles('image');

    $image_file = File::create([
      'uri' => $test_files[0]->uri,
      'uuid' => 'a2cb2b6f-7bf8-4da4-9de5-316e93487518',
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $image_file->save();

    $media_file = File::create([
      'uri' => $test_files[2]->uri,
      'uuid' => '5dd794d0-cb75-4130-9296-838aebc1fe74',
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $media_file->save();

    $media = Media::create([
      'bundle' => 'image',
      'name' => 'Image 1',
      'field_media_image' => ['target_id' => $media_file->id()],
    ]);
    $media->save();

    $node_values = [
      'title' => 'Alpha',
      'type' => 'page',
      'field_image' => [
        'target_id' => $image_file->id(),
      ],
      'field_media' => [
        'target_id' => $media->id(),
      ],
    ];
    $this->node = Node::create($node_values);
  }

}
