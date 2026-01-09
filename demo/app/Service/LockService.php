<?php
namespace App\Service;

use Nette\Database\Explorer;

class LockService
{
    public function __construct(
        private Explorer $database
    ) {
    }

    /**
     * Attempts to acquire a named advisory lock.
     *
     * @param string $identifier The unique name of the lock.
     * @param int $timeout Time in seconds to wait for the lock (default 0).
     * @return bool True if lock was acquired, False if timeout or error.
     */
    public function acquireLock(string $identifier, int $timeout = 0): bool
    {
        // MySQL GET_LOCK(str, timeout) returns 1 if successful, 0 if timed out, NULL on error.
        $result = $this->database->query('SELECT GET_LOCK(?, ?)', $identifier, $timeout)->fetchField();
        
        return $result == 1;
    }

    /**
     * Releases a named advisory lock.
     * 
     * @param string $identifier The unique name of the lock.
     * @return bool True if released, False if not established by this thread or didn't exist.
     */
    public function releaseLock(string $identifier): bool
    {
        // RELEASE_LOCK(str) returns 1 if released, 0 if not owned by thread, NULL if doesn't exist.
        $result = $this->database->query('SELECT RELEASE_LOCK(?)', $identifier)->fetchField();

        return $result == 1;
    }
}
