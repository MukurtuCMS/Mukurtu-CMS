<?php

declare(strict_types=1);

namespace Drupal\devel\Drush\Commands;

use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Commands\AutowireTrait;
use Drush\Commands\pm\PmCommands;
use Drush\Drush;
use Drush\SiteAlias\ProcessManager;
use Drush\Style\DrushStyle;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: self::NAME,
  description: 'Uninstall, and Install modules.',
  aliases: ['dre', 'devel-reinstall'],
)]
final class DevelReinstallCommand extends Command {

  use AutowireTrait;

  const NAME = 'devel:reinstall';

  public function __construct(
    private readonly SiteAliasManagerInterface $siteAliasManager,
    private readonly ProcessManager $processManager,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this
      ->addArgument('modules', InputArgument::REQUIRED, 'A comma-separated list of module names.');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $modules = StringUtils::csvToArray($input->getArgument('modules'));
    $modules_str = implode(',', $modules);
    $options = Drush::redispatchOptions();
    $process = $this->processManager->drush($this->siteAliasManager->getSelf(), PmCommands::UNINSTALL, [$modules_str], $options);
    $process->mustRun();
    $process = $this->processManager->drush($this->siteAliasManager->getSelf(), PmCommands::INSTALL, [$modules_str], $options);
    $process->mustRun();
    (new DrushStyle($input, $output))->success(sprintf('%s reinstalled.', $modules_str));
    return Command::SUCCESS;
  }

}
