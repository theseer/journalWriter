<?php declare(strict_types=1);
namespace theseer\journalWriter;

use ArrayIterator;
use IteratorAggregate;
use function bin2hex;
use function debug_backtrace;
use function hexdec;
use function random_bytes;
use function sprintf;
use function substr;
use Throwable;

final class LogEntry implements IteratorAggregate {

    private array $data = [];

    /**
     * @throws LogEntryException
     */
    private function __construct(array $values) {
        $this->createId();

        foreach($values as $key => $value) {
            $this->addValue($key, $value);
        }
    }

    public static function fromMessage(string $message): self {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 1)[0];
        return new self(
            [
                'MESSAGE' => $message,
                'CODE_FILE' => $trace['file'] ?? 'unknown',
                'CODE_LINE' => (string)$trace['line'],
                'CODE_FUNC' => sprintf(
                    '%s%s%s',
                    $trace['class'],
                    $trace['type'],
                    $trace['function']
                )
            ]
        );
    }

    public static function fromThrowable(Throwable $throwable): self {
        return new self(
            [
                'MESSAGE' => $throwable->getMessage(),
                'CODE_FILE' => $throwable->getFile(),
                'CODE_LINE' => (string)$throwable->getLine(),
                'CODE_FUNC' => $throwable->getTrace()[0]['function'],
                'ERRNO' => (string)$throwable->getCode(),
                'CLASS' => \get_class($throwable),
                'TRACE' => $throwable->getTraceAsString()
            ]
        );
    }

    public function addValue(string $key, string $value) {
        $caps = \mb_strtoupper($key);
        if ($caps[0] === '_') {
            throw new LogEntryException(
                'Key must not start with "_".'
            );
        }

        if (isset($this->data[$caps])) {
            throw new LogEntryException(
                sprintf('Cannot overwrite already set key "%s"', $caps)
            );
        }

        $this->data[$caps] = $value;
    }

    private function createId(): void {
        try {
            $bytes = random_bytes(16);
            // @codeCoverageIgnoreStart
        } catch (Throwable) {
            throw new LogEntryException('Failed to create UUID', previous: $e);
        }
        // @codeCoverageIgnoreEnd

        $bytes = bin2hex($bytes);

        $this->data['MESSAGE_ID'] =sprintf(
            '%08s-%04s-4%03s-%04x-%012s',
            substr($bytes, 0, 8),
            substr($bytes, 8, 4),
            substr($bytes, 13, 3),
            hexdec(substr($bytes, 16, 4)) & 0x3fff | 0x8000,
            substr($bytes, 20, 12)
        );
    }

    public function getIterator(): ArrayIterator {
        return new ArrayIterator($this->data);
    }
}
