<?php

declare(strict_types=1);

namespace Drupal\devel\Drush\Commands;

use Consolidation\SiteProcess\Util\Escape;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Exceptions\UserAbortException;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\ProcessManager;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
  name: self::NAME,
  description: 'List implementations of a given hook and optionally edit one.',
  aliases: ['fnh', 'fn-hook', 'hook', 'devel-hook']
)]
#[CLI\OptionsetGetEditor()]
class DevelHookCommand extends Command {

  use AutowireTrait;
  use CodeTrait;
  use ExecTrait;

  const NAME = 'devel:hook';

  public function __construct(
    protected readonly ProcessManager $processManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this
      ->addArgument('hook', mode: InputArgument::REQUIRED, description: 'The name of the hook to explore.')
      ->addArgument(name: 'implementation', mode: InputArgument::OPTIONAL, description: 'The name of the implementation to edit. Usually omitted')
      ->addUsage('devel:hook cron');
  }

  protected function interact(InputInterface $input, OutputInterface $output) {
    if (!$hook = $input->getArgument('hook')) {
      throw new \InvalidArgumentException(dt('No hook specified.'));
    }

    $hook_implementations = [];
    if (!$input->getArgument('implementation')) {
      foreach (array_keys($this->moduleHandler->getModuleList()) as $key) {
        if ($this->moduleHandler->hasImplementations($hook, [$key])) {
          $hook_implementations[] = $key;
        }
      }

      if ($hook_implementations !== []) {
        if (!$choice = (new DrushStyle($input, $output))->select('Select the hook implementation you wish to view.', array_combine($hook_implementations, $hook_implementations))) {
          throw new UserAbortException();
        }

        $input->setArgument('implementation', $choice);
      }
      else {
        throw new \InvalidArgumentException(dt('No implementations'));
      }
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    // Get implementations in the .install files as well.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';
    drupal_load_updates();
    $info = $this->codeLocate($input->getArgument('implementation') . ('_' . $input->getArgument('hook')));
    $exec = self::getEditor('');
    $cmd = sprintf($exec, Escape::shellArg($info['file']));
    $process = $this->processManager->shell($cmd);
    $process->setTty(TRUE);
    $process->mustRun();
    return Command::SUCCESS;
  }

}
