<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class ResponseException extends Response
{
    public const HTTP_AUTHENTICATION_TIMEOUT = 419; // not in RFC 2616

    public static $customStatusTexts = [
        419 => 'Authentication Timeout'
    ];

    public static function getStatusTextByCode(int $code): string
    {
        $statusTexts = parent::$statusTexts;
        $statusTexts[self::HTTP_AUTHENTICATION_TIMEOUT] = self::$customStatusTexts[self::HTTP_AUTHENTICATION_TIMEOUT];

        return $statusTexts[$code] ?? 'Unknow Status';
    }

    /**
     * Get list of HTTP constant names indexed by HTTP code
     *
     * @return array
     */
    public static function getListHttpConstantStatusNames(): array
    {
        return [
            parent::HTTP_CONTINUE => 'HTTP_CONTINUE',
            parent::HTTP_SWITCHING_PROTOCOLS => 'HTTP_SWITCHING_PROTOCOLS',
            parent::HTTP_PROCESSING => 'HTTP_PROCESSING',
            parent::HTTP_EARLY_HINTS => 'HTTP_EARLY_HINTS',
            parent::HTTP_OK => 'HTTP_OK',
            parent::HTTP_CREATED => 'HTTP_CREATED',
            parent::HTTP_ACCEPTED => 'HTTP_ACCEPTED',
            parent::HTTP_NON_AUTHORITATIVE_INFORMATION => 'HTTP_NON_AUTHORITATIVE_INFORMATION',
            parent::HTTP_NO_CONTENT => 'HTTP_NO_CONTENT',
            parent::HTTP_RESET_CONTENT => 'HTTP_RESET_CONTENT',
            parent::HTTP_PARTIAL_CONTENT => 'HTTP_PARTIAL_CONTENT',
            parent::HTTP_MULTI_STATUS => 'HTTP_MULTI_STATUS',
            parent::HTTP_ALREADY_REPORTED => 'HTTP_ALREADY_REPORTED',
            parent::HTTP_IM_USED => 'HTTP_IM_USED',
            parent::HTTP_MULTIPLE_CHOICES => 'HTTP_MULTIPLE_CHOICES',
            parent::HTTP_MOVED_PERMANENTLY => 'HTTP_MOVED_PERMANENTLY',
            parent::HTTP_FOUND => 'HTTP_FOUND',
            parent::HTTP_SEE_OTHER => 'HTTP_SEE_OTHER',
            parent::HTTP_NOT_MODIFIED => 'HTTP_NOT_MODIFIED',
            parent::HTTP_USE_PROXY => 'HTTP_USE_PROXY',
            parent::HTTP_RESERVED => 'HTTP_RESERVED',
            parent::HTTP_TEMPORARY_REDIRECT => 'HTTP_TEMPORARY_REDIRECT',
            parent::HTTP_PERMANENTLY_REDIRECT => 'HTTP_PERMANENTLY_REDIRECT',
            parent::HTTP_BAD_REQUEST => 'HTTP_BAD_REQUEST',
            parent::HTTP_UNAUTHORIZED => 'HTTP_UNAUTHORIZED',
            parent::HTTP_PAYMENT_REQUIRED => 'HTTP_PAYMENT_REQUIRED',
            parent::HTTP_FORBIDDEN => 'HTTP_FORBIDDEN',
            parent::HTTP_NOT_FOUND => 'HTTP_NOT_FOUND',
            parent::HTTP_METHOD_NOT_ALLOWED => 'HTTP_METHOD_NOT_ALLOWED',
            parent::HTTP_NOT_ACCEPTABLE => 'HTTP_NOT_ACCEPTABLE',
            parent::HTTP_PROXY_AUTHENTICATION_REQUIRED => 'HTTP_PROXY_AUTHENTICATION_REQUIRED',
            parent::HTTP_REQUEST_TIMEOUT => 'HTTP_REQUEST_TIMEOUT',
            parent::HTTP_CONFLICT => 'HTTP_CONFLICT',
            parent::HTTP_GONE => 'HTTP_GONE',
            parent::HTTP_LENGTH_REQUIRED => 'HTTP_LENGTH_REQUIRED',
            parent::HTTP_PRECONDITION_FAILED => 'HTTP_PRECONDITION_FAILED',
            parent::HTTP_REQUEST_ENTITY_TOO_LARGE => 'HTTP_REQUEST_ENTITY_TOO_LARGE',
            parent::HTTP_REQUEST_URI_TOO_LONG => 'HTTP_REQUEST_URI_TOO_LONG',
            parent::HTTP_UNSUPPORTED_MEDIA_TYPE => 'HTTP_UNSUPPORTED_MEDIA_TYPE',
            parent::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE => 'HTTP_REQUESTED_RANGE_NOT_SATISFIABLE',
            parent::HTTP_EXPECTATION_FAILED => 'HTTP_EXPECTATION_FAILED',
            parent::HTTP_I_AM_A_TEAPOT => 'HTTP_I_AM_A_TEAPOT',
            parent::HTTP_MISDIRECTED_REQUEST => 'HTTP_MISDIRECTED_REQUEST',
            parent::HTTP_UNPROCESSABLE_ENTITY => 'HTTP_UNPROCESSABLE_ENTITY',
            parent::HTTP_LOCKED => 'HTTP_LOCKED',
            parent::HTTP_FAILED_DEPENDENCY => 'HTTP_FAILED_DEPENDENCY',
            parent::HTTP_TOO_EARLY => 'HTTP_TOO_EARLY',
            parent::HTTP_UPGRADE_REQUIRED => 'HTTP_UPGRADE_REQUIRED',
            parent::HTTP_PRECONDITION_REQUIRED => 'HTTP_PRECONDITION_REQUIRED',
            parent::HTTP_TOO_MANY_REQUESTS => 'HTTP_TOO_MANY_REQUESTS',
            parent::HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE => 'HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE',
            parent::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS => 'HTTP_UNAVAILABLE_FOR_LEGAL_REASONS',
            parent::HTTP_INTERNAL_SERVER_ERROR => 'HTTP_INTERNAL_SERVER_ERROR',
            parent::HTTP_NOT_IMPLEMENTED => 'HTTP_NOT_IMPLEMENTED',
            parent::HTTP_BAD_GATEWAY => 'HTTP_BAD_GATEWAY',
            parent::HTTP_SERVICE_UNAVAILABLE => 'HTTP_SERVICE_UNAVAILABLE',
            parent::HTTP_GATEWAY_TIMEOUT => 'HTTP_GATEWAY_TIMEOUT',
            parent::HTTP_VERSION_NOT_SUPPORTED => 'HTTP_VERSION_NOT_SUPPORTED',
            parent::HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL => 'HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL',
            parent::HTTP_INSUFFICIENT_STORAGE => 'HTTP_INSUFFICIENT_STORAGE',
            parent::HTTP_LOOP_DETECTED => 'HTTP_LOOP_DETECTED',
            parent::HTTP_NOT_EXTENDED => 'HTTP_NOT_EXTENDED',
            parent::HTTP_NETWORK_AUTHENTICATION_REQUIRED => 'HTTP_NETWORK_AUTHENTICATION_REQUIRED',
            self::HTTP_AUTHENTICATION_TIMEOUT => 'HTTP_AUTHENTICATION_TIMEOUT'
        ];
    }
}
