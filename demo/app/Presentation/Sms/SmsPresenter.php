<?php
namespace App\Presentation\Sms;

use Nette;
use Nette\Application\UI\Form;

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
            'number' => $data['number'],
            'text' => $data['text'],
            'status' => 'new',
        ]);

        $this->flashMessage('SMS successfully sent!', 'sms_success');
        $this->redirect('this'); // Redirect to refresh the page and display updates
    }
}