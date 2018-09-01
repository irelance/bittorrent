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

class Output extends Command
{
    protected function configure()
    {
        $this
            ->setHidden(true)
            ->setName('output')
            ->setDescription('output style test')
            ->setHelp('This command allows you to test output style');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bar = new ProgressBar($output, 3);
        $bar->setFormat("<info>%message%</info>\n%bar%\n");
        $bar->setMessage('Task starts');
        $bar->advance();
        sleep(1);
        $bar->setMessage('Step 1');
        $bar->advance();
        sleep(1);
        $bar->setMessage('Step 2');
        $bar->advance();
        sleep(1);
        $bar->clear();
    }

    protected function progress(InputInterface $input, OutputInterface $output)
    {
        $files = ['a.php', 'b.php', 'c.php'];
        $bar = new ProgressBar($output, 3);
        $bar->setFormat(" %message%\n %current%/%max%\n Working on %filename%\n");
        $bar->setMessage('Task starts');
        $bar->setMessage('', 'filename');
        $bar->start();

        $bar->setMessage('Task is in progress...');
        while ($file = array_pop($files)) {
            sleep(1);
            $bar->setMessage($file, 'filename');
            $bar->advance();
        }

        $bar->setMessage('Task is finished');
        $bar->setMessage('', 'filename');
        $bar->finish();
    }
}