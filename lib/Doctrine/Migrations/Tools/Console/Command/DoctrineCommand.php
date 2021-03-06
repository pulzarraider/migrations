<?php

declare(strict_types=1);

namespace Doctrine\Migrations\Tools\Console\Command;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\ConnectionLoader;
use Doctrine\Migrations\Tools\Console\ConsoleLogger;
use Doctrine\Migrations\Tools\Console\Exception\DependenciesNotSatisfied;
use Doctrine\Migrations\Tools\Console\Helper\ConfigurationHelper;
use Doctrine\Migrations\Tools\Console\Helper\MigrationsConfigurationHelper;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use function escapeshellarg;
use function is_string;
use function proc_open;
use function str_repeat;
use function strlen;

/**
 * The DoctrineCommand class provides base functionality for the other migrations commands to extend from.
 */
abstract class DoctrineCommand extends Command
{
    /** @var DependencyFactory|null */
    private $dependencyFactory;

    public function __construct(?string $name = null, ?DependencyFactory $dependencyFactory = null)
    {
        parent::__construct($name);
        $this->dependencyFactory = $dependencyFactory;
    }

    protected function configure() : void
    {
        $this->addOption(
            'configuration',
            null,
            InputOption::VALUE_OPTIONAL,
            'The path to a migrations configuration file.'
        );

        $this->addOption(
            'db-configuration',
            null,
            InputOption::VALUE_OPTIONAL,
            'The path to a database connection configuration file.'
        );
    }

    protected function outputHeader(
        OutputInterface $output
    ) : void {
        $name = $this->getDependencyFactory()->getConfiguration()->getName();
        $name = $name ?? 'Doctrine Database Migrations';
        $name = str_repeat(' ', 20) . $name . str_repeat(' ', 20);
        $output->writeln('<question>' . str_repeat(' ', strlen($name)) . '</question>');
        $output->writeln('<question>' . $name . '</question>');
        $output->writeln('<question>' . str_repeat(' ', strlen($name)) . '</question>');
        $output->writeln('');
    }

    protected function initialize(InputInterface $input, OutputInterface $output) : void
    {
        if ($this->dependencyFactory !== null) {
            $this->dependencyFactory->freeze();

            return;
        }

        $helperSet = $this->getHelperSet() ?: new HelperSet();

        if ($helperSet->has('configuration') && $helperSet->get('configuration') instanceof ConfigurationHelper) {
            /** @var MigrationsConfigurationHelper $configHelper */
            $configHelper = $helperSet->get('configuration');
        } else {
            $configHelper = new MigrationsConfigurationHelper();
        }

        $configuration = $configHelper->getConfiguration($input);

        $dbConfig = is_string($input->getOption('db-configuration')) ? $input->getOption('db-configuration'): null;

        $connection = (new ConnectionLoader())
            ->getConnection($dbConfig, $helperSet);

        $em = null;
        if ($helperSet->has('em') && $helperSet->get('em') instanceof EntityManagerHelper) {
            $em = $helperSet->get('em')->getEntityManager();
        }

        $logger                  = new ConsoleLogger($output);
        $this->dependencyFactory = new DependencyFactory($configuration, $connection, $em, $logger);
        $this->dependencyFactory->freeze();
    }

    protected function getDependencyFactory() : DependencyFactory
    {
        if ($this->dependencyFactory === null) {
            throw DependenciesNotSatisfied::new();
        }

        return $this->dependencyFactory;
    }

    protected function askConfirmation(
        string $question,
        InputInterface $input,
        OutputInterface $output
    ) : bool {
        return $this->getHelper('question')->ask(
            $input,
            $output,
            new ConfirmationQuestion($question)
        );
    }

    protected function canExecute(
        string $question,
        InputInterface $input,
        OutputInterface $output
    ) : bool {
        return ! $input->isInteractive() || $this->askConfirmation($question, $input, $output);
    }

    protected function procOpen(string $editorCommand, string $path) : void
    {
        proc_open($editorCommand . ' ' . escapeshellarg($path), [], $pipes);
    }
}
