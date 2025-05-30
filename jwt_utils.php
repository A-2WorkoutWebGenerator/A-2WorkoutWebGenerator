<?php
require_once __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

const JWT_SECRET = 'your-super-secret-key';
const JWT_ISSUER = 'fitgen-app';
const JWT_EXPIRE = 60*60*24;

function create_jwt($user_id, $username, $email) {
    $payload = [
        'iss' => JWT_ISSUER,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRE,
        'sub' => $user_id,
        'username' => $username,
        'email' => $email
    ];
    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

function decode_jwt($jwt) {
    return JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
}
?>