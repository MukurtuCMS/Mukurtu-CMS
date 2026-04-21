<?php

namespace Drupal\Tests\paragraphs\Functional\WidgetStable;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Enables replicate module.
 *
 * @group paragraphs
 */
#[RunTestsInSeparateProcesses]
#[Group('paragraphs')]
class ParagraphsReplicateEnableTest extends ParagraphsDuplicateFeatureTest {

  protected static $modules = [
    'replicate',
  ];

}
