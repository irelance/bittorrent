<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/30
 * Time: 13:43
 */

namespace Irelance\Torrent\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class Download extends Command
{
    protected function configure()
    {
        $this
            ->setHidden(true)
            ->setName('download')
            ->setDescription('download pieces from peer')
            ->setHelp('This command allows you to download pieces from peer');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
    }
}