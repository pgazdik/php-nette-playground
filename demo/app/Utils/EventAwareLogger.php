<?php
namespace App\Utils;

use Tracy\Debugger;
use Tracy\ILogger;
use Tracy\Logger;

class EventAwareLogger implements ILogger
{

    private ILogger $logger;
    private static ?int $eventId = null;

    public function __construct(
        private string $logDirectory
    ) {
        $eventDir = $this->logDirectory . "/events";
        if (!is_dir($eventDir)) {
            mkdir($eventDir, 0755, true);
        }
    }

    public function log($value, string $priority = self::INFO): ?string
    {
        if (self::$eventId)
            $priority = "events/event-" . self::$eventId;

        return $this->getLogger()->log($value, $priority);
    }

    public static function setEventId(?int $eventId): void
    {
        self::$eventId = $eventId;
    }

	private function getLogger(): ILogger
	{
		if (empty($this->logger)) {
			$this->logger = new Logger($this->logDirectory, Debugger::$email, Debugger::getBlueScreen());
			$this->logger->directory = &$this->logDirectory; // back compatiblity
			$this->logger->email = &Debugger::$email;
		}

		return $this->logger;
	}

}