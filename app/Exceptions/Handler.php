<?php

namespace App\Exceptions;

use App\Mail\ExceptionOccured;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Mail;
use ReflectionClass;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\ExceptionHandler as SymfonyExceptionHandler;
use App\Exceptions\ResponseException as Response;
use Throwable;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Session\TokenMismatchException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param \Throwable $exception
     *
     * @return void
     * @throws Exception
     */
    public function report(Throwable $exception)
    {
        $enableEmailExceptions = config('exceptions.emailExceptionEnabled');

        if ($enableEmailExceptions === '') {
            $enableEmailExceptions = config(
                'exceptions.emailExceptionEnabledDefault'
            );
        }

        if ($enableEmailExceptions && $this->shouldReport($exception)) {
            $this->sendEmail($exception);
        }
        $sentry_dsn = config('sentry.dsn');

        if ($sentry_dsn &&
            config('app.env') == 'production' &&
            $this->shouldReport($exception) &&
            app()->bound('sentry')
        ) {
            app('sentry')->captureException($exception);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Throwable               $exception
     *
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception)
    {
        $middelware_array = $request->route()->middleware();
        
        if (config('app.env') == 'local' || (!empty($middelware_array) && in_array('web', $middelware_array))) {
            if ($exception instanceof TokenMismatchException) {
                return redirect()
                    ->back()
                    ->withErrors(
                        ['auth.sessionExpired' => trans('auth.sessionExpired')]
                    );
            }

            return parent::render($request, $exception);
        }

        return $this->handleApiException($request, $exception);
    }

    private function handleApiException($request, Throwable $exception)
    {
        $exception = $this->prepareException($exception);

        if ($exception instanceof \Illuminate\Http\Exception\HttpResponseException) {
            $exception = $exception->getResponse();
        }

        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            $exception = $this->unauthenticated($request, $exception);
            if (!config('app.env') !== 'debug') {
                return $exception;
            }
        }

        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            $exception = $this->convertValidationExceptionToResponse(
                $exception,
                $request
            );
        }

        return $this->customApiResponse($exception);
    }

    private function customApiResponse($exception)
    {
        $responseCode = Response::HTTP_INTERNAL_SERVER_ERROR;

        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = $exception->getStatusCode();
            $responseCode = $exception->getStatusCode();
        } else {
            $responseCode = $exception->getCode();
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        $response = [];
        $response['error'] = Response::getStatusTextByCode($statusCode);
        $response['type'] = $this->getTypeErrorResponseFromCode($statusCode);

        if ($statusCode === Response::HTTP_UNPROCESSABLE_ENTITY) {
            $message = $exception->getMessage();
            if ($message === '') {
                $message = Response::getStatusTextByCode($statusCode);
            }
            if (\is_object($message)) {
                $message = $message->toArray();
            }
            $response['error'] = $message;
        }

        if (config('app.debug')) {
            $response['trace'] = $exception->getTrace();
        }
        $response['status_code'] = $responseCode;
        $response['host_name'] = gethostname();
        return response()->json($response, $statusCode);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param \Illuminate\Http\Request                 $request
     * @param \Illuminate\Auth\AuthenticationException $exception
     *
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated(
        $request,
        AuthenticationException $exception
    ) {
        if ($request->expectsJson() ||
            (isset($exception->api_response) && $exception->api_response)
        ) {
            $response = [];
            $response['error'] =
                Response::$statusTexts[Response::HTTP_UNAUTHORIZED];
            if (config('app.debug')) {
                $response['trace'] = $exception->getTrace();
            }
            $response['status_code'] = Response::HTTP_UNAUTHORIZED;
            $response['host_name'] = gethostname();
            return response()->json($response, Response::HTTP_UNAUTHORIZED);
        }
        $route_keys = explode('/', $_SERVER['REQUEST_URI']);
        $is_api_key_route = in_array('api_key', $route_keys);
        $route_login = $is_api_key_route ? 'api_key.login' : 'login';

        return redirect()->guest(route($route_login));
    }

    /**
     * Sends an email upon exception.
     *
     * @param \Exception $exception
     *
     * @return void
     */
    public function sendEmail(Exception $exception)
    {
        try {
            $e = FlattenException::create($exception);
            $handler = new SymfonyExceptionHandler();
            $html = $handler->getHtml($e);

            Mail::send(new ExceptionOccured($html));
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }

    /**
     * Get name of the Response static property that remains to the given status code response.
     *
     * @param int $statusCode
     *
     * @return string
     */
    private function getTypeErrorResponseFromCode(int $statusCode): string
    {
        $listHttpConstantNames = Response::getListHttpConstantStatusNames();

        return $listHttpConstantNames[$statusCode] ?? $listHttpConstantNames[Response::HTTP_INTERNAL_SERVER_ERROR];
    }
}
