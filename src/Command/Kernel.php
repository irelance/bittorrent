<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/29
 * Time: 14:13
 */

namespace Irelance\Torrent\Command;

use Irelance\Torrent\Client\Download;
use Irelance\Torrent\Command\Tracker\Http;
use Irelance\Torrent\Torrent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Webmozart\PathUtil\Path;
use Symfony\Component\Process\Process;

class Kernel extends Command
{
    protected function configure()
    {
        $this
            ->setName('download')
            ->setDescription('Torrent Download Manage')
            ->addOption(
                'add-torrent',
                'a',
                InputOption::VALUE_REQUIRED,
                'Add Torrent to Download list.',
                ''
            )
            ->addOption(
                'set-save-path',
                's',
                InputOption::VALUE_REQUIRED,
                'The path to save in Disk.',
                ''
            )
            ->addOption(
                'download',
                'd',
                InputOption::VALUE_NONE,
                'start download'
            )
            //->addArgument('a', InputArgument::OPTIONAL, 'Add Torrent to Download list.')
            ->setHelp('This command allows you to add a Torrent to Download list');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (($file = $input->getOption('add-torrent')) && $torrent = Torrent::file($file)) {
            $download = new Download(bin2hex($torrent->infoHash));
            $path = Path::makeAbsolute($input->getOption('set-save-path'), getcwd());
            $download->addMission($path);
        }
        if ($input->getOption('download')) {
            $output->writeln('<info>Start Download:</info>');
            $tracker = new Process(['php', 'seeder', 'tracker:http', '-a']);
            $tracker->start();
            $tracker->signal(SIGKILL);
        }
    }
}