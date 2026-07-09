<?php
// includes/resend.php
class Resend {
    private $apiKey;

    public function __construct() {
        $this->apiKey = $_ENV['RESEND_API_KEY'] ?? getenv('RESEND_API_KEY');
    }

    public function send($to, $subject, $html, $fromName = "KINAS GROUP") {
        $data = [
            "from" => "$fromName <noreply@kinas-group.com>",
            "to" => $to,
            "subject" => $subject,
            "html" => $html
        ];

        $ch = curl_init("https://api.resend.com/emails");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->apiKey,
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
