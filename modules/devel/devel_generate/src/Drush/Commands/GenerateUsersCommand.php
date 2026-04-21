<?php

declare(strict_types=1);

namespace Drupal\devel_generate\Drush\Commands;

use Drupal\devel_generate\DevelGeneratePluginManager;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'devel-generate:users',
  description: 'Create users.',
  aliases: ['genu', 'devel-generate-users']
)]
final class GenerateUsersCommand extends Command {

  use AutowireTrait;

  const string PLUGIN_ID = 'user';

  public function __construct(
    protected DevelGeneratePluginManager $manager,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this
      ->addArgument('num', InputArgument::OPTIONAL, 'Number of users to generate.', 50)
      ->addOption('kill', NULL, InputOption::VALUE_NONE, 'Delete all users before generating new ones.')
      ->addOption('roles', NULL, InputOption::VALUE_REQUIRED, 'A comma delimited list of role IDs for new users. Don\'t specify authenticated.')
      ->addOption('pass', NULL, InputOption::VALUE_REQUIRED, 'Specify a password to be set for all generated users.');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    /** @var \Drupal\devel_generate\DevelGenerateBaseInterface $instance */
    $instance = $this->manager->createInstance(self::PLUGIN_ID, []);
    $parameters = $instance->validateDrushParams($input->getArguments(), $input->getOptions());
    $instance->generate($parameters);
    return Command::SUCCESS;
  }

}
