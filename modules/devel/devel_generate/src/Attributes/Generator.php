<?php

declare(strict_types=1);

namespace Drupal\devel_generate\Attributes;

use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use JetBrains\PhpStorm\Deprecated;

/**
 * Devel generate plugin details.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
#[Deprecated('Used directly by Commands')]
class Generator {

  public function __construct(
    public string $id,
  ) {}

  public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo): void {
    $args = $attribute->getArguments();
    $commandInfo->addAnnotation('pluginId', $args['id']);
  }

}
