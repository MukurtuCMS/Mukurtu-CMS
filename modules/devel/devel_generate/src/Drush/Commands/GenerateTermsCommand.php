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
  name: 'devel-generate:terms',
  description: 'Create terms in specified vocabulary.',
  aliases: ['gent', 'devel-generate-terms']
)]
final class GenerateTermsCommand extends Command {

  use AutowireTrait;

  const string PLUGIN_ID = 'term';

  public function __construct(
    protected DevelGeneratePluginManager $manager,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this
      ->addArgument('num', InputArgument::OPTIONAL, 'Number of terms to generate.', '50')
      ->addOption('kill', NULL, InputOption::VALUE_NONE, 'Delete all terms in these vocabularies before generating new ones.')
      ->addOption('bundles', NULL, InputOption::VALUE_REQUIRED, 'A comma-delimited list of machine names for the vocabularies where terms will be created.')
      ->addOption('feedback', NULL, InputOption::VALUE_REQUIRED, 'An integer representing interval for insertion rate logging.', '1000')
      ->addOption('languages', NULL, InputOption::VALUE_REQUIRED, 'A comma-separated list of language codes')
      ->addOption('translations', NULL, InputOption::VALUE_REQUIRED, 'A comma-separated list of language codes for translations.')
      ->addOption('min-depth', NULL, InputOption::VALUE_REQUIRED, 'The minimum depth of hierarchy for the new terms.', '1')
      ->addOption('max-depth', NULL, InputOption::VALUE_REQUIRED, 'The maximum depth of hierarchy for the new terms.', '4');
  }

  public function execute(InputInterface $input, OutputInterface $output): int {
    /** @var \Drupal\devel_generate\DevelGenerateBaseInterface $instance */
    $instance = $this->manager->createInstance(self::PLUGIN_ID, []);
    $parameters = $instance->validateDrushParams($input->getArguments(), $input->getOptions());
    $instance->generate($parameters);
    return Command::SUCCESS;
  }

}
