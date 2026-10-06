<?php

// Router for PHP's built-in web server, used as a fake central auth service.
//
// - Token "Bearer good" is a valid user with role "admin" and permission "user.read".
//   has-role and has-permission-to answer for any token, like before.
// - Token "Bearer flaky" gets a 500 on its first request and 200 afterwards.
// - Paths under /broken/ always answer 500 (auth service outage).
// - Every request is appended to the file in FAKE_AUTH_LOG, so tests can count calls.

header('Content-Type: application/json');

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$log = getenv('FAKE_AUTH_LOG');

if ($log) {
    file_put_contents($log, $path . ' ' . $token . "\n", FILE_APPEND);
}

$reply = function (int $status, $data) {
    http_response_code($status);
    echo json_encode(['status' => $status, 'message' => $status === 200 ? 'OK' : 'Error', 'data' => $data]);
};

if (str_starts_with($path, 'broken/')) {
    $reply(500, null);
    return;
}

if ($token === 'Bearer flaky' && $log) {
    $marker = $log . '.flaky';
    if (!file_exists($marker)) {
        touch($marker);
        $reply(500, null);
        return;
    }
}

if ($path === 'me' || $path === 'validate-token') {
    if ($token === 'Bearer good' || $token === 'Bearer flaky') {
        $reply(200, ['id' => 1, 'name' => 'Ana']);
    } else {
        $reply(401, null);
    }
    return;
}

if ($path === 'logout') {
    $reply(200, null);
    return;
}

if ($path === 'has-role') {
    $roles = explode('|', $_GET['role_name'] ?? '');
    $reply(200, ['has_role' => in_array('admin', $roles, true)]);
    return;
}

if ($path === 'has-permission-to') {
    $permissions = explode('|', $_GET['permission_name'] ?? '');
    $granted = in_array('user.read', $permissions, true);
    $reply(200, [
        'has_permission_to' => $granted && count($permissions) === 1,
        'has_any_permission' => $granted,
    ]);
    return;
}

$reply(404, null);
