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
    protected $slack;

    protected $convoCache = [];

    public function __construct($token)
    {
        $this->token = $token;
        $this->slack = new SlackWrapi($this->token);
    }

    public function enrichChannelData(&$data)
    {
      $slack = $this->slack;
      if(! empty($data['channel'])) {
          $cachKey = 'channel-' . $data['channel'];
          if(Cache::has($cachKey)) {
            $data['conversation'] = Cache::get($cachKey);;
          } else {
            $convo = $slack->conversations->info(array("channel" => $data['channel']));
            if( isset($convo['ok'])) {
              $isIm = $convo['channel']['is_im'] ?? FALSE;
              if($isIm) {
                $withUser = $this->getUserData($convo['channel']['user']);
                $convo['with_user'] = $withUser;
              }

              $data['conversation'] = $convo;
            }

            Cache::put($cachKey, $convo, now()->addMinutes(2));
          }
      }

      $data['text'] = $this->prettifyChannelIds($data['text']);
      
    }

    public function getUserData($userId)
    {
      $slack = $this->slack;
      $cachKey = 'user-' . $userId;
      $data = null;
      if(Cache::has($cachKey)) {
        $data = Cache::get($cachKey);;
      } else {
        $user = $slack->users->info(array("user" => $userId));
        if( isset($user['ok'])) {
          $data = $user;
          Cache::put($cachKey, $data, now()->addMinutes(2));
        }   
      }
      return $data;
    }

    public function enrichUserData(&$data)
    { 
      if(! empty($data['user'])) {
          $data['user_info'] = $this->getUserData($data['user']);
      }
      
      $data['text'] = $this->prettifyUserIds($data['text']);
      
    }

    public function prettifyChannelIds($text)
    {
      if(preg_match_all('/<#(C\w+\|[\w_-]+)>/', $text, $matches)) {
        foreach($matches[0] as $index => $match) {
          if(preg_match("/(.*)\|([\w_-]+)/", $match, $parts)) {
            $text = str_replace($matches[0][$index], "#" . $parts[2], $text);
          }
        }
      }

      return $text;
    }

    public function prettifyUserIds($text)
    {
      if(preg_match_all('/<@(U\w+)>/', $text, $matches)) {
        foreach($matches[1] as $index => $match) {
          $userData = $this->getUserData($match);
          if(isset($userData['user']['real_name'])) {
            $text = str_replace($matches[0][$index], $userData['user']['real_name'], $text);
          }
        }
      }

      return $text;
    }

    public function enrichAttachments(&$data) 
    {
      $attachment_simplifications = [];
      if(isset($data['attachments'])) {
        foreach($data['attachments'] as $index => $attachment) {
          if(isset($data['attachments'][$index]['text'])) {
            $text = $data['attachments'][$index]['text'];
            $text = $this->prettifyUserIds($text);
            $text = $this->prettifyChannelIds($text);
            $attachment_simplifications[] = $text;
          }
        }
      }

      if(count($attachment_simplifications) > 0) {
        $data['attachment_simplifications'] = $attachment_simplifications;
      }
    }

    public function enrichSlackData(&$data) 
    {
        $this->enrichChannelData($data);
        $this->enrichUserData($data);
        $this->enrichAttachments($data);
    }

    public function getItemByChannelTS($channelId, $TS)
    {
      $slack = $this->slack;
      $cachKey = 'items-' . $channelId . '-' . $TS;
      $data = null;
      if(Cache::has($cachKey)) {
        $data = Cache::get($cachKey);;
      } else {
        $item = $slack->conversations->history(array("channel" => $channelId, "latest" => $TS, "limit" => 1, "inclusive" => true));
        if($item['ok'] == 1 && isset($item['messages'][0])) {
          $data = $item['messages'][0];
          Cache::put($cachKey, $data, now()->addMinutes(2));
        }
      }

      return $data;
    }

    public function tail(callable $messageFunction, callable $reactionAdded = null)
    {

        $loop = ReactEventLoopFactory::create();

        $client = new SlackRealTimeClient($loop);
        $client->setToken($this->token);
        $client->connect();

        $client->on('message', function ($data) use ($client, $messageFunction) {
            $this->enrichSlackData($data);
            if(isset($data['message']) && is_array($data['message'])) {
              $message_data = $data['message'];
              $this->enrichSlackData($message_data);
              $data['message_data'] = $message_data;
            }

            $messageFunction($data);        
        });

        $client->on('reaction_added', function ($data) use ($reactionAdded) {
          if($reactionAdded) {
          
            if(isset($data['user'])) {
              $this->enrichUserData($data);
            }

            if(isset($data['item']['channel'])) {
              $data['channel'] = $data['item']['channel'];
              $this->enrichChannelData($data);
            }

            $data['reaction_item'] = $this->getItemByChannelTS($data['item']['channel'], $data['item']['ts']);

            $reactionAdded($data);
          }
        });

        $loop->run();
    }
}
