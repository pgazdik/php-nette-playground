<?php
namespace App\Service;

use App\Common\Maybe;
use Tracy\Debugger;


class SmsGwService
{
    public function __construct(
        private string $smsGwUrl,
        private string $smsGwToken
    ) {}

    public function requestToSmsGateway($urlPath, $postData = null): Maybe
    {
        $url = $this->smsGwUrl;
        if (!str_ends_with($url, '/'))
            $url .= '/';
        $url .= 'index.php/api/v2/messages/' . $urlPath;
        $accessToken = $this->smsGwToken;

        //Debugger::log("Curl request to: " . $url);

        // Initialize cURL session
        $ch = curl_init();

        try {
            // Set the URL
            curl_setopt($ch, CURLOPT_URL, $url);

            $length = 0;
            if ($postData) {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                $length = strlen($postData);
            }


            // Set custom headers
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'access-token: ' . $accessToken,
                'Content-Length: ' . $length // It's good practice to set Content-Length for POST requests
            ]);

            // --insecure equivalent: Disable SSL certificate verification
            // IMPORTANT: Use CURLOPT_SSL_VERIFYPEER to false ONLY for development/testing
            // or if you fully understand the security implications.
            // In a production environment, you should properly verify SSL certificates.
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Also often needed with CURLOPT_SSL_VERIFYPEER for self-signed or invalid certs

            // Return the response as a string instead of echoing it
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


            // Execute the cURL request
            $response = curl_exec($ch);

            Debugger::log("Curl response: " . $response);

            // Check for cURL errors
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                Debugger::log("Curl error: " . $error, Debugger::ERROR);
                return Maybe::error('MMS failed to send! Error: ' . $error);
            }

            // Get HTTP status code
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            Debugger::log("HTTP Status Code: " . $httpCode, Debugger::DEBUG);

            // You can further process the response, e.g., decode JSON
            //$responseData = json_decode($response, true); // true to get an associative array
            $responseData = json_decode($response);
            if (!$responseData) {
                return Maybe::error('MMS failed to send! No response data.');
            }

            return Maybe::success($responseData);

        } finally {
            // Close cURL session
            curl_close($ch);
        }
    }
}
