<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

/**
 * Validate JWT token and return user ID
 * 
 * @param string $token JWT token to validate
 * @return int|false User ID if valid, false if invalid
 */
function validateTokenAndGetUserId($token) {
    global $secret;
    
    if (empty($token)) {
        return false;
    }
    
    try {
        // Decode the JWT token
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        
        // Check if token has expired (JWT library handles this automatically)
        // Return user ID from the token payload
        return isset($decoded->id) ? (int)$decoded->id : false;
        
    } catch (ExpiredException $e) {
        // Token has expired
        error_log("JWT Token expired: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        // Invalid token or other JWT error
        error_log("JWT Token validation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Simple token validation (returns boolean)
 * 
 * @param string $token JWT token to validate
 * @return bool True if valid, false if invalid
 */
function validateToken($token) {
    return validateTokenAndGetUserId($token) !== false;
}

/**
 * Extract token from Authorization header
 * 
 * @param string $authHeader Authorization header value
 * @return string|false Token if found, false if not found
 */
function extractTokenFromHeader($authHeader) {
    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return false;
    }
    
    return trim($matches[1]);
}

/**
 * Validate authorization header and return user ID
 * 
 * @param string $authHeader Authorization header value
 * @return array Array with 'success' boolean and 'user_id' or 'error' message
 */
function validateAuthHeader($authHeader) {
    $token = extractTokenFromHeader($authHeader);
    
    if (!$token) {
        return [
            'success' => false,
            'error' => 'Unauthorized: Bearer token required'
        ];
    }
    
    $userId = validateTokenAndGetUserId($token);
    
    if (!$userId) {
        return [
            'success' => false,
            'error' => 'Invalid or expired token'
        ];
    }
    
    return [
        'success' => true,
        'user_id' => $userId
    ];
}