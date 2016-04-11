<?php
namespace Syonix\LogViewer\Console\Command;

class LintCommand extends Command
{
    private $configDefaultPath;
    
    protected function configure($configDefaultPath = null)
    {
        $this->configDefaultPath = $configDefaultPath;
        
        $this
            ->setName('config:lint')
            ->setDescription('Lint your config file to detect potential problems')
            ->addArgument(
                'config_file',
                InputArgument::OPTIONAL,
                'The path to your config file'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // TODO: Implement
        // TODO: If config file not given, search in $this->configDefaultPath
    }
}
