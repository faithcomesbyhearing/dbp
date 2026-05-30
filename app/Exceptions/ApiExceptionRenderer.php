<?php

namespace App\Exceptions;

use App\Mail\ExceptionOccured;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use App\Exceptions\ResponseException as Response;
use Throwable;

class ApiExceptionRenderer
{
    public function render(Throwable $exception, $request)
    {
        if (config('app.env') == 'local') {
            return null;
        }

        if ($request->route() instanceof \Illuminate\Routing\Route) {
            $middelware_array = $request->route()->middleware();

            if (!empty($middelware_array) && in_array('web', $middelware_array)) {
                return $this->handleWebException($exception);
            }
        }

        return $this->handleApiException($request, $exception);
    }

    private function handleWebException(Throwable $exception)
    {
        if ($exception instanceof TokenMismatchException) {
            return redirect()
                ->back()
                ->withErrors(
                    ['auth.sessionExpired' => trans('auth.sessionExpired')]
                );
        }

        return null;
    }

    private function handleApiException($request, Throwable $exception)
    {
        $exception = $this->prepareException($exception);

        if ($exception instanceof \Illuminate\Http\Exception\HttpResponseException) {
            $exception = $exception->getResponse();
        }

        if ($exception instanceof AuthenticationException) {
            $result = $this->unauthenticated($request, $exception);
            if (config('app.env') !== 'debug') {
                return $result;
            }
        }

        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            $exception = $this->convertValidationExceptionToResponse($exception, $request);
        }

        return $this->customApiResponse($exception);
    }

    private function prepareException(Throwable $exception)
    {
        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            $exception = new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException($exception->getMessage(), $exception);
        } elseif ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
            $exception = new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException($exception->getMessage(), $exception);
        } elseif ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            // already an HTTP exception
        }

        return $exception;
    }

    private function convertValidationExceptionToResponse($exception, $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => $exception->errors(),
                'status_code' => 422,
            ], 422);
        }

        return redirect()->back()->withInput($request->input())->withErrors($exception->errors());
    }

    private function customApiResponse($exception)
    {
        if ($exception instanceof \Illuminate\Http\JsonResponse || $exception instanceof \Illuminate\Http\Response || $exception instanceof \Illuminate\Http\RedirectResponse) {
            return $exception;
        }

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

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() ||
            (isset($exception->api_response) && $exception->api_response)
        ) {
            $response = [];
            $response['error'] = Response::$statusTexts[Response::HTTP_UNAUTHORIZED];
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

    public function sendEmail(Throwable $exception)
    {
        try {
            $e = FlattenException::createFromThrowable($exception);
            $renderer = new HtmlErrorRenderer(true);
            $html = $renderer->render($e)->getAsString();

            Mail::send(new ExceptionOccured($html));
        } catch (Exception $ex) {
            Log::error($ex);
        }
    }

    private function getTypeErrorResponseFromCode(int $statusCode): string
    {
        $listHttpConstantNames = Response::getListHttpConstantStatusNames();

        return $listHttpConstantNames[$statusCode] ?? $listHttpConstantNames[Response::HTTP_INTERNAL_SERVER_ERROR];
    }
}
