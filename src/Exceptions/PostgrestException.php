<?php

declare(strict_types=1);

namespace Sereny\PostgREST\Exceptions;

use GuzzleHttp\Exception\RequestException;
use RuntimeException;

class PostgrestException extends RuntimeException
{
    /**
     * Create an exception from a failed PostgREST request.
     *
     * The PostgREST error body (message, details, hint) is used to build a
     * descriptive exception message when available.
     */
    public static function fromResponse(RequestException $exception): self
    {
        $response = $exception->getResponse();
        $status = $response?->getStatusCode() ?? 0;
        $message = $exception->getMessage();

        if ($response !== null) {
            $body = json_decode((string) $response->getBody(), true);

            if (is_array($body)) {
                $message = $body['message'] ?? $message;

                if (! empty($body['details'])) {
                    $message .= ' ('.$body['details'].')';
                }
            }
        }

        return new self($message, $status, $exception);
    }
}
