<?php

namespace Gemboot\Traits;

use Exception;
use Throwable;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Gemboot\Exceptions\HttpErrorException;
use Gemboot\Exceptions\BadRequestException;
use Gemboot\Exceptions\UnauthorizedException;
use Gemboot\Exceptions\ForbiddenException;
use Gemboot\Exceptions\NotFoundException;
use Gemboot\Exceptions\ValidationFailException;
use Gemboot\Exceptions\ServerErrorException;

// use Illuminate\Support\Facades\Notification;
// use Gemboot\Notifications\Telegram;
use Gemboot\Libraries\TelegramLibrary;
use Gemboot\Support\SecurityHeaders;

use Gemboot\GembootValidator;

trait JSONResponses
{
    public static $STATUS_OK = 200;

    public static $STATUS_BAD_REQUEST = 400;
    public static $STATUS_UNAUTHORIZED = 401;
    public static $STATUS_FORBIDDEN = 403;
    public static $STATUS_NOT_FOUND = 404;

    public static $STATUS_SERVER_ERROR = 500;

    protected function statusMessage($status_code)
    {
        return isset(Response::$statusTexts[$status_code]) ? Response::$statusTexts[$status_code] : $status_code;
    }

    protected function encapsulateResponse($status, $data, $message = null)
    {
        return [
            'status' => $status,
            'message' => empty($message) ? $this->statusMessage($status) : $message,
            'data' => $data,
        ];
    }

    /**
     * BASIC RESPONSE
     *
     * @param integer $status HTTP STATUS CODE
     * @param array $data response data
     *
     * @return json
     */
    protected function response($status, $data, $message = null, $status_message = null, $additional_headers = [])
    {
        try {
            $headers = array_merge(SecurityHeaders::get(), [
                'Content-type' => 'application/json; charset=utf-8',
            ]);

            if (app('config')->get('gemboot.response.compressed')) {
                // Reported once per process; Laravel logs it to the deprecations channel.
                static $compressionDeprecationReported = false;
                if (!$compressionDeprecationReported) {
                    $compressionDeprecationReported = true;
                    trigger_error('gemboot.response.compressed is deprecated and will be removed in gemboot-lara 9.0. Let the web server compress responses.', E_USER_DEPRECATED);
                }

                ob_get_clean();

                $accept_encoding = request()->header('accept-encoding');

                if (substr_count($accept_encoding, "gzip")) {
                    $headers['Content-Encoding'] = 'gzip';
                    ob_start('ob_gzhandler');
                } else {
                    ob_start();
                }
            }

            $encapsulated = $this->encapsulateResponse(
                $status,
                $data,
                $message
            );

            // log_access() is not part of Gemboot; call it only if the app defines it.
            if (!empty($this->logAccessTag) && request()->isMethod('GET') && function_exists('log_access')) {
                log_access($this->logAccessTag);
            }

            $response = response()->json(
                $encapsulated,
                $status
            )->withHeaders(array_merge($headers, $additional_headers));
            if (!empty($status_message)) {
                $response = $response->setStatusCode($status, $status_message);
            }
            return $response;
        } catch (Exception $e) {
            if (config('app.debug')) {
                $data = $e->getTrace();
                $message = $e->getMessage();
            } else {
                $data = 'EXCEPTION: INTERNAL SERVER ERROR';
                $message = 'INTERNAL SERVER ERROR';
            }

            return response()->json(
                $this->encapsulateResponse(
                    self::$STATUS_SERVER_ERROR,
                    $data,
                    $message
                ),
                self::$STATUS_SERVER_ERROR
            )->withHeaders(SecurityHeaders::get());
        }
    }

    /**
     * SUCCESS RESPONSE (200)
     *
     * @param array $data response data
     * @param string $message (default:'Success!')
     *
     * @return json
     */
    public function responseSuccess($data = [], $message = null, $status_message = null)
    {
        return $this->response(Response::HTTP_OK, $data, $message, $status_message);
    }

