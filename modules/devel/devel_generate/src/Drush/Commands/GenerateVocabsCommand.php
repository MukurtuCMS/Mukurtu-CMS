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
  name: 'devel-generate:vocabs',
  description: 'Create vocabularies.',
  aliases: ['genv', 'devel-generate-vocabs']
)]
#[CLI\ValidateModulesEnabled(modules: ['taxonomy'])]
final class GenerateVocabsCommand extends Command {

  use AutowireTrait;

  const string PLUGIN_ID = 'vocabulary';

  public function __construct(
    protected DevelGeneratePluginManager $manager,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this
      ->addArgument('num', InputArgument::OPTIONAL, 'Number of vocabularies to generate.', '1')
      ->addOption('kill', NULL, InputOption::VALUE_NONE, 'Delete all vocabs before generating new ones.');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    /** @var \Drupal\devel_generate\DevelGenerateBaseInterface $instance */
    $instance = $this->manager->createInstance(self::PLUGIN_ID, []);
    $parameters = $instance->validateDrushParams($input->getArguments(), $input->getOptions());
    $instance->generate($parameters);
    return Command::SUCCESS;
  }

}
