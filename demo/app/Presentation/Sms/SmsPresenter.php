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
            // we select everything except img_content, which is a BLOB
            ->select(
                'id, text, toNumber, status, ' .
                'img_name, img_type, ' .
                'gw_id, gw_send_status, gw_check_status, gw_error_code, gw_send_date, gw_delivery_date, ' .
                'created_at, updated_at'
            )
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
        $form->addText('text', 'MMS Text:')
            ->setRequired('Please enter the text.');
        $form->addUpload('image', 'Image:')
            ->addRule($form::MimeType, 'Image must be JPEG or PNG.', ['image/jpeg', 'image/png'])
            ->addRule($form::MaxFileSize, 'Maximum size is 1 MB.', 1024 * 1024);
        $form->addSubmit('create', 'Create');

        $form->onSuccess[] = [$this, 'messageFormSucceeded'];

        return $form;
    }
    public function messageFormSucceeded(Form $form, array $data): void
    {
        $number = $data['number'];
        $text = $data['text'];
        $image = $data['image'];

        $imageName = null;
        $imageType = null;
        $imageContent = null;

        if ($image->hasFile()) {
            if (!$image->isOk()) {
                $error = $image->getError();

                $phpFileUploadErrors = array(
                    0 => 'There is no error, the file uploaded with success',
                    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                    3 => 'The uploaded file was only partially uploaded',
                    4 => 'No file was uploaded',
                    6 => 'Missing a temporary folder',
                    7 => 'Failed to write file to disk.',
                    8 => 'A PHP extension stopped the file upload.',
                );

                $this->flashMessage('Image upload failed: ' . $phpFileUploadErrors[$error], 'msg_error');
                $this->redirect('this');
                return;
            }

            // $imageName = $image->getSanitizedName();
            $imageName = $this->sanitizeName($image->getUntrustedName());
            $imageType = $image->getContentType();
            $imageContent = $image->getContents();
        }

        $this->database->table('message')->insert([
            'toNumber' => $number,
            'text' => $text,
            'img_name' => $imageName,
            'img_type' => $imageType,
            'img_content' => $imageContent,
            'status' => 'new',
        ]);

        $this->flashMessage('MMS successfully created!', 'msg_success');
        $this->redirect('this'); // Redirect to refresh the page and display updates
    }

    private function sanitizeName(string $name): string
    {
        // remove everything except letters, numbers, _, -, (, ), ., '
        return preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚčďěňóřšťůýžČĎĚŇÓŘŠŤŮÝŽ_\-\(\)\.\']/u', '', $name);
    }

    // ########################################################
    //                     SEND MMS
    // ########################################################

    public function handleSend($id): void
    {
        $TEST = false;

        $message = $this->database->table('message')->get($id);
        if (!$message) {
            $this->error('Message not found.');
        }

        Debugger::log("Sending MMS: " . $message->text .
            ($message->img_name ? " with image: " . $message->img_name . " (" . $message->img_type . ")" : ""));

        $attachements = [];
        if ($message->img_name) {
            $attachements[] = [
                "content_type" => $message->img_type,
                "content" => base64_encode($message->img_content),
            ];
        }

        $postData = json_encode([
            "to" => [$message->toNumber],
            "text" => $message->text,
            "encoding" => "unicode",
            "validity" => "max",
            // "send_after" => "08:00",
            // "send_before" => "21:00",
            "test" => $TEST,
            "attachments" => $attachements
        ]);

        $result = $this->requestToSmsGateway('mms', $postData);
        if (!$result->isSuccess) {
            $this->flashMessage('MMS failed to send! Error: ' . $result->error, 'msg_error');
            $this->redirect('this'); // Redirect to refresh the page and display updates
            return;
        }

        $response = $result->value[0];

        $this->database->query('UPDATE message SET', [
            'status' => 'sent',
            'gw_id' => $response->id,
            'gw_send_status' => $response->status,
        ], 'WHERE id = ?', $id);

        $this->flashMessage('MMS sent successfully!', 'msg_success');
        $this->redirect('this'); // Redirect to refresh the page and display updates
    }

    // ########################################################
    //                     CHECK MMS
    // ########################################################

    public function handleCheck($id): void
    {
        $message = $this->database->table('message')->get($id);
        if (!$message) {
            $this->error('Message not found.');
        }
        Debugger::log("Checking MMS: " . $message->text);

        $gwId = $message->gw_id;

        $result = $this->requestToSmsGateway('sent?id_from=' . $gwId . '&id_to=' . $gwId, null);
        if (!$result->isSuccess) {
            $this->flashMessage('MMS failed to send! Error: ' . $result->error, 'msg_error');
            $this->redirect('this'); // Redirect to refresh the page and display updates
            return;
        }

        $response = $result->value;

        // if the message is not yet recognized by the SMS GW, the response JSON is {"message":"Resource(s) not found"}
        if (!is_array($response)) {
            if (property_exists($response, 'message') && str_contains($response->message, 'not found')) {
                $this->flashMessage('MMS not found yet, try again later!', 'msg_error');
                $this->redirect('this'); // Redirect to refresh the page and display updates
            } else {
                $msg = "Unexpected response: " . json_encode($response);
                Debugger::log($msg, Debugger::ERROR);
                $this->flashMessage('MMS check failed! ' . $msg, 'msg_error');
                $this->redirect('this'); // Redirect to refresh the page and display updates
            }
            return;
        }

        if (sizeof($response) !== 1) {
            Debugger::log("Wrong number of responses: " . json_encode($response), Debugger::ERROR);

            $this->flashMessage('MMS check failed! Expected 1 response, but got: ' . sizeof($response), 'msg_error');
            $this->redirect('this'); // Redirect to refresh the page and display updates
            return;
        }

        $response = $response[0];

        $this->database->query('UPDATE message SET', [
            'gw_check_status' => $response->status,
            'gw_error_code' => $response->error_code,
            'gw_send_date' => $this->parseDate($response->sending_date),
            'gw_delivery_date' => $this->parseDate($response->delivery_date),
        ], 'WHERE id = ?', $id);

        $this->flashMessage('MMS checked successfully!', 'msg_success');
        $this->redirect('this'); // Redirect to refresh the page and display updates
    }

    private function parseDate($str): DateTime|null
    {
        if ($str === null)
            return null;

        $date = new DateTime($str);
        $date->setTimezone(new DateTimeZone('UTC'));
        return $date;
    }

    // ########################################################
    //                     Delete MMS
    // ########################################################

    public function handleDelete($id): void
    {
        $rows = $this->database->table('message')->get($id)->delete();

        if ($rows === 1) {
            $this->flashMessage('MMS deleted successfully!', 'msg_success');
            $this->redirect('this'); // Redirect to refresh the page and display updates
        } else {
            $this->flashMessage('MMS not found!', 'msg_error');
            $this->redirect('this'); // Redirect to refresh the page and display updates
        }
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
                return Maybe::error('MMS failed to send! Error: ' . $error);
            }

            // Get HTTP status code
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            Debugger::log("HTTP Status Code: " . $httpCode, Debugger::DEBUG);

            // You can further process the response, e.g., decode JSON
            //$responseData = json_decode($response, true); // true to get an associative array
            $responseData = json_decode($response); // true to get an associative array
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