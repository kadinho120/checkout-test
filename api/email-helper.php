<?php

class SimpleSMTP
{
    private $host;
    private $port;
    private $username;
    private $password;
    private $conn;
    private $debug = false;

    public function __construct($host, $port, $username, $password)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    public function send($to, $subject, $body, $fromName = 'Suporte')
    {
        try {
            $this->connect();
            $this->auth();
            $this->sendMail($to, $subject, $body, $fromName);
            $this->quit();
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function connect()
    {
        $socket = ($this->port == 465) ? "ssl://{$this->host}" : "tcp://{$this->host}";
        $this->conn = fsockopen($socket, $this->port, $errno, $errstr, 15);

        if (!$this->conn) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }

        $this->getResponse(); // Banner
        $this->sendCommand("EHLO " . $_SERVER['SERVER_NAME']);
    }

    private function auth()
    {
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->username));
        $this->sendCommand(base64_encode($this->password));
    }

    private function sendMail($to, $subject, $body, $fromName)
    {
        $this->sendCommand("MAIL FROM: <{$this->username}>");
        $this->sendCommand("RCPT TO: <$to>");
        $this->sendCommand("DATA");

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $fromName <{$this->username}>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";

        $data = "$headers\r\n$body\r\n.";

        $this->sendCommand($data);
    }

    private function quit()
    {
        $this->sendCommand("QUIT");
        fclose($this->conn);
    }

    private function sendCommand($cmd)
    {
        fputs($this->conn, $cmd . "\r\n");
        $response = $this->getResponse();

        // Basic error check (response codes 4xx or 5xx)
        if (substr($response, 0, 1) == '4' || substr($response, 0, 1) == '5') {
            throw new Exception("SMTP Error: $response");
        }
        return $response;
    }

    private function getResponse()
    {
        $response = "";
        while ($str = fgets($this->conn, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ")
                break;
        }
        return $response;
    }
}

function sendOrderEmail($to, $subject, $body)
{
    if (empty($to) || empty($subject) || empty($body)) {
        return ['success' => false, 'error' => 'Missing email fields'];
    }

    // Hardcoded credentials as requested
    $host = 'smtp.hostinger.com';
    $port = 465;
    $username = 'suporte@instaboost.com.br';
    $password = 'Houshiengi22@';

    $smtp = new SimpleSMTP($host, $port, $username, $password);
    return $smtp->send($to, $subject, $body, 'InstaBoost Suporte');
}
?>