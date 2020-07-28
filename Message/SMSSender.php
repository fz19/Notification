<?php
namespace App\Libraries\Message;

class SMSSender extends MessageSender
{
    const TYPE = 'sms';
    const REST_URI_SEND = 'https://apis.aligo.in/send/';

    public function __construct($config)
    {
        $this->ALIGO_SMS_KEY = $config->ALIGO_SMS_KEY;
        $this->ALIGO_USER_ID = $config->ALIGO_USER_ID;
        $this->ALIGO_DEFAULT_SENDER = $config->ALIGO_DEFAULT_SENDER;

        if (!$this->ALIGO_SMS_KEY || !$this->ALIGO_USER_ID)
            throw new MessageException("invalid ALIGO_SMS_KEY or ALIGO_USER_ID");
    }

    public static function createMessage($text, $subject=NULL, $from=NULL)
    {
        if (!is_string($text) || strlen($text) > 2000)
            throw new \InvalidArgumentException('invalid message text');

        $msg = new Message(SMSSender::TYPE);
        $msg->from = $from;
        $msg->subject = $subject;
        $msg->text = $text;
        return $msg;
    }

    // @override
    public function send()
    {
        $result = FALSE;

        foreach ($this->queue as $msg)
        {
            $from = $msg->from ? $msg->from : $this->ALIGO_DEFAULT_SENDER;
            $list = !is_array($msg->to) ? [$msg->to] : $msg->to;

            while(count($list))
            {
                $receiveList = array_slice($list, 0, 1000);
                $list = array_slice($list, 1000);

                $url = self::REST_URI_SEND;
                $post = array(
                        'key' => $this->ALIGO_SMS_KEY,
                        'user_id' => $this->ALIGO_USER_ID,
                        'sender' => $from,
                        'receiver' => implode(',', $receiveList),
                        'msg' => $msg->text,
                        'title' => $msg->subject,
                        'destination' => '',
                        'rdate' => '',
                        'rtime' => '',
                        'image' => '',
                        'testmode_yn' => ''
                );

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                $response = curl_exec($ch);
                curl_close($ch);

                $result = $this->_parse_result($response) || $result;
            };
        }

        return $result;
    }

    private function _parse_result($response)
    {
        $this->saveResult(self::TYPE . ' ' . $response);

        $obj = json_decode($response, true);
        if (!$obj)
            return FALSE;

        return $obj['result_code'] == '1';
    }
}