<?php

namespace Gemboot\Traits;

use Exception;
use Illuminate\Http\Response;
use Gemboot\Exceptions\HttpErrorException;
use Gemboot\Exceptions\BadRequestException;
use Gemboot\Exceptions\UnauthorizedException;
use Gemboot\Exceptions\ForbiddenException;
use Gemboot\Exceptions\NotFoundException;
use Gemboot\Exceptions\ValidationFailException;

// use Illuminate\Support\Facades\Notification;
// use Gemboot\Notifications\Telegram;
use Gemboot\Libraries\TelegramLibrary;

use Gemboot\Exceptions\ServerErrorException;
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
            $headers = [
                'Content-type' => 'application/json; charset=utf-8',
            ];

            if (app('config')->get('gemboot.response.compressed')) {
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

            if (!empty($this->logAccessTag) && request()->isMethod('GET')) {
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
            if (env('APP_DEBUG')) {
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
            );
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
        if (app('config')->get('gemboot.response.send_header_error')) {
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
     * @param \Exception $exception exception
     *
     * @return json
     */
    public function responseException(Exception $exception)
    {
        $message = $exception->getMessage();

        \Log::error($message);
        \Log::error($exception->getTraceAsString());

        if (env('GEMBOOT_TELEGRAM_BOT_TOKEN')) {
            // $notif = (object)[
            //     'content' => "*NEW ERROR CATCH:*\n$message",
            // ];
            // Notification::notify(new Telegram($notif)); 
            (new TelegramLibrary)->sendExceptionMessage($exception);
        }

        if (env('APP_DEBUG')) {
            return $this->responseError([
                'error' => $message,
                'trace' => $exception->getTrace()
            ], null, null);
        } else {
            return $this->responseError([
                'error' => $message,
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
        } catch (ValidationFailException $e) {
            $err_message = json_decode($e->getMessage(), true);
            return $this->responseBadRequest(
                [
                    'error' => $err_message
                ]
            );
        } catch (HttpErrorException $e) {
            $err_message = $e->getMessage();
            return $this->responseHttpError(
                $e->getCode(),
                [
                    'error' => $err_message,
                ],
                null,
                $err_message
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $err_message = "Data Not Found!";
            return $this->responseNotFound(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (BadRequestException $e) {
            $err_message = $e->getMessage();
            return $this->responseBadRequest(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (UnauthorizedException $e) {
            $err_message = $e->getMessage();
            return $this->responseUnauthorized(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (ForbiddenException $e) {
            $err_message = $e->getMessage();
            return $this->responseForbidden(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (NotFoundException $e) {
            $err_message = $e->getMessage();
            return $this->responseNotFound(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (ServerErrorException $e) {
            return $this->responseException($e);
        } catch (Exception $e) {
            return $this->responseException($e);
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
        } catch (ValidationFailException $e) {
            $err_message = json_decode($e->getMessage(), true);
            return $this->responseBadRequest(
                [
                    'error' => $err_message
                ]
            );
        } catch (HttpErrorException $e) {
            $err_message = $e->getMessage();
            return $this->responseHttpError(
                $e->getCode(),
                [
                    'error' => $err_message,
                ],
                null,
                $err_message
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $err_message = "Data Not Found!";
            return $this->responseNotFound(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (BadRequestException $e) {
            $err_message = $e->getMessage();
            return $this->responseBadRequest(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (UnauthorizedException $e) {
            $err_message = $e->getMessage();
            return $this->responseUnauthorized(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (ForbiddenException $e) {
            $err_message = $e->getMessage();
            return $this->responseForbidden(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (NotFoundException $e) {
            $err_message = $e->getMessage();
            return $this->responseNotFound(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (ServerErrorException $e) {
            return $this->responseException($e);
        } catch (Exception $e) {
            return $this->responseException($e);
        }
    }

    /**
     * SUCCESS (200) OR ERROR RESPONSE (500), Using Tansaction
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
        } catch (ValidationFailException $e) {
            \DB::rollback();
            $err_message = json_decode($e->getMessage(), true);
            return $this->responseBadRequest(
                [
                    'error' => $err_message
                ]
            );
        } catch (HttpErrorException $e) {
            \DB::rollback();
            $err_message = $e->getMessage();
            return $this->responseHttpError(
                $e->getCode(),
                [
                    'error' => $err_message,
                ],
                null,
                $err_message
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \DB::rollback();
            $err_message = "Data Not Found!";
            return $this->responseNotFound(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (BadRequestException $e) {
            \DB::rollback();
            $err_message = $e->getMessage();
            return $this->responseBadRequest(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (UnauthorizedException $e) {
            \DB::rollback();
            $err_message = $e->getMessage();
            return $this->responseUnauthorized(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (ForbiddenException $e) {
            \DB::rollback();
            $err_message = $e->getMessage();
            return $this->responseForbidden(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (NotFoundException $e) {
            \DB::rollback();
            $err_message = $e->getMessage();
            return $this->responseNotFound(
                [
                    'error' => $err_message
                ],
                null,
                $err_message
            );
        } catch (ServerErrorException $e) {
            \DB::rollback();
            return $this->responseException($e);
        } catch (Exception $e) {
            \DB::rollback();
            return $this->responseException($e);
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
}
