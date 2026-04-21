<?php

declare(strict_types=1);

namespace Drupal\devel_generate\Drush\Commands;

use Drupal\devel_generate\DevelGeneratePluginManager;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: 'devel-generate:block-content',
  description: 'Create Block content blocks.',
  aliases: ['genbc', 'devel-generate-block-content']
)]
#[CLI\ValidateModulesEnabled(modules: ['block_content'])]
final class GenerateBlockCommand extends Command {

  use AutowireTrait;

  const string PLUGIN_ID = 'block_content';

  public function __construct(
    protected DevelGeneratePluginManager $manager,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this
      ->addArgument('num', InputArgument::OPTIONAL, 'Number of blocks to generate.', '50')
      ->addOption('kill', NULL, InputOption::VALUE_NONE, 'Delete all block content before generating new.')
      ->addOption('block_types', NULL, InputOption::VALUE_REQUIRED, 'A comma-delimited list of block content types to create.', 'basic')
      ->addOption('authors', NULL, InputOption::VALUE_REQUIRED, 'A comma delimited list of authors ids. Defaults to all users.')
      ->addOption('feedback', NULL, InputOption::VALUE_REQUIRED, 'An integer representing interval for insertion rate logging.', '1000')
      ->addOption('skip-fields', NULL, InputOption::VALUE_REQUIRED, 'A comma delimited list of fields to omit when generating random values')
      ->addOption('base-fields', NULL, InputOption::VALUE_REQUIRED, 'A comma delimited list of base field names to populate')
      ->addOption('languages', NULL, InputOption::VALUE_REQUIRED, 'A comma-separated list of language codes')
      ->addOption('translations', NULL, InputOption::VALUE_REQUIRED, 'A comma-separated list of language codes for translations.')
      ->addOption('add-type-label', NULL, InputOption::VALUE_NONE, 'Add the block type label to the front of the node title')
      ->addOption('reusable', NULL, InputOption::VALUE_NONE, 'Create re-usable blocks. Disable for inline Layout Builder blocks, for example.');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    /** @var \Drupal\devel_generate\DevelGenerateBaseInterface $instance */
    $instance = $this->manager->createInstance(self::PLUGIN_ID, []);
    $parameters = $instance->validateDrushParams($input->getArguments(), $input->getOptions());
    $instance->generate($parameters);
    return Command::SUCCESS;
  }

}
