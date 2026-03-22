<?php
namespace App\Common;

/**
 * @template TValue
 * @template TError
 */
class Maybe
{

    private function __construct(
        public readonly bool $isSuccess,
        /** @var TValue|null */
        public readonly mixed $value,
        /** @var TError|null */
        public readonly mixed $error = null,
        public readonly ?string $warning = null
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
        return new self(true, $value);
    }

    /**
     * Creates a success Result with a warning.
     * @template TValue
     * @param TValue $value
     * @return Maybe<TValue, string>
     */
    public static function successWithWarning(mixed $value, string $warning): Maybe
    {
        return new self(true, $value, null, $warning);
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
     */
    public function onError(callable $callback): self
    {
        if (!$this->isSuccess) {
            $callback($this->error);
        }
        return $this;
    }

    public function onWarning(callable $callback): self
    {
        if ($this->warning !== null) {
            $callback($this->warning);
        }
        return $this;
    }

}