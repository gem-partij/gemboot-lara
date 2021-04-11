<?php
namespace Gemboot\Traits;

use Gemboot\Exceptions\BadRequestException;
use Gemboot\Exceptions\UnauthorizedException;
use Gemboot\Exceptions\ForbiddenException;
use Gemboot\Exceptions\NotFoundException;

use Gemboot\Exceptions\ServerErrorException;

trait JSONResponses
{
    public static $STATUS_OK = 200;

    public static $STATUS_BAD_REQUEST = 400;
    public static $STATUS_UNAUTHORIZED = 401;
    public static $STATUS_FORBIDDEN = 403;
    public static $STATUS_NOT_FOUND = 404;

    public static $STATUS_SERVER_ERROR = 500;

    protected function encapsulateResponse($status, $data, $message = null)
    {
        return [
            'status' => $status,
            'message' => empty($message) ? $status : $message,
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
    protected function response($status, $data, $message = null)
    {
        try {
            $accept_encoding = request()->header('accept-encoding');

            $headers = [
                'Content-type' => 'application/json; charset=utf-8',
            ];

            if (substr_count($accept_encoding, "gzip")) {
                $headers['Content-Encoding'] = 'gzip';
                ob_start('ob_gzhandler');
            } else {
                // $headers['Transfer-Encoding'] = 'chunk';
                ob_start();
            }

            $encapsulated = $this->encapsulateResponse(
                $status,
                $data,
                $message
            );

            if (! empty($this->logAccessTag) && request()->isMethod('GET')) {
                log_access($this->logAccessTag);
            }

            return response()->json(
                $encapsulated,
                $status
            )->withHeaders($headers);
        } catch (\Exception $e) {
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
    public function responseSuccess($data= [], $message= 'Success!')
    {
        // $data['message'] = $message;
        return $this->response(static::$STATUS_OK, $data, $message);
    }

    /**
     * BAD REQUEST RESPONSE (400)
     *
     * @param array $data response data
     * @param string $message (default:'Bad Request!')
     *
     * @return json
     */
    public function responseBadRequest($data= [], $message= 'Bad Request!')
    {
        // $data['message'] = $message;
        return $this->response(static::$STATUS_BAD_REQUEST, $data, $message);
    }

    /**
     * UNAUTHORIZED RESPONSE (401)
     *
     * @param array $data response data
     * @param string $message (default:'Unauthorized!')
     *
     * @return json
     */
    public function responseUnauthorized($data= [], $message= 'Unauthorized!')
    {
        // $data['message'] = $message;
        return $this->response(static::$STATUS_UNAUTHORIZED, $data, $message);
    }

    /**
     * FORBIDDEN RESPONSE (403)
     *
     * @param array $data response data
     * @param string $message (default:'Forbidden!')
     *
     * @return json
     */
    public function responseForbidden($data= [], $message= 'Forbidden!')
    {
        // $data['message'] = $message;
        return $this->response(static::$STATUS_FORBIDDEN, $data, $message);
    }

    /**
     * BAD REQUEST RESPONSE (404)
     *
     * @param array $data response data
     * @param string $message (default:'Not Found!')
     *
     * @return json
     */
    public function responseNotFound($data= [], $message= 'Not Found!')
    {
        // $data['message'] = $message;
        return $this->response(static::$STATUS_NOT_FOUND, $data, $message);
    }

    /**
     * ERROR RESPONSE (500)
     *
     * @param array $data response data
     * @param string $message (default:'Error!')
     *
     * @return json
     */
    public function responseError($data= [], $message= 'Server Error!')
    {
        // $data['message'] = $message;
        return $this->response(static::$STATUS_SERVER_ERROR, $data, $message);
    }

    /**
     * ERROR RESPONSE (500)
     *
     * @param \Exception $exception exception
     *
     * @return json
     */
    public function responseException($exception)
    {
        \Log::error($exception->getMessage());
        \Log::error($exception->getTraceAsString());

        if (env('APP_DEBUG')) {
            return $this->responseError([
                'error' => $exception->getMessage(),
                'trace' => $exception->getTrace()
            ]);
        } else {
            return $this->responseError([
                'error' => $exception->getMessage(),
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
    public function responseSuccessOrException(callable $callback)
    {
        try {
            $data = $callback();
            return $this->responseSuccess($data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->responseNotFound([
                // 'error' => $e->getMessage()
                'error' => "Data Not Found!"
            ]);
        } catch (BadRequestException $e) {
            return $this->responseBadRequest([
                'error' => $e->getMessage()
            ]);
        } catch (UnauthorizedException $e) {
            return $this->responseUnauthorized([
                'error' => $e->getMessage()
            ]);
        } catch (ForbiddenException $e) {
            return $this->responseForbidden([
                'error' => $e->getMessage()
            ]);
        } catch (NotFoundException $e) {
            return $this->responseNotFound([
                'error' => $e->getMessage()
            ]);
        } catch (ServerErrorException $e) {
            return $this->responseException($e);
        } catch (\Exception $e) {
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
    public function responseSuccessOrExceptionUsingTransaction(callable $callback)
    {
        \DB::beginTransaction();
        try {
            $data = $callback();
            \DB::commit();
            return $this->responseSuccess($data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \DB::rollback();
            return $this->responseNotFound([
                // 'error' => $e->getMessage()
                'error' => "Data Not Found!"
            ]);
        } catch (BadRequestException $e) {
            \DB::rollback();
            return $this->responseBadRequest([
                'error' => $e->getMessage()
            ]);
        } catch (UnauthorizedException $e) {
            \DB::rollback();
            return $this->responseUnauthorized([
                'error' => $e->getMessage()
            ]);
        } catch (ForbiddenException $e) {
            \DB::rollback();
            return $this->responseForbidden([
                'error' => $e->getMessage()
            ]);
        } catch (NotFoundException $e) {
            \DB::rollback();
            return $this->responseNotFound([
                'error' => $e->getMessage()
            ]);
        } catch (ServerErrorException $e) {
            \DB::rollback();
            return $this->responseException($e);
        } catch (\Exception $e) {
            \DB::rollback();
            return $this->responseException($e);
        }
    }
}
