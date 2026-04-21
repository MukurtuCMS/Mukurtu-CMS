<?php

declare(strict_types=1);

namespace Drupal\devel\Drush\Commands;

use Consolidation\SiteProcess\Util\Escape;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
  name: self::NAME,
  description: 'List implementations of a given event and optionally edit one.',
  aliases: ['fne', 'fn-event', 'event']
)]
class DevelEventCommand extends Command {

  use AutowireTrait;
  use CodeTrait;
  use ExecTrait;

  const NAME = 'devel:event';

  public function __construct(
    protected readonly ProcessManager $processManager,
    protected readonly EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this
      ->addArgument('event', mode: InputArgument::REQUIRED, description: 'The name of the event to explore.')
      ->addArgument(name: 'implementation', mode: InputArgument::OPTIONAL, description: 'The name of the implementation to edit. Usually omitted')
      ->addUsage('devel-event kernel.terminate');
  }

  protected function interact(InputInterface $input, OutputInterface $output) {
    $event = $input->getArgument('event');
    $io = new DrushStyle($input, $output);
    if (!$event) {
      // @todo Expand this list.
      $events = [
        'kernel.controller',
        'kernel.exception',
        'kernel.request',
        'kernel.response',
        'kernel.terminate',
        'kernel.view',
      ];
      $events = array_combine($events, $events);
      if (!$event = $io->select('Enter the event you wish to explore.', $events)) {
        throw new UserAbortException();
      }

      $input->setArgument('event', $event);
    }

    /** @var \Symfony\Component\EventDispatcher\EventDispatcher $event_dispatcher */
    $event_dispatcher = $this->eventDispatcher;
    if ($implementations = $event_dispatcher->getListeners($event)) {
      $choices = [];
      foreach ($implementations as $implementation) {
        $callable = $implementation[0]::class . '::' . $implementation[1];
        $choices[$callable] = $callable;
      }

      if (!$choice = $io->select('Select the implementation you wish to view.', $choices)) {
        throw new UserAbortException();
      }

      $input->setArgument('implementation', $choice);
    }
    else {
      throw new \Exception(dt('No implementations.'));
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $info = $this->codeLocate($input->getArgument('implementation'));
    $exec = self::getEditor('');
    $cmd = sprintf($exec, Escape::shellArg($info['file']));
    $process = $this->processManager->shell($cmd);
    $process->setTty(TRUE);
    $process->mustRun();
    return Command::SUCCESS;
  }

}
