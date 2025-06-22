<?php
namespace App\Presentation\Sms;

use App\Common\Maybe;

use DateTime;
use DateTimeZone;
use Nette;
use Nette\Application\UI\Form;


use Tracy\Debugger;

final class SmsPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private Nette\Database\Explorer $database
    ) {
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->setLayout('smsLayout');
    }


    public function renderDefault(): void
    {
        // Make session data available to the Latte template
        $messages = $this->database
            ->table('message')
            ->order('id DESC')
            ->fetchAll();

        $this->template->messages = $messages;
    }

    protected function createComponentSmsGatewayForm(): Form
    {
        $form = new Form;
        $form->addText('url', 'SMS Gateway URL:');
        $form->addText('token', 'API Token:');
        $form->addSubmit('send', 'Save to Session');

        $form->onSuccess[] = [$this, 'smsGatewayFormSucceeded'];

        // Try to pre-fill the form from session data
        $data = $this->getSmsGatewaySessionData();
        if ($data->getIterator()->count() > 0) {
            $form->setDefaults(["url" => $data["url"], "token" => $data["token"]]);
        }

        return $form;
    }
    public function smsGatewayFormSucceeded(Form $form, array $data): void
    {
        // Get the session section specifically for SMS gateway data
        $sessionSection = $this->getSmsGatewaySessionData();

        // Store the 'url' and 'token' in the session section
        $sessionSection->set('url', $data['url']);
        $sessionSection->set('token', $data['token']);

        // Alternatively, store the entire data array at once:
        // TODO verify whether this works, seems dubious
        // $sessionSection->set(null, $data);

        $this->flashMessage('SMS Gateway settings successfully saved to session!', 'gateway_success');
        $this->redirect('this'); // Redirect to refresh the page and display updates
    }

    private function getSmsGatewaySessionData(): Nette\Http\SessionSection
    {
        return $this->getSession('smsGatewayData');
    }

    protected function createComponentMessageForm(): Form
    {
        $form = new Form;
        $form->addText('number', 'Number:')
            ->setRequired('Please enter the number.');
        $form->addText('text', 'SMS Text:')
            ->setRequired('Please enter the text.');
        $form->addSubmit('create', 'Create');

        $form->onSuccess[] = [$this, 'messageFormSucceeded'];

        return $form;
    }
    public function messageFormSucceeded(Form $form, array $data): void
    {
        $this->database->table('message')->insert([
            'toNumber' => $data['number'],
            'text' => $data['text'],
            'status' => 'new',
        ]);

        $this->flashMessage('SMS successfully created!', 'sms_success');
        $this->redirect('this'); // Redirect to refresh the page and display updates
    }

    // ########################################################
    //                     SEND SMS
    // ########################################################

    public function handleSend($id): void
    {
        $TEST = false;

        $message = $this->database->table('message')->get($id);
        if (!$message) {
            $this->error('Message not found.');
        }

        Debugger::log("Sending SMS: " . $message->text);

        $postData = json_encode([
            "to" => $message->toNumber,
            "text" => $message->text,
            "encoding" => "unicode",
            "flash" => false,
            "validity" => "max",
            // "send_after" => "08:00",
            // "send_before" => "21:00",
            "test" => $TEST
        ]);

        $result = $this->requestToSmsGateway('sms_single', $postData);
        if (!$result->isSuccess) {
            $this->flashMessage('SMS failed to send! Error: ' . $result->error, 'sms_error');
            $this->redirect('this'); // Redirect to refresh the page and display updates
            return;
        }

        $response = $result->value[0];

        $this->database->query('UPDATE message SET', [
            'status' => 'sent',
            'gw_id' => $response['id'],
            'gw_send_status' => $response['status'],
        ], 'WHERE id = ?', $id);

        $this->flashMessage('SMS sent successfully!', 'sms_success');
        $this->redirect('this'); // Redirect to refresh the page and display updates
    }

    // ########################################################
    //                     CHECK SMS
    // ########################################################

    public function handleCheck($id): void
    {
        $message = $this->database->table('message')->get($id);
        if (!$message) {
            $this->error('Message not found.');
        }
        Debugger::log("Checking SMS: " . $message->text);

        $gwId = $message->gw_id;

        $result = $this->requestToSmsGateway('sent?id_from=' . $gwId . '&id_to=' . $gwId, null);
        if (!$result->isSuccess) {
            $this->flashMessage('SMS failed to send! Error: ' . $result->error, 'sms_error');
            $this->redirect('this'); // Redirect to refresh the page and display updates
            return;
        }

        $response = $result->value;
        if (sizeof($response) !== 1) {
            Debugger::log("Wrong number of responses: " . json_encode($response), Debugger::ERROR);

            $this->flashMessage('SMS check failed! Expected 1 response, but got: ' . sizeof($response), 'sms_error');
            $this->redirect('this'); // Redirect to refresh the page and display updates
            return;
        }

        $response = $result->value[0];

        $this->database->query('UPDATE message SET', [
            'gw_check_status' => $response['status'],
            'gw_error_code' => $response['error_code'],
            'gw_send_date' => $this->parseDate($response['sending_date']),
            'gw_delivery_date' => $this->parseDate($response['delivery_date']),
        ], 'WHERE id = ?', $id);

        $this->flashMessage('SMS checked successfully!', 'sms_success');
        $this->redirect('this'); // Redirect to refresh the page and display updates
    }

    private function parseDate($str): DateTime
    {
        $date = new DateTime($str);
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date;
    }

    // ########################################################
    //                     Request to SMS Gateway
    // ########################################################


    function requestToSmsGateway($urlPath, $postData = null): Maybe
    {
        $gwData = $this->getSmsGatewaySessionData();
        if ($gwData->getIterator()->count() == 0) {
            // TODO handle this better
            return Maybe::error('SMS Gateway settings not found.');
        }

        $url = $gwData->url;
        if (!str_ends_with($url, '/'))
            $url .= '/';
        $url .= 'index.php/api/v2/messages/' . $urlPath;
        $accessToken = $gwData->token;

        Debugger::log("Curl request to: " . $url);

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
                return Maybe::error('SMS failed to send! Error: ' . $error);
            }

            // Get HTTP status code
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            Debugger::log("HTTP Status Code: " . $httpCode, Debugger::DEBUG);

            // You can further process the response, e.g., decode JSON
            $responseData = json_decode($response, true); // true to get an associative array
            if (!$responseData) {
                return Maybe::error('SMS failed to send! No response data.');
            }

            return Maybe::success($responseData);

        } finally {
            // Close cURL session
            curl_close($ch);
        }
    }


}