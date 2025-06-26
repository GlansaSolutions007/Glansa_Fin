<?php

// app/Services/JwtService.php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Config;

class JwtService
{
    protected $secret;

    public function __construct()
    {
        $this->secret = env('JWT_SECRET');  // JWT secret from .env file
    }

    // Method to encode data into a JWT token
    public function encode($data)
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + 3600;  // jwt valid for 1 hour from the issued time
        $payload = array(
            "iat" => $issuedAt,
            "exp" => $expirationTime,
            "data" => $data
        );

        return JWT::encode($payload, $this->secret);
    }

    // Method to decode a JWT token
    public function decode($jwt)
    {
        try {
            $decoded = JWT::decode($jwt, $this->secret, array('HS256'));
            return (array) $decoded->data;
        } catch (\Exception $e) {
            return null;  // If the token is invalid, return null
        }
    }

    // Method to validate the token
    public function validate($jwt)
    {
        return $this->decode($jwt) !== null;
    }
}
