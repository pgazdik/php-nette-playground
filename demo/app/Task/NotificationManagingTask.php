<?php
namespace App\Task;

use App\Service\NotificationManager;
use Tracy\Debugger;

class NotificationManagingTask
{
	public function __construct(
		private NotificationManager $notificationManager
	) {
	}

	public function run()
	{
		// If we first SEND, then CHECK, then all newly sent msgs would not be found on the SMS GW
		$this->notificationManager->checkStatusOfSentNotifications();
		$this->notificationManager->sendEligibleNotifications();
		// Debugger::log("ENV SMS_GW_URL: " . getenv('SMS_GW_URL'));
		// Debugger::log("ENV SMS_GW_TOKEN: " . getenv('SMS_GW_TOKEN'));
	}

}