    /**
     * BAD REQUEST RESPONSE (400)
     *
     * @param array $data response data
     * @param string $message (default:'Bad Request!')
     *
     * @return json
     */
    public function responseBadRequest($data = [], $message = null, $status_message = null)
    {
        return $this->response(Response::HTTP_BAD_REQUEST, $data, $message, $status_message);
    }

    /**
     * UNAUTHORIZED RESPONSE (401)
     *
     * @param array $data response data
     * @param string $message (default:'Unauthorized!')
     *
     * @return json
     */
    public function responseUnauthorized($data = [], $message = null, $status_message = null)
    {
        return $this->response(Response::HTTP_UNAUTHORIZED, $data, $message, $status_message);
    }

    /**
     * FORBIDDEN RESPONSE (403)
     *
     * @param array $data response data
     * @param string $message (default:'Forbidden!')
     *
     * @return json
     */
    public function responseForbidden($data = [], $message = null, $status_message = null)
    {
        return $this->response(Response::HTTP_FORBIDDEN, $data, $message, $status_message);
    }

    /**
     * BAD REQUEST RESPONSE (404)
     *
     * @param array $data response data
     * @param string $message (default:'Not Found!')
     *
     * @return json
     */
    public function responseNotFound($data = [], $message = null, $status_message = null)
    {
        return $this->response(Response::HTTP_NOT_FOUND, $data, $message, $status_message);
    }

    /**
     * ERROR RESPONSE (500)
     *
     * @param array $data response data
     * @param string $message (default:'Error!')
     *
     * @return json
     */
    public function responseError($data = [], $message = null, $status_message = null)
    {
        $additional_headers = [];
        if (app('config')->get('gemboot.response.send_header_error', true)) {
            $additional_headers['x-gemboot-error-message'] = $message;
        }
        return $this->response(Response::HTTP_INTERNAL_SERVER_ERROR, $data, $message, $status_message, $additional_headers);
    }

    /**
     * HTTP ERROR RESPONSE
     *
     * @param array $data response data
     * @param array $data response data
     *
     * @return json
     */
    public function responseHttpError($status_code, $data = [], $message = null, $status_message = null)
    {
        return $this->response($status_code, $data, $message, $status_message);
    }

    /**
     * ERROR RESPONSE (500)
     *
     * @param \Throwable $exception exception
     *
     * @return json
     */
    public function responseException(Throwable $exception)
    {
        $message = $exception->getMessage();

        // Goes through the app's exception handler: logged as before, and also
        // passed to error trackers (Sentry, Nightwatch, ...) the app has set up.
        report($exception);

        // Read through config() so the token survives config:cache.
        // The placeholder from the published config counts as not set.
        $telegram_token = config('gemboot.notifications.telegram.token');
        if ($telegram_token && $telegram_token !== 'YOUR BOT TOKEN HERE') {
            // $notif = (object)[
            //     'content' => "*NEW ERROR CATCH:*\n$message",
            // ];
            // Notification::notify(new Telegram($notif)); 
            try {
                (new TelegramLibrary)->sendExceptionMessage($exception);
            } catch (Throwable $e) {
                // A failed notification must not replace the original error response.
                \Log::warning('Gemboot: failed to send Telegram exception notification: ' . $e->getMessage());
            }
        }

        if (config('app.debug')) {
            return $this->responseError([
                'error' => $message,
                'trace' => $exception->getTrace()
            ], null, null);
        } else {
            // Unexpected exceptions can carry internals (a QueryException holds the
            // SQL, host, and database name), so clients only get a generic message.
            return $this->responseError([
                'error' => 'Internal Server Error',
            ]);
        }
    }

