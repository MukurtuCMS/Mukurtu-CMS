<?php

declare(strict_types=1);

namespace Drupal\devel\Drush\Commands;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: self::NAME,
  description: 'Get a list of available container services.',
  aliases: ['devel-container-services', 'dcs', 'devel-services']
)]
#[CLI\FieldLabels(labels: [
  'id' => 'Id',
])]
#[CLI\DefaultTableFields(fields: ['id'])]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
final class DevelServicesCommand extends Command {

  use AutowireTrait;
  use FormatterTrait;

  public const string NAME = 'devel:services';

  public function __construct(
    protected FormatterManager $formatterManager,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this
      ->addArgument('prefix', InputArgument::OPTIONAL, 'Optional prefix to filter the service list by.')
      ->addUsage('dcs plugin.manager');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    $data = $this->doExecute($input, $output);
    $this->writeFormattedOutput($input, $output, $data);
    return Command::SUCCESS;
  }

  protected function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields {
    $prefix = $input->getArgument('prefix');
    $services = \Drupal::getContainer()->getServiceIds();

    if ($prefix !== NULL && $prefix !== '') {
      $services = preg_grep(sprintf('/%s/', $prefix), $services);
    }

    if (empty($services)) {
      throw new \RuntimeException(dt('No container services found.'));
    }
    sort($services);
    foreach ($services as $service) {
      $all[]['id'] = $service;
    }
    return new RowsOfFields($all);
  }

}
