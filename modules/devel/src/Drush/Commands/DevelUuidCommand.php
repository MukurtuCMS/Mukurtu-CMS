<?php

declare(strict_types=1);

namespace Drupal\devel\Drush\Commands;

use Drupal\Component\Uuid\Php;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: self::NAME,
  description: 'Generate a Universally Unique Identifier (UUID).',
  aliases: ['uuid', 'devel-uuid']
)]
final class DevelUuidCommand extends Command {

  public const NAME = 'devel:uuid';

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $uuid = new Php();
    $output->writeln($uuid->generate());
    return Command::SUCCESS;
  }

}
