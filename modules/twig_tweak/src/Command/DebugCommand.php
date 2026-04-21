<?php

namespace Drupal\twig_tweak\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * Lists twig functions, filters, and tests present in the current project.
 *
 * This is a simplified version of Symfony's Debug command.
 *
 * @see https://github.com/symfony/symfony/blob/5.x/src/Symfony/Bridge/Twig/Command/DebugCommand.php
 */
final class DebugCommand extends Command {

  /**
   * Twig environment.
   *
   * @var \Twig\Environment
   */
  private $twig;

  /**
   * {@inheritdoc}
   */
  public function __construct(Environment $twig) {
    parent::__construct();
    $this->twig = $twig;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->setName('twig-tweak:debug')
      ->setAliases(['twig-debug'])
      ->addOption('filter', NULL, InputOption::VALUE_REQUIRED, 'Show details for all entries matching this filter.')
      ->setDescription('Shows a list of twig functions, filters, globals and tests')
      ->setHelp(<<<'EOF'
        The <info>%command.name%</info> command outputs a list of twig functions,
        filters, globals and tests.

          <info>drush %command.name%</info>

        The command lists all functions, filters, etc.

          <info>drush %command.name% --filter=date</info>

        The command lists everything that contains the word date.
      EOF);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $filter = $input->getOption('filter');

    $types = ['functions', 'filters', 'tests'];
    foreach ($types as $type) {
      $items = [];
      foreach ($this->twig->{'get' . ucfirst($type)}() as $name => $entity) {
        if (!$filter || \strpos($name, $filter) !== FALSE) {

          $signature = '';
          // Tests are typically implemented as Twig nodes so that it is hard
          // to get their signatures through reflection.
          if ($type == 'filters' || $type == 'functions') {
            try {
              $meta = $this->getMetadata($type, $entity);
              $default_signature = $type == 'functions' ? '()' : '';
              $signature = $meta ? '(' . implode(', ', $meta) . ')' : $default_signature;
            }
            catch (\UnexpectedValueException $exception) {
              $signature = sprintf(' <error>%s</error>', OutputFormatter::escape($exception->getMessage()));
            }
          }

          $items[$name] = $name . $signature;
        }
      }

      if (!$items) {
        continue;
      }

      $io->section(\ucfirst($type));

      ksort($items);
      $io->listing($items);
    }

    if (!$filter && $loaderPaths = $this->getLoaderPaths()) {
      $io->section('Loader Paths');
      $rows = [];
      foreach ($loaderPaths as $namespace => $paths) {
        foreach ($paths as $path) {
          $rows[] = [$namespace, $path . \DIRECTORY_SEPARATOR];
        }
      }
      $io->table(['Namespace', 'Paths'], $rows);

    }

    return 0;
  }

  /**
   * Gets loader paths.
   */
  private function getLoaderPaths(): array {
    $loaderPaths = [];
    foreach ($this->getFilesystemLoaders() as $loader) {
      foreach ($loader->getNamespaces() as $namespace) {
        $paths = $loader->getPaths($namespace);
        $namespace = FilesystemLoader::MAIN_NAMESPACE === $namespace ?
          '(None)' : '@' . $namespace;
        $loaderPaths[$namespace] = array_merge($loaderPaths[$namespace] ?? [], $paths);
      }
    }
    return $loaderPaths;
  }

  /**
   * Gets metadata.
   */
  private function getMetadata(string $type, $entity): ?array {

    $callable = $entity->getCallable();

    if (!$callable) {
      return NULL;
    }
    if (\is_array($callable)) {
      if (!method_exists($callable[0], $callable[1])) {
        return NULL;
      }
      $reflection = new \ReflectionMethod($callable[0], $callable[1]);
    }
    elseif (\is_object($callable) && method_exists($callable, '__invoke')) {
      $reflection = new \ReflectionMethod($callable, '__invoke');
    }
    elseif (\function_exists($callable)) {
      $reflection = new \ReflectionFunction($callable);
    }
    elseif (\is_string($callable) && preg_match('{^(.+)::(.+)$}', $callable, $m) && method_exists($m[1], $m[2])) {
      $reflection = new \ReflectionMethod($m[1], $m[2]);
    }
    else {
      throw new \UnexpectedValueException('Unsupported callback type.');
    }

    $args = $reflection->getParameters();

    // Filter out context/environment args.
    if ($entity->needsEnvironment()) {
      array_shift($args);
    }
    if ($entity->needsContext()) {
      array_shift($args);
    }

    if ($type == 'filters') {
      // Remove the value the filter is applied on.
      array_shift($args);
    }

    // Format args.
    $args = array_map(
      static function (\ReflectionParameter $param): string {
        $arg = $param->getName();
        if ($param->isDefaultValueAvailable()) {
          $arg .= ' = ' . \json_encode($param->getDefaultValue());
        }
        return $arg;
      },
      $args,
    );

    return $args;
  }

  /**
   * Returns files system loaders.
   *
   * @return \Twig\Loader\FilesystemLoader[]
   *   File system loaders.
   */
  private function getFilesystemLoaders(): array {
    $loaders = [];

    $loader = $this->twig->getLoader();
    if ($loader instanceof FilesystemLoader) {
      $loaders[] = $loader;
    }
    elseif ($loader instanceof ChainLoader) {
      foreach ($loader->getLoaders() as $chained_loaders) {
        if ($chained_loaders instanceof FilesystemLoader) {
          $loaders[] = $chained_loaders;
        }
      }
    }

    return $loaders;
  }

}
