<?php
namespace App\Presentation;

use App\Service\NotificationMsgRepository;

abstract class BaseEventPresenter extends \Nette\Application\UI\Presenter
{
    private NotificationMsgRepository $injectedNotificationMsgRepository;

    public function injectNotificationMsgRepository(NotificationMsgRepository $notificationMsgRepository): void
    {
        $this->injectedNotificationMsgRepository = $notificationMsgRepository;
    }

    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->template->countToApprove = $this->injectedNotificationMsgRepository->getCountToApprove();
    }
}
