<?php
namespace App\Presentation\Notification;

use App\Service\NotificationMsgRepository;
use App\Service\EventManager;
use App\Utils\DebuggerUtils;
use Nette\Application\UI\Form;
use Tracy\Debugger;

use Exception;
use Nette;

final class NotificationPresenter extends Nette\Application\UI\Presenter
{
    private const PAGE_SIZE = 20;

    /** @persistent */
    public int $page = 1;

    private array $notificationsForForm = [];

    public function __construct(
        private NotificationMsgRepository $notificationMsgRepository,
        private EventManager $eventManager
    ) {
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->setLayout('eventlayout');
    }

    public function renderDefault(): void
    {
        $count = $this->notificationMsgRepository->getCount();
        $lastPage = (int) ceil($count / self::PAGE_SIZE);
        if ($lastPage === 0) {
            $lastPage = 1;
        }

        $this->page = max(1, min($this->page, $lastPage));
        $offset = ($this->page - 1) * self::PAGE_SIZE;

        $this->template->notifications = $this->notificationMsgRepository->getAll(self::PAGE_SIZE, $offset);
        $this->template->page = $this->page;
        $this->template->lastPage = $lastPage;
    }

    //
    // To Approve
    //


    public function actionToApprove(): void
    {
        $count = $this->notificationMsgRepository->getCountToApprove();
        $lastPage = (int) ceil($count / self::PAGE_SIZE);
        if ($lastPage === 0) {
            $lastPage = 1;
        }

        $this->page = max(1, min($this->page, $lastPage));
        $offset = ($this->page - 1) * self::PAGE_SIZE;

        $this->notificationsForForm = $this->notificationMsgRepository->getToApprove(
            self::PAGE_SIZE,
            $offset
        );
    }

    public function renderToApprove(): void
    {
        // Re-calculate lastPage for the template (redundant but safe) or store in property.
        // For now, let's just pass the data we fetched in action.
        $count = $this->notificationMsgRepository->getCountToApprove();
        $lastPage = (int) ceil($count / self::PAGE_SIZE);
        if ($lastPage === 0) {
            $lastPage = 1;
        }

        $this->template->notifications = $this->notificationsForForm;
        $this->template->page = $this->page;
        $this->template->lastPage = $lastPage;
    }

    protected function createComponentApproveForm(): Form
    {
        $form = new Form;
        $notificationsContainer = $form->addContainer('notifications');

        foreach ($this->notificationsForForm as $notification) {
            $container = $notificationsContainer->addContainer($notification->id);
            $container->addTextArea('text')
                ->setDefaultValue($notification->text);
            $container->addSubmit('save', 'Save')
                ->onClick[] = [$this, 'approveFormItemSucceeded'];
        }
        return $form;
    }

    public function approveFormItemSucceeded(Nette\Forms\Controls\SubmitButton $button): void
    {
        $container = $button->getParent();
        $id = $container->getName();
        $text = $container['text']->getValue();

        $this->notificationMsgRepository->updateText((int) $id, $text);
        $this->flashMessage('Notification updated.', 'msg_success');
        $this->redirect('this');
    }

    public function handleApprove(int $id): void
    {
        try {
            $this->eventManager->approveNotification($id);
            $this->flashMessage('Notifications for the event have been scheduled.', 'msg_success');
        } catch (Exception $e) {
            DebuggerUtils::logException($e, "Failed to approve notification #{$id}");
            $this->flashMessage('Failed to approve notification: ' . $e->getMessage(), 'msg_error');
        }
        $this->redirect('this');
    }

    //
    // Scheduled
    //
    public function renderScheduled(): void
    {
        $count = $this->notificationMsgRepository->getCountScheduled();
        $lastPage = (int) ceil($count / self::PAGE_SIZE);
        if ($lastPage === 0) {
            $lastPage = 1;
        }

        $this->page = max(1, min($this->page, $lastPage));
        $offset = ($this->page - 1) * self::PAGE_SIZE;

        $this->template->notifications = $this->notificationMsgRepository->getScheduled(self::PAGE_SIZE, $offset);
        $this->template->page = $this->page;
        $this->template->lastPage = $lastPage;
    }


}