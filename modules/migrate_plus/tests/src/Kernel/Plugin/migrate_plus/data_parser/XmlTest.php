<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate_plus\Kernel\Plugin\migrate_plus\data_parser;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test of the data_parser Xml migrate_plus plugin.
 */
#[Group('migrate_plus')]
#[RunTestsInSeparateProcesses]
final class XmlTest extends BaseXml {

  /**
   * {@inheritdoc}
   */
  public function testParentTraversalMatch(): void {
    $this->markTestSkipped('This is currently unsupported.');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDataParserPluginId(): string {
    return 'xml';
  }

}
