<?php
namespace App\Presentation\Notification;

use App\Presentation\BaseEventPresenter;
use App\Service\NotificationMsgRepository;
use App\Service\NotificationAttemptRepository;
use App\Service\NotificationManager;
use App\Utils\DateUtils;
use App\Utils\DebuggerUtils;
use App\Utils\MediaHandler;
use Nette\Application\UI\Form;

use App\Utils\EventAwareLogger;
use Exception;
use Nette;
use function PHPUnit\Framework\callback;

class NotificationPresenter extends BaseEventPresenter
{
    private const PAGE_SIZE = 20;

    /** @persistent */
    public int $page = 1;

    private array $notificationsForForm = [];

    public function __construct(
        private NotificationMsgRepository $notificationMsgRepository,
        private NotificationAttemptRepository $notificationAttemptRepository,
        private NotificationManager $notificationManager,
        private MediaHandler $mediaHandler,
    ) {
    }

    //
    // Serving Images
    //

    public function actionServeImage(int $notificationId): void
    {
        $msg = $this->notificationMsgRepository->getById($notificationId);

        if (!$msg || !$msg->filePath) {
            throw new Nette\Application\BadRequestException('Notification or file path not found.');
        }

        $fullPath = $this->mediaHandler->resolvePath($msg->filePath);

        if (!file_exists($fullPath)) {
            throw new Nette\Application\BadRequestException('Image file not found.');
        }

        $this->sendResponse(new Nette\Application\Responses\FileResponse($fullPath));
    }

    public function actionServeImageByPath(string $path, string $doctorName): void
    {
        // Security check: path must start with doctorName
        if (!str_starts_with($path, $doctorName . '/')) {
            throw new Nette\Application\BadRequestException('Invalid image path for this doctor.');
        }

        $fullPath = $this->mediaHandler->resolvePath($path);

        if (!file_exists($fullPath) || !is_file($fullPath)) {
            throw new Nette\Application\BadRequestException('Image file not found.');
        }

        $this->sendResponse(new Nette\Application\Responses\FileResponse($fullPath));
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->setLayout('eventlayout');
        $this->template->countToApprove = $this->notificationMsgRepository->getCountToApprove();
    }

    public function renderDefault(): void
    {
        $this->preparePaginatedContent(
            fn() => $this->notificationMsgRepository->getCount(),
            fn($size, $offset) => $this->notificationMsgRepository->getAll($size, $offset)
        );
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
        [$notificationMsgs] = $this->preparePaginatedContent(
            fn() => $this->notificationMsgRepository->getCountScheduledOrSent(),
            fn($pageSize, $offset) => $this->notificationMsgRepository->getScheduledOrSent($pageSize, $offset)
        );

        $msgIds = array_map(fn($n) => $n->id, $notificationMsgs);
        $this->template->activeAttempts = $this->notificationAttemptRepository->getActiveAttemptsMap($msgIds);
    }

    public function renderFailed(): void
    {
        $this->preparePaginatedContent(
            fn() => $this->notificationMsgRepository->getCountFailed(),
            fn($pageSize, $offset) => $this->notificationMsgRepository->getFailed($pageSize, $offset)
        );
    }

    private function preparePaginatedContent(callable $countFn, callable $listFn): array
    {
        $count = $countFn();
        $lastPage = (int) ceil($count / self::PAGE_SIZE);
        if ($lastPage === 0) {
            $lastPage = 1;
        }

        $this->page = max(1, min($this->page, $lastPage));
        $offset = ($this->page - 1) * self::PAGE_SIZE;

        $notificationMsgs = $listFn(self::PAGE_SIZE, $offset);
        $this->template->notificationMsgs = $notificationMsgs;

        $this->template->page = $this->page;
        $this->template->lastPage = $lastPage;

        return [$notificationMsgs];
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