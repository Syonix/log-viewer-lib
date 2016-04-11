<?php
namespace Syonix\LogViewer\Console\Command;

class LintCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('config:lint')
            ->setDescription('Lint your config file to detect potential problems')
            ->addArgument(
                'config_file',
                InputArgument::REQUIRED,
                'The path to your config file'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // TODO: Implement
    }
}