    /**
     * SUCCESS (200) OR ERROR RESPONSE (500)
     *
     * @param array $data response data
     *
     * @return json
     */
    public function responseSuccessOrException(callable $callback, array $validation_rules = [], array $validation_messages = [])
    {
        try {
            if (!empty($validation_rules)) {
                // $validator = (new GembootValidator)->make(request()->all(), $validation_rules, $validation_messages);
                // if ($validator->fails()) {
                //     return $this->responseBadRequest(['error' => $validator->errors()]);
                // }
                (new GembootValidator)->makeAndThrow(request()->all(), $validation_rules, $validation_messages);
            }

            $data = $callback();
            return $this->responseSuccess($data);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * SUCCESS (200) OR ERROR RESPONSE (500)
     *
     * @param array $data response data raw
     *
     * @return json
     */
    public function responseSuccessOrExceptionRaw(callable $callback, array $validation_rules = [], array $validation_messages = [])
    {
        try {
            if (!empty($validation_rules)) {
                // $validator = (new GembootValidator)->make(request()->all(), $validation_rules, $validation_messages);
                // if ($validator->fails()) {
                //     return $this->responseBadRequest(['error' => $validator->errors()]);
                // }
                (new GembootValidator)->makeAndThrow(request()->all(), $validation_rules, $validation_messages);
            }

            return $callback();
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * SUCCESS (200) OR ERROR RESPONSE (500), using a transaction
     *
     * @param array $data response data
     *
     * @return json
     */
    public function responseSuccessOrExceptionUsingTransaction(callable $callback, array $validation_rules = [], array $validation_messages = [])
    {
        \DB::beginTransaction();
        try {
            if (!empty($validation_rules)) {
                (new GembootValidator)->makeAndThrow(request()->all(), $validation_rules, $validation_messages);
            }

            $data = $callback();
            \DB::commit();
            return $this->responseSuccess($data);
        } catch (Throwable $e) {
            \DB::rollback();
            return $this->handleException($e);
        }
    }


    /**
     * Response Bad Request, (validation error laravel)
     *
     * @param \Illuminate\Support\MessageBag $errors
     *
     * @return json
     */
    public function responseValidationError(\Illuminate\Support\MessageBag $errors, $throw_err = false)
    {
        $err_message = null;
        $all_errors = $errors->all();

        if (count($all_errors) > 0) {
            $err_message = $all_errors[0];
        }

        if ($throw_err) {
            throw new BadRequestException($err_message);
        }

        return $this->responseBadRequest([
            'error' => $errors,
        ], $err_message, $err_message);
    }


    /**
     * CENTRALIZED EXCEPTION HANDLER
     * Menangani semua jenis exception secara seragam
     */
    protected function handleException(Throwable $e)
    {
        // 1. Handle Validation Exception (Specific Case)
        // Laravel's own validation ($request->validate(), Validator::validate())
        // answers like Gemboot's: 400 with the errors in data.error.
        if ($e instanceof ValidationException) {
            return $this->handleException(new ValidationFailException($e->errors(), 'Validation Failed', 400, $e));
        }

        if ($e instanceof ValidationFailException) {
            // The validation errors carried by the exception.
            $errors = $e->getData();
            // Backward compatibility: no data, but the message may hold the errors as JSON.
            if (empty($errors)) {
                $decoded = json_decode($e->getMessage(), true);
                $errors = $decoded ?: ['error' => $e->getMessage()];
            }

            return $this->responseBadRequest(['error' => $errors]);
        }

        // 2. Handle Gemboot HttpErrorException (Cover 400, 401, 403, 404, 422, 500, etc)
        if ($e instanceof HttpErrorException) {
            $data = $e->getData();
            $message = $e->getMessage();

            // Without specific data, the message becomes data.error.
            if (empty($data)) {
                $data = ['error' => $message];
            }

            return $this->responseHttpError(
                $e->getStatusCode(), // From Symfony's HttpException
                $data,
                null,
                $message
            );
        }

        // 3. Handle Laravel Model Not Found
        if ($e instanceof ModelNotFoundException) {
            return $this->responseNotFound(['error' => 'Data Not Found!'], null, 'Data Not Found!');
        }

        // 4. Laravel authorization: $this->authorize(), Gate::authorize(), policies.
        // A denial is the client's problem (403, or the status the policy chose),
        // not a server error.
        if ($e instanceof AuthorizationException) {
            $status = $e->hasStatus() ? $e->status() : Response::HTTP_FORBIDDEN;

            return $this->handleException(new HttpErrorException($status, $e->getMessage() ?: 'This action is unauthorized.', [], $e));
        }

        // 5. Default / Unexpected Exception (500)
        return $this->responseException($e);
    }
}
