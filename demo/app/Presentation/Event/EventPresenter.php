<?php
namespace App\Presentation\Event;

use App\Model\Entity\Event;
use App\Service\EventService;
use Nette;
use Nette\Application\UI\Form;

final class EventPresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
        private EventService $eventService
    ) {
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        // reusing smsLayout, it's the normal layout without the flash messages, which are within the right panel
        $this->setLayout('smsLayout');
    }

    public function renderDefault(): void
    {
        $this->template->events = $this->eventService->getAll();
    }

    protected function createComponentSmsGatewayForm(): Form
    {
        $form = new Form;
        $form->addText('url', 'SMS Gateway URL:');
        $form->addText('token', 'API Token:');
        $form->addSubmit('send', 'Save to Session');

        $form->onSuccess[] = [$this, 'smsGatewayFormSucceeded'];

        $data = $this->getSmsGatewaySessionData();
        if ($data->getIterator()->count() > 0) {
            $form->setDefaults(["url" => $data["url"], "token" => $data["token"]]);
        }

        return $form;
    }

    public function smsGatewayFormSucceeded(Form $form, array $data): void
    {
        $sessionSection = $this->getSmsGatewaySessionData();
        $sessionSection->set('url', $data['url']);
        $sessionSection->set('token', $data['token']);

        $this->flashMessage('SMS Gateway settings successfully saved to session!', 'gateway_success');
        $this->redirect('this');
    }

    private function getSmsGatewaySessionData(): Nette\Http\SessionSection
    {
        return $this->getSession('smsGatewayData');
    }

    protected function createComponentEventForm(): Form
    {
        $form = new Form;
        $form->addText('patientName', 'Patient Name:')
            ->setRequired('Please enter the patient name.');

        $form->addText('phoneNumber', 'Phone Number:')
            ->setRequired('Please enter the phone number.');

        $form->addText('doctorName', 'Doctor Name:')
            ->setRequired('Please enter the doctor name.');

        $form->addTextArea('doctorAddress', 'Doctor Address:')
            ->setRequired('Please enter the doctor address.');

        $form->addText('appointmentDate', 'Appointment Date:')
            ->setHtmlAttribute('type', 'datetime-local')
            ->setRequired('Please enter the appointment date.');

        $form->addUpload('attachment', 'Attachment (Image):')
            ->addRule($form::MimeType, 'Image must be JPEG or PNG.', ['image/jpeg', 'image/png'])
            ->addRule($form::MaxFileSize, 'Maximum size is 1 MB.', 1024 * 1024);

        $form->addSubmit('create', 'Create Event');

        $form->onSuccess[] = [$this, 'eventFormSucceeded'];

        return $form;
    }

    public function eventFormSucceeded(Form $form, array $data): void
    {
        $attachment = $data['attachment'];
        $attachmentName = null;
        $attachmentType = null;
        $attachmentContent = null;

        if ($attachment->hasFile()) {
            if (!$attachment->isOk()) {
                $this->flashMessage('Attachment upload failed.', 'msg_error');
                $this->redirect('this');
                return;
            }
            $attachmentName = $attachment->getUntrustedName(); // Simple for now, maybe sanitize like SmsPresenter
            $attachmentType = $attachment->getContentType();
            $attachmentContent = $attachment->getContents();
        }

        $event = new Event(
            patientName: $data['patientName'],
            phoneNumber: $data['phoneNumber'],
            doctorName: $data['doctorName'],
            doctorAddress: $data['doctorAddress'],
            appointmentDate: (new \DateTime($data['appointmentDate']))->setTimezone(new \DateTimeZone('UTC')),
            attachmentContent: $attachmentContent,
            attachmentName: $attachmentName,
            attachmentType: $attachmentType
        );

        $this->eventService->create($event);

        $this->flashMessage('Event successfully created!', 'msg_success');
        $this->redirect('this');
    }
}
