<?php
namespace App\Common;

class Maybe
{

    private function __construct(
        public readonly bool $isSuccess,
        public readonly mixed $value,
        public readonly ?string $error
    ) {
    }

    /**
     * Creates a successful Result.
     * @template TValue
     * @param TValue $value
     * @return Maybe<TValue, mixed>
     */
    public static function success(mixed $value): Maybe
    {
        return new self(true, $value, null);
    }

    /**
     * Creates an error Result.
     * @template TError
     * @param TError $error
     * @return Maybe<mixed, TError>
     */
    public static function error(mixed $error): Maybe
    {
        return new self(false, null, $error);
    }

    /**
     * Executes a callback if the result is successful, passing the value.
     * @template TValue
     * @param callable(TValue): void $callback
     * @return $this
     */
    public function onSuccess(callable $callback): self
    {
        if ($this->isSuccess) {
            $callback($this->value);
        }
        return $this;
    }

    /**
     * Executes a callback if the result is an error, passing the error.
     * @template TError
     * @param callable(TError): void $callback
     * @return $this
     */
    public function onError(callable $callback): self
    {
        if (!$this->isSuccess) {
            $callback($this->error);
        }
        return $this;
    }
}