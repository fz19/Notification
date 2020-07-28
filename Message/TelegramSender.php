<?php
namespace App\Libraries\Message;

class TelegramMessage implements Message
{
    private $from;
    private $subject;
    private $text;

    public static function createMessage($text)
    {
        if (!defined('TELEGRAM_BOT_TOKEN'))
            throw new \Exception("not defined TELEGRAM_BOT_TOKEN");

        if (!is_string($text) || strlen($text) > 2000)
            throw new \InvalidArgumentException('invalid message text');

        $msg = new TelegramMessage();
        $msg->from = TELEGRAM_BOT_TOKEN;
        $msg->text = $text;

        return $msg;
    }

    private function __construct()
    {
    }

    // @override
    function __toString()
    {
        return spl_object_hash($this);
    }

    // @override
    public function getMessageType()
    {
        return TelegramMessageSender::TYPE;
    }

    // @override
    public function getSubject()
    {
        return $this->subject;
    }

    // @override
    public function getText()
    {
        return $this->text;
    }

    // @override
    public function getFrom()
    {
        return $this->from;
    }
}

class TelegramSender extends MessageSender
{
    const TYPE = 'telegram';
    const REST_BASE_URI = 'https://api.telegram.org/bot';
    const COMMAND_SEND_MESSAGE = '/sendMessage';

    public function __construct()
    {
    }

    // @override
    public function addQueue(Message $msg, $to)
    {
        if ( ! ($msg instanceof TelegramMessage) )
            throw new \InvalidArgumentException('is not TelegramMessage');

        parent::addQueue($msg, $to);
    }

    // @override
    public function send()
    {
        $server = ENVIRONMENT == 'development' ? '[테스트서버] ' : '';

        $result = FALSE;

        foreach ($this->queue as $item)
        {
            $msg = $item[0];
            $list = $item[1];

            foreach ($list as $to)
            {
                $url = self::REST_BASE_URI . $msg->getFrom() . self::COMMAND_SEND_MESSAGE;
                $post = array(
                    'parse_mode' => 'HTML',
                    'chat_id' => $to,
                    'text' => $server . $msg->getText()
                );

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                $response = curl_exec($ch);
                curl_close($ch);

                $result = $this->_parse_result($response) || $result;
            }
        }

        return $result;
    }

    private function _parse_result($response)
    {
        $this->saveResult(self::TYPE . ' ' . $response);

        $obj = json_decode($response, true);
        if (!$obj)
            return FALSE;

        return !!$obj['ok'];
    }
}