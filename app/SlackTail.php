<?php
namespace App;

use React\EventLoop\Factory as ReactEventLoopFactory;
use Slack\RealTimeClient as SlackRealTimeClient;
use Slack\ApiClient as ApiClient;
use wrapi\slack\slack as SlackWrapi;
use Illuminate\Support\Facades\Cache;

class SlackTail
{
    private $token;
    protected $regexFilters = [];

    protected $convoCache = [];

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function addRegexFilter($regex)
    {
        $this->regexFilters[] = $regex;
    }

    public function enrichSlackData(&$data) 
    {
        $slack = new SlackWrapi($this->token);
        if(! empty($data['channel'])) {
            $cachKey = 'channel-' . $data['channel'];
            if(Cache::has($cachKey)) {
              $data['conversation'] = Cache::get($cachKey);;
            } else {
              $convo = $slack->conversations->info(array("channel" => $data['channel']));
              if( isset($convo['ok'])) {
                $data['conversation'] = $convo;
              }

              Cache::put($cachKey, $convo, now()->addMinutes(2));
            }
        }
    }

    public function tail(callable $matchFunction, callable $nomatchFunction = null)
    {

        $loop = ReactEventLoopFactory::create();

        $client = new SlackRealTimeClient($loop);
        $client->setToken($this->token);
        $client->connect();

        $client->on('message', function ($data) use ($client, $matchFunction, $nomatchFunction) {
            echo "Incoming message: ".$data['text']."\n";
            $this->enrichSlackData($data);

            foreach($this->regexFilters as $regex) {
              if(preg_match($regex, $data['text'], $match)) {
                $matchFunction(['match' => $match,'data' => $data]);
              } else {
                if($nomatchFunction) {
                  $nomatchFunction(['match' => null, 'data' => $data]);
                }
              }
            }
        });

        $loop->run();
    }
}
