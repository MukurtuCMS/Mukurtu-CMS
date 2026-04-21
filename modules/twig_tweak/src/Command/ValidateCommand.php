<?php

namespace Drupal\twig_tweak\Command;

use Symfony\Component\Finder\Finder;

/**
 * Implements twig-tweak:lint console command.
 *
 * @cspell:ignore friendsoftwig, twigcs
 *
 * @todo Remove this in 4.x.
 */
final class ValidateCommand extends LintCommand {

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {

    if (!\class_exists(Finder::class)) {
      throw new \LogicException('To validate Twig templates you must install symfony/finder component.');
    }

    parent::configure();
    $this->setName('twig-tweak:validate');
    $this->setAliases(['twig-validate']);
    $this->setHelp(
      $this->getHelp() . <<< 'TEXT'

      This command only validates Twig Syntax. For checking code style
      consider using <info>friendsoftwig/twigcs</info> package.
      TEXT
    );
  }

}
