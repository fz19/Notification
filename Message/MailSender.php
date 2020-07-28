<?php
namespace App\Libraries\Message;

use \App\Config\Service;

class MailSender extends MessageSender
{
    const TYPE = 'mail';
    const REST_URI_SEND = 'https://api-mail.cloud.toast.com/email/v1.6/appKeys/{appKey}/sender/eachMail';

    public function __construct($config)
    {
        $this->MAIL_DEFAULT_SENDER_MAIL = $config->MAIL_DEFAULT_SENDER_MAIL;
        $this->MAIL_DEFAULT_SENDER_NAME = $config->MAIL_DEFAULT_SENDER_NAME;
        $this->API_TOAST_ACCESS_KEY = $config->API_TOAST_ACCESS_KEY;
        $this->API_TOAST_SECRET = $config->API_TOAST_SECRET;
        $this->API_TOAST_NOTIFICATION_MAIL_APPKEY = $config->API_TOAST_NOTIFICATION_MAIL_APPKEY;

        $this->email = \Config\Services::email();
    }

    public static function createMessage($subject, $text, $contentType='html')
    {
        if ($contentType != 'text' && $contentType != 'html')
            throw new \InvalidArgumentException("contentType must be 'text' or 'html'");

        $msg = new Message(MailSender::TYPE);
        // $msg->from = null;
        $msg->subject = $subject;
        $msg->text = $text;
        $msg->contentType = $contentType;
        return $msg;
    }

    // @override
    public function send()
    {
        $result = FALSE;

        foreach ($this->queue as $msg)
        {
            $url = str_replace('{appKey}', $this->API_TOAST_NOTIFICATION_MAIL_APPKEY, self::REST_URI_SEND);
            $list = !is_array($msg->to) ? [$msg->to] : $msg->to;

            while(count($list))
            {
                $receiveList = array_slice($list, 0, 1000);
                $list = array_slice($list, 1000);

                $post = json_encode([
                    'senderAddress' => $this->MAIL_DEFAULT_SENDER_MAIL,
                    'senderName' => $this->MAIL_DEFAULT_SENDER_NAME,
                    'title' => $msg->subject,
                    'body' => $msg->text,
                    'receiverList' => array_map(function($to) {
                            return ['receiveMailAddr' => $to];
                        }, $receiveList)
                ], JSON_UNESCAPED_UNICODE);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
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

        return $obj['header']['resultCode'] == '0';
    }
}