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
  name: 'devel-generate:menus',
  description: 'Create menus.',
  aliases: ['genm', 'devel-generate-menus']
)]
final class GenerateMenusCommand extends Command {

  use AutowireTrait;

  const string PLUGIN_ID = 'menu';

  public function __construct(
    protected DevelGeneratePluginManager $manager,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this
      ->addArgument('number_menus', InputArgument::OPTIONAL, 'Number of menus to generate.', '2')
      ->addArgument('number_links', InputArgument::OPTIONAL, 'Number of links to generate.', '50')
      ->addArgument('max_depth', InputArgument::OPTIONAL, 'Max link depth.', '3')
      ->addArgument('max_width', InputArgument::OPTIONAL, 'Max width of first level of links.', '8')
      ->addOption('kill', NULL, InputOption::VALUE_NONE, 'Delete any menus and menu links previously created by devel_generate before generating new ones.');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    /** @var \Drupal\devel_generate\DevelGenerateBaseInterface $instance */
    $instance = $this->manager->createInstance(self::PLUGIN_ID, []);
    $parameters = $instance->validateDrushParams($input->getArguments(), $input->getOptions());
    $instance->generate($parameters);
    return Command::SUCCESS;
  }

}
