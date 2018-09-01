<?php
/**
 * Created by PhpStorm.
 * User: heirelance
 * Date: 2018/8/22
 * Time: 15:30
 */

namespace Irelance\Torrent\Command\Tracker;

use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Arr;
use Irelance\Torrent\Bencode;
use Irelance\Torrent\Client\Download;
use Irelance\Torrent\Client\TorrentPeer;
use Irelance\Torrent\Client\TorrentTracker;
use Irelance\Torrent\Server\Peer;
use Irelance\Torrent\Client\Tracker;
use Irelance\Torrent\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class Http extends Command
{
    public static $concurrency = 5;//并发量

    protected function configure()
    {
        $this
            ->setName('tracker:http')
            ->setDescription('Tracker Http announce or scrape')
            ->addOption(
                'announce',
                'a',
                InputOption::VALUE_NONE,
                'start announce'
            )
            ->setHelp('This command allows you to add a Torrent to Download list');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('announce')) {
            $this->announce($input, $output);
        }
    }

    protected function announce(InputInterface $input, OutputInterface $output)
    {
        $startBar = new ProgressBar($output);
        $startBar->setFormat("<info>HTTP announce start!</info>\n");
        $startBar->advance();
        $now = time();
        $keys = [];
        foreach (TorrentTracker::get() as $torrentTracker) {
            $download = Download::find($torrentTracker->info_hash);
            $tracker = Tracker::find($torrentTracker->tracker);
            if (!$torrentTracker->status) {
                $torrentTracker->save();
                $torrentTracker->flush();
                continue;
            }
            if ($torrentTracker->next_update > $now) {
                continue;
            }
            if (!in_array($tracker->scheme, ['http', 'https'])) {
                continue;
            }
            $data = [
                'info_hash' => $torrentTracker->info_hash,
                'peer_id' => Peer::$peerId,
                'port' => Peer::$port,
                'uploaded' => $download->uploaded,
                'downloaded' => $download->downloaded,
                'left' => $download->left,
                'corrupt' => '0',//todo to make sure what does it means
                'compact' => Tracker::$compact,
                'key' => Peer::$key, //(可选)，不和其他用户共享的附加标识。当客户端IP地址改变时，可以使用该标识来证明自己的身份。
                'event' => $download->event,
                'numwant' => Tracker::$numwant,
                'no_peer_id' => Tracker::$noPeerId,//(可选)，如果=1，且返回的peers是dictionary model，则不返回peer id
            ];
            //(可选)，如果之前的announce包含一个tracker id，那么当前的请求必须设置该参数。
            if ($torrentTracker->tracker_id) {
                $data['trackerid'] = $torrentTracker->tracker_id;
            }
            /**
             * todo
             * 仅在请求进入的IP地址不是客户端的IP地址的情况下才需要该参数。如果客户端通过代理（或透明的Web代理/缓存）与跟踪器通信，则会发生这种情况。
             * 当客户端和跟踪器都位于NAT网关的同一本地端时，也需要这样做。这样做的原因是否则跟踪器会给出客户端的内部（RFC1918）地址
             * $data['ip'] = 'xxx.xxx.xxx.xxx';
             */
            $keys[] = [
                'torrent_tracker' => $torrentTracker,
                'url' => $torrentTracker->tracker . '?' . Arr::query($data),
            ];
        }
        $count = count($keys);
        $processBar = new ProgressBar($output, $count);
        $startBar->clear();
        $processBar->setFormat("<info>HTTP announce processing:</info>%current%/%max%\n Working on <info>%url%</info>\n <comment>%message%</comment>\n");
        $processBar->setMessage('');
        $processBar->setMessage('', 'url');
        $processBar->display();
        $client = new Client();

        $requests = function ($total) use ($keys) {
            for ($i = 0; $i < $total; $i++) {
                yield new Request('GET', $keys[$i]['url']);
            }
        };

        $pool = new Pool($client, $requests($count), [
            'concurrency' => 5,
            'timeout' => 3,
            'connect_timeout' => 3,
            'fulfilled' => function ($response, $i) use ($keys, $processBar) {
                $processBar->setMessage('');
                $processBar->setMessage(parse_url($keys[$i]['url'])['host'], 'url');
                if (!$response = Bencode::bdecode($response->getBody()->getContents())) {
                    $keys[$i]['torrent_tracker']->status = false;
                    $processBar->setMessage('Bencode decode fail!');
                    return false;
                }

                if ($fatal = Arr::get($response, 'failure reason')) {
                    if (Arr::has($response, 'retry in') && $keys[$i]['torrent_tracker']->is_new) {
                        $retryIn = Arr::get($response, 'retry in');
                        $keys[$i]['torrent_tracker']->retry_in = $retryIn;
                    } else {
                        $keys[$i]['torrent_tracker']->retry_in--;
                    }
                    Tracker::find($keys[$i]['torrent_tracker']->tracker)->failure++;
                    $processBar->setMessage($fatal);
                    $processBar->advance();
                    $keys[$i]['torrent_tracker']->interval *= 2;
                    return false;
                }
                if ($warning = Arr::get($response, 'warning message')) {
                    $processBar->setMessage($warning);
                }
                if ($trackerId = Arr::get($response, 'tracker id')) {
                    $keys[$i]['torrent_tracker']->tracker_id = $trackerId;
                }
                //min interval
                if ($interval = (int)Arr::get($response, 'interval')) {
                    $keys[$i]['torrent_tracker']->interval = $interval;
                }
                if ($peers = Arr::get($response, 'peers')) {
                    if (is_string($peers) && strlen($peers) % 6 == 0 && $peers = str_split($peers, 6)) foreach ($peers as $peer) {
                        $ip = Util::intToIp(Util::decodeInt(substr($peer, 0, 4)));
                        $port = Util::decodeShort(substr($peer, 4, 2));
                        new TorrentPeer($keys[$i]['torrent_tracker']->info_hash . ':' . $ip . ':' . $port);
                    }
                }
                $processBar->advance();
                return true;
            },
            'rejected' => function ($reason, $i) use ($keys, $processBar) {
                $processBar->setMessage($reason->getResponse() ?
                    $reason->getResponse()->getStatusCode() . ' ' . $reason->getResponse()->getReasonPhrase() :
                    $reason->getMessage());
                $processBar->setMessage(parse_url($keys[$i]['url'])['host'], 'url');
                $processBar->advance();
                $keys[$i]['torrent_tracker']->status = false;
                Tracker::find($keys[$i]['torrent_tracker']->tracker)->failure++;
            },
        ]);

        // Initiate the transfers and create a promise
        $promise = $pool->promise();

        // Force the pool of requests to complete.
        $promise->wait();
        $processBar->clear();
    }


    public static function scrape()
    {
    }
}