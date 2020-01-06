<?php
namespace Gemboot\Traits;

trait GembootHelpers
{
    public function get_unique_request_id()
    {
        if (request()->has('request_id')) {
            return request('request_id');
        }

        $string = date('Y-m-d').'-'.request()->ip();
        if (request()->has('access_token')) {
            $string .= '-'.request('access_token');
        }

        return md5($string);
    }

    public function log_access($request_tag)
    {
        $request_id     = get_unique_request_id();
        $method         = request()->method();
        $url            = request()->fullUrl();
        $request_data   = json_encode(request()->all());
        $request_ip     = request()->ip();
        $environment    = json_encode([
                            'APP_ENV' => env('APP_ENV'),
                            'APP_DEBUG' => env('APP_DEBUG'),
                            'CACHE_DRIVER' => env('CACHE_DRIVER'),
                        ]);
        $response_time  = (microtime(true) - LARAVEL_START);

        return (new \App\Services\LogAccessService())->store([
            'request_id'    => $request_id,
            'request_tag'   => $request_tag,
            'method'        => $method,
            'url'           => $url,
            'request_data'  => $request_data,
            'request_ip'    => $request_ip,
            'environment'   => $environment,
            'response_time' => $response_time,
        ]);
    }

    public function log_access_post($post_id)
    {
        $request_id     = get_unique_request_id();
        $method         = request()->method();
        $url            = request()->fullUrl();
        $request_data   = json_encode(request()->all());
        $request_ip     = request()->ip();
        $environment    = json_encode([
                            'APP_ENV' => env('APP_ENV'),
                            'APP_DEBUG' => env('APP_DEBUG'),
                            'CACHE_DRIVER' => env('CACHE_DRIVER'),
                        ]);
        $response_time  = (microtime(true) - LARAVEL_START);

        return (new \App\Services\LogAccessPostService())->updateOrCreate([
            'request_id'    => $request_id,
            'post_id'       => $post_id,
        ], [
            'method'        => $method,
            'url'           => $url,
            'request_data'  => $request_data,
            'request_ip'    => $request_ip,
            'environment'   => $environment,
            'response_time' => $response_time,
        ]);
    }

    public function log_access_agenda($agenda_id)
    {
        $request_id     = get_unique_request_id();
        $method         = request()->method();
        $url            = request()->fullUrl();
        $request_data   = json_encode(request()->all());
        $request_ip     = request()->ip();
        $environment    = json_encode([
                            'APP_ENV' => env('APP_ENV'),
                            'APP_DEBUG' => env('APP_DEBUG'),
                            'CACHE_DRIVER' => env('CACHE_DRIVER'),
                        ]);
        $response_time  = (microtime(true) - LARAVEL_START);

        return (new \App\Services\LogAccessAgendaService())->updateOrCreate([
            'request_id'    => $request_id,
            'agenda_id'     => $agenda_id,
        ], [
            'method'        => $method,
            'url'           => $url,
            'request_data'  => $request_data,
            'request_ip'    => $request_ip,
            'environment'   => $environment,
            'response_time' => $response_time,
        ]);
    }

    public function get_cache_key($tag, $id, $single_or_group = 'single')
    {
        $single_or_group = ($single_or_group == 'group') ? config('c_cache_observer.cache_group_prefix') : config('c_cache_observer.cache_single_prefix');
        $cacheKey = $tag.'-'.$single_or_group.'-'.$id;
        return $cacheKey;
    }

    public function get_cache_tags($tableName)
    {
        return [
            $tableName,
            $tableName.'-addwith',
        ];
    }
}