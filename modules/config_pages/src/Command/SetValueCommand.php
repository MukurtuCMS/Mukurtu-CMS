<?php

namespace Drupal\config_pages\Command;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\Command;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Class Drupal command.
 *
 * @DrupalCommand (
 *     extension="config_pages",
 *     extensionType="module"
 * )
 */
class SetValueCommand extends Command {

  /**
   * Logger interface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new SetValueCommand object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('config_pages');
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('config_pages:set_value')
      ->setDescription($this->trans('commands.config_pages.set_value.description'))
      ->addArgument(
        'bundle',
        InputArgument::REQUIRED,
        $this->trans('commands.user.login.url.options.bundle'),
        NULL
      )
      ->addArgument(
        'field_name',
        InputArgument::REQUIRED,
        $this->trans('commands.user.login.url.options.field_name'),
        NULL
      )
      ->addArgument(
        'value',
        InputArgument::REQUIRED,
        $this->trans('commands.user.login.url.options.value'),
        NULL
      )
      ->addArgument(
        'context',
        InputArgument::OPTIONAL,
        $this->trans('commands.user.login.url.options.context'),
        NULL
      )->setAliases(['cpsfv']);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $bundle = $input->getArgument('bundle');
    $field_name = $input->getArgument('field_name');
    $value = $input->getArgument('value');
    $context = $input->getArgument('context');

    try {
      $config_page = config_pages_config($bundle, $context);

      if (empty($config_page)) {
        $type = ConfigPagesType::load($bundle);
        $config_page = ConfigPages::create([
          'type' => $bundle,
          'label' => $type->label(),
          'context' => $type->getContextData(),
        ]);
        $config_page->save();
      }

      $config_page->set($field_name, str_replace('\n', PHP_EOL, $value));
      $config_page->save();

      $output->writeln('Saved new value for ' . $field_name . ' field.');
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

}
