<?php
namespace App\Presentation\Event;

use App\Model\Entity\Event\Event;
use App\Model\Entity\Event\NotificationMsg;
use App\Model\Entity\Event\NotificationType;
use App\Model\Entity\Event\NotificationMsgStatus;
use App\Model\Entity\Event\MediaType;
use App\Service\EventRepository;
use App\Service\EventManager;
use App\Service\NotificationMsgRepository;
use App\Service\NotificationAttemptRepository;
use App\Presentation\BaseEventPresenter;
use App\Utils\DateUtils;
use App\Utils\EventAwareLogger;
use App\Utils\MediaHandler;
use Nette\Application\UI\Form;
use Tracy\Debugger;

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

        $form->addSubmit('create', 'Create Event');

        $form->onSuccess[] = [$this, 'eventFormSucceeded'];

        return $form;
    }

    public function eventFormSucceeded(Form $form, array $data): void
    {
        $event = new Event(
            patientName: $data['patientName'],
            phoneNumber: $data['phoneNumber'],
            doctorName: $data['doctorName'],
            doctorAddress: $data['doctorAddress'],
            appointmentDate: DateUtils::newBaDate($data['appointmentDate']),
        );

        $result = $this->eventManager->createEvent($event);

        if (!$result->isSuccess) {
            $this->flashMessage($result->error, 'msg_error');

        } else if ($result->warning) {
            $this->flashMessage($result->warning, 'msg_warning');
        } else {
            $this->flashMessage('Event successfully created!', 'msg_success');
        }

        $this->redirect('this');
    }

    public function renderShow(int $id): void
    {
        EventAwareLogger::setEventId($id);

        $event = $this->eventRepository->getById($id);
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

        $this->template->unusedImages = $this->eventManager->identifyUnusedImages($event, $notifications);
    }

    // TODO test
    public function handleUpdateImageNotifications(int $id): void
    {
        EventAwareLogger::setEventId($id);

        $toDelete = $this->getHttpRequest()->getPost('delete') ?: [];
        $toAdd = $this->getHttpRequest()->getPost('add') ?: [];

        $event = $this->eventRepository->getById($id);
        $mainMsg = $this->notificationMsgRepository->getMainTextMessage($id);

        if (!$event || !$mainMsg) {
            $this->flashMessage('Event or Main message not found!', 'msg_error');
            $this->redirect('this');
        }

        if (!\in_array($mainMsg->status, [NotificationMsgStatus::Draft, NotificationMsgStatus::Scheduled])) {
            $this->flashMessage('Cannot update notifications to a published event!', 'msg_error');
            $this->redirect('this');
        }

        $this->eventManager->updateImageNotifications($event, $mainMsg->scheduledAt, $toDelete, $toAdd);

        if (!empty($toDelete) || !empty($toAdd)) {
            $this->flashMessage('Image notifications updated.', 'msg_success');
        }

        $this->redirect('this');
    }

}
