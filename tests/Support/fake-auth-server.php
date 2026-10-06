<?php

// Router for PHP's built-in web server, used as a fake central auth service.
// Grants role "admin" and permission "user.read" to any request.

header('Content-Type: application/json');

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

if ($path === 'has-role') {
    $roles = explode('|', $_GET['role_name'] ?? '');
    echo json_encode(['status' => 200, 'message' => 'OK', 'data' => [
        'has_role' => in_array('admin', $roles, true),
    ]]);
    return;
}

if ($path === 'has-permission-to') {
    $permissions = explode('|', $_GET['permission_name'] ?? '');
    $granted = in_array('user.read', $permissions, true);
    echo json_encode(['status' => 200, 'message' => 'OK', 'data' => [
        'has_permission_to' => $granted && count($permissions) === 1,
        'has_any_permission' => $granted,
    ]]);
    return;
}

http_response_code(404);
echo json_encode(['status' => 404, 'message' => 'Not Found', 'data' => null]);
