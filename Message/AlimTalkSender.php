<?php
namespace App\Libraries\Message;

class AlimTalkSender extends MessageSender
{
    const TYPE = 'alimtalk';
    const REST_URI_CREATE_TOKEN = 'https://kakaoapi.aligo.in/akv10/token/create/2/h/';
    const REST_URI_AUTH_PLUS_FRIEND = 'https://kakaoapi.aligo.in/akv10/profile/auth/';
    const REST_URI_SEND = 'https://kakaoapi.aligo.in/akv10/alimtalk/send/';

    private $token;
    private $isAuth = false;

    public function __construct($config)
    {
        $this->ALIGO_KAKAO_KEY = $config->ALIGO_KAKAO_KEY;
        $this->ALIGO_KAKAO_USER_ID = $config->ALIGO_KAKAO_USER_ID;
        $this->ALIGO_KAKAO_SENDER_KEY = $config->ALIGO_KAKAO_SENDER_KEY;
        $this->ALIGO_KAKAO_DEFAULT_SENDER = $config->ALIGO_KAKAO_DEFAULT_SENDER;
        $this->ALIGO_KAKAO_PLUS_ID = $config->ALIGO_KAKAO_PLUS_ID;

        if (!$this->ALIGO_KAKAO_KEY || !$this->ALIGO_KAKAO_SENDER_KEY)
            throw new MessageException("invalid ALIGO_KAKAO_KEY or ALIGO_KAKAO_SENDER_KEY");
    }

    public static function createMessage($template, $subject, $text, $button)
    {
        if (!is_string($text) || !$template)
            throw new \InvalidArgumentException('invalid message text or template');

        $msg = new Message(AlimTalkSender::TYPE);
        $msg->template = $template;
        $msg->subject = $subject;
        $msg->text = $text;
        $msg->button = $button;

        return $msg;
    }

    public function generateToken() {
        $url = self::REST_URI_CREATE_TOKEN;
        $post = array(
            'apikey' => $this->ALIGO_KAKAO_KEY,
            'userid' => $this->ALIGO_KAKAO_USER_ID
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        if (!$result || $result['code'] != 0)
            throw new MessageException('Create Token Fail. ' . $result['message']);

        $this->token = $result['token'];
    }

    public function setToken(string $token)
    {
        $this->token = $token;
    }

    public function getToken() : string
    {
        return $this->token;
    }

    public function authPlusFriend() {
        if (empty($this->token))
            $this->generateToken();

        $url = self::REST_URI_CREATE_TOKEN;
        $post = array(
            'apikey' => $this->ALIGO_KAKAO_KEY,
            'userid' => $this->ALIGO_KAKAO_USER_ID,
            'token' => $this->token,
            'plusid' => $this->ALIGO_KAKAO_PLUS_ID,
            'phonenumber' => $this->ALIGO_KAKAO_DEFAULT_SENDER,
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        if (!$result || $result['code'] != 0)
            throw new MessageException('Auth Fail. ' . $result['message']);

        $this->isAuth = true;
    }

    // @override
    public function send()
    {
        if (empty($this->token))
            $this->generateToken();

        $result = FALSE;
        foreach ($this->queue as $msg)
        {
            $list = !is_array($msg->to) ? [$msg->to] : $msg->to;

            do
            {
                $post = array(
                    'apikey' => $this->ALIGO_KAKAO_KEY,
                    'userid' => $this->ALIGO_KAKAO_USER_ID,
                    'token' => $this->token,
                    'senderkey' => $this->ALIGO_KAKAO_SENDER_KEY,
                    'tpl_code' => $msg->template,
                    'sender' => $this->ALIGO_KAKAO_DEFAULT_SENDER
                );

                $receiveList = array_slice($list, 0, 500);
                $receiveCount = count($receiveList);
                $list = array_slice($list, 500);

                for ($i=0; $i<$receiveCount; $i++) {
                    $n = $i + 1;
                    $post['receiver_'.$n] = $receiveList[$i];
                    $post['subject_'.$n] = $msg->subject;
                    $post['message_'.$n] = $msg->text;
                    $post['button_'.$n] = $msg->button;
                }

                $url = self::REST_URI_SEND;

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                $response = curl_exec($ch);
                curl_close($ch);

                $result = $this->_parse_result($response) || $result;
            } while(count($list));
        }

        return $result;
    }

    private function _parse_result($response)
    {
        $this->saveResult(self::TYPE . ' ' . $response);

        $obj = json_decode($response, true);
        if (!$obj)
            return FALSE;

        return $obj['code'] === 0;
    }
}