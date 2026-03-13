<?php
namespace App\Presentation\Event;

use App\Model\Entity\Event\Event;
use App\Service\EventRepository;
use App\Service\EventManager;
use App\Service\NotificationMsgRepository;
use App\Service\NotificationAttemptRepository;
use App\Presentation\BaseEventPresenter;
use App\Utils\DateUtils;
use App\Utils\MediaHandler;
use Nette\Application\UI\Form;

class EventPresenter extends BaseEventPresenter
{
    public function __construct(
        private EventRepository $eventRepository,
        private EventManager $eventManager,
        private NotificationMsgRepository $notificationMsgRepository,
        private NotificationAttemptRepository $notificationAttemptRepository,
        private MediaHandler $mediaHandler,
    ) {
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->setLayout('eventlayout');
    }

    public function renderDefault(): void
    {
        $this->template->events = $this->eventRepository->listAll();
    }

    protected function createComponentEventForm(): Form
    {
        $form = new Form;
        $form->addText('patientName', 'Patient Name:')
            ->setRequired('Please enter the patient name.');

        $form->addText('phoneNumber', 'Phone Number:')
            ->setRequired('Please enter the phone number.');

        $dirNames = $this->mediaHandler->listDirNames();
        $form->addSelect('doctorName', 'Doctor Name:', array_combine($dirNames, $dirNames))
            ->setPrompt('Select a doctor')
            ->setRequired('Please select a doctor.');

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
            appointmentDate: DateUtils::newBaDate($data['appointmentDate']),
            attachmentContent: $attachmentContent,
            attachmentName: $attachmentName,
            attachmentType: $attachmentType
        );

        $this->eventManager->createEvent($event);

        $this->flashMessage('Event successfully created!', 'msg_success');
        $this->redirect('this');
    }

    public function renderShow(int $id): void
    {
        $event = $this->eventRepository->getByIdWithImage($id);
        if (!$event) {
        $this->flashMessage('Event not found!', 'msg_error');
        $this->redirect('default');
        }

        $this->template->event = $event;
        $notifications = $this->notificationMsgRepository->getAllByEventId($id);
        $this->template->notifications = $notifications;

        $attempts = [];
        foreach ($notifications as $msg) {
            $attempts[$msg->id] = $this->notificationAttemptRepository->listLatestByMsgId($msg->id, 10);
        }
        $this->template->attempts = $attempts;
    }

}
