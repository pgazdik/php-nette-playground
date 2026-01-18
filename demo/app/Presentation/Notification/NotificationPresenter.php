<?php
namespace App\Presentation\Notification;

use App\Presentation\BaseEventPresenter;
use App\Service\NotificationMsgRepository;
use App\Service\NotificationAttemptRepository;
use App\Service\NotificationManager;
use App\Utils\DateUtils;
use App\Utils\DebuggerUtils;
use Nette\Application\UI\Form;

use Exception;
use Nette;

class NotificationPresenter extends BaseEventPresenter
{
    private const PAGE_SIZE = 20;

    /** @persistent */
    public int $page = 1;

    private array $notificationsForForm = [];

    public function __construct(
        private NotificationMsgRepository $notificationMsgRepository,
        private NotificationAttemptRepository $notificationAttemptRepository,
        private NotificationManager $notificationManager
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


    // Prepares data ($notificationsForForm) for both renderToApprove and createComponentApproveForm
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
            
            $container->addText('scheduledAt')
                ->setHtmlAttribute('type', 'datetime-local')
                ->setDefaultValue($notification->scheduledAt->format('Y-m-d\TH:i'));

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
        $scheduledAtStr = $container['scheduledAt']->getValue();

        $this->notificationMsgRepository->updateText((int) $id, $text);

        if ($scheduledAtStr) {
            $scheduledAt = DateUtils::newBaDate($scheduledAtStr);
            $this->notificationMsgRepository->rescheduleAt((int) $id, $scheduledAt);
        }

        $this->flashMessage('Notification updated.', 'msg_success');
        $this->redirect('this');
    }

    public function handleApprove(int $id): void
    {
        try {
            $this->notificationManager->approveNotification($id);
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

        $notificationMsgs = $this->notificationMsgRepository->getScheduled(self::PAGE_SIZE, $offset);
        $this->template->notificationMsgs = $notificationMsgs;
        
        $msgIds = array_map(fn($n) => $n->id, $notificationMsgs);
        $this->template->activeAttempts = $this->notificationAttemptRepository->getActiveAttemptsMap($msgIds);

        $this->template->page = $this->page;
        $this->template->lastPage = $lastPage;
    }

    public function handleSend(int $msgId): void
    {
        try {
            $error = $this->notificationManager->forceSend($msgId);
            if ($error)
                $this->flashMessage($error, 'msg_error');
            else
                $this->flashMessage('Notification sending triggered.', 'msg_success');

        } catch (Exception $e) {
            DebuggerUtils::logException($e, "Failed to send msg #{$msgId}");
            $this->flashMessage('Failed to trigger sending: ' . $e->getMessage(), 'msg_error');
        }
        $this->redirect('this');
    }

    public function handleWithdraw(int $msgId): void
    {
        try {
            $this->notificationManager->withdrawNotification($msgId);
            $this->flashMessage('Notification withdrawn.', 'msg_success');

        } catch (Exception $e) {
            DebuggerUtils::logException($e, "Failed to withdraw msg #{$msgId}");
            $this->flashMessage('Failed to withdraw notification: ' . $e->getMessage(), 'msg_error');
        }
        $this->redirect('this');
    }

    public function handleCheckStatus(int $attemptId): void
    {
        try {
            $error = $this->notificationManager->forceCheckStatus($attemptId);
            if ($error)
                $this->flashMessage($error, 'msg_error');
            else
                $this->flashMessage('Notification status check triggered.', 'msg_success');

        } catch (Exception $e) {
            DebuggerUtils::logException($e, "Failed to check status for attempt #{$attemptId}");
            $this->flashMessage('Failed to trigger status check: ' . $e->getMessage(), 'msg_error');
        }
        $this->redirect('this');
    }


}