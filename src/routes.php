<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Services\Mailer;

// Include the token validation functions
require_once __DIR__ . '/validate_token.php';

$secret = $_ENV['JWT_SECRET'];

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello from Slim!");
    return $response;
});

$app->post('/login', function (Request $request, Response $response) use ($secret) {
    $db = getDB();
    $data = $request->getParsedBody();

    // Check if both fields are provided
    if (empty($data['username']) || empty($data['password'])) {
        $response->getBody()->write(json_encode(['message' => 'Username and password are required']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
    }

    // Check for whitespace in username
    if (preg_match('/\s/', $data['username'])) {
        $response->getBody()->write(json_encode(['message' => 'Username must not contain spaces']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE name = ?");
    $stmt->execute([$data['username']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($data['password'], $user['password'])) {
        $response->getBody()->write(json_encode(['message' => 'Invalid credentials']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }

    $payload = [
        'id' => $user['id'],
        'role' => $user['role'],
        'exp' => time() + (7 * 24 * 60 * 60) // 1 week
    ];
    $jwt = JWT::encode($payload, $secret, 'HS256');

    $response->getBody()->write(json_encode([
        'token' => $jwt,
        'role' => $user['role'],
        'message' => 'Logged in successfully.'
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

//Send otp for password reset ___ there email will be validated and then otp will be sent to the email
$app->post('/send-otp', function (Request $request, Response $response) {
    $db = getDB();
    $data = $request->getParsedBody();
    $email = $data['email'] ?? null;

    if (!$email) {
        $response->getBody()->write(json_encode(['message' => 'Email is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Check if email exists
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $response->getBody()->write(json_encode(['message' => 'Email not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    // Generate OTP
    $otpCode = rand(100000, 999999);

    // Delete any old OTPs for this purpose
    $stmt = $db->prepare("DELETE FROM otp_verifications WHERE email = ? AND purpose = 'password_reset'");
    $stmt->execute([$email]);

    // Insert new OTP
    $stmt = $db->prepare("INSERT INTO otp_verifications (email, otp, purpose) VALUES (?, ?, 'password_reset')");
    $stmt->execute([$email, $otpCode]);

    // Send OTP
    $mailer = new Mailer();
    $sent = $mailer->sendOTP($email, $otpCode);

    if ($sent) {
        $response->getBody()->write(json_encode(['message' => 'OTP sent']));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } else {
        $response->getBody()->write(json_encode(['message' => 'Failed to send OTP']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// Change password using OTP ___ this will be used to change the password using the otp sent to the email
// and the new password will be set in the database
$app->put('/forgot-password', function (Request $request, Response $response) {
    $db = getDB();
    $data = $request->getParsedBody();
    $email = $data['email'] ?? null;
    $otp = $data['otp'] ?? null;
    $newPassword = $data['newPassword'] ?? null;

    if (!$email || !$otp || !$newPassword) {
        $response->getBody()->write(json_encode(['message' => 'Email, OTP, and new password are required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // Validate OTP
    $stmt = $db->prepare("SELECT * FROM otp_verifications 
                          WHERE email = ? AND otp = ? AND purpose = 'password_reset' 
                          AND created_at >= NOW() - INTERVAL 10 MINUTE");
    $stmt->execute([$email, $otp]);
    $otpData = $stmt->fetch();

    if (!$otpData) {
        $response->getBody()->write(json_encode(['message' => 'Invalid or expired OTP']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // Update password
    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashed, $email]);

    // Delete OTP
    $stmt = $db->prepare("DELETE FROM otp_verifications WHERE email = ? AND purpose = 'password_reset'");
    $stmt->execute([$email]);

    $response->getBody()->write(json_encode(['message' => 'Password changed successfully']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

$app->put('/change-password', function (Request $request, Response $response) use ($secret) {
    $db = getDB();
    $data = $request->getParsedBody();
    
    // Validate authorization header
    $authHeader = $request->getHeaderLine('Authorization');
    $authResult = validateAuthHeader($authHeader);
    
    if (!$authResult['success']) {
        $response->getBody()->write(json_encode(['message' => $authResult['error']]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
    
    $userId = $authResult['user_id'];
    
    // Get and validate input data
    $currentPassword = $data['currentPassword'] ?? null;
    $newPassword = $data['newPassword'] ?? null;
    $confirmPassword = $data['confirmPassword'] ?? null;
    
    // Check if all required fields are provided
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $response->getBody()->write(json_encode(['message' => 'Current password, new password, and confirm password are all required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    
    // Check if new passwords match
    if ($newPassword !== $confirmPassword) {
        $response->getBody()->write(json_encode(['message' => 'New password and confirm password do not match']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    
    // Add password strength validation
    if (strlen($newPassword) < 8) {
        $response->getBody()->write(json_encode(['message' => 'New password must be at least 8 characters long']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    
    try {
        // Fetch current user data from database
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $response->getBody()->write(json_encode(['message' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            $response->getBody()->write(json_encode(['message' => 'Current password is incorrect']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        // Hash the new password
        $hashedNewPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        
        // Update password in database
        $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateResult = $updateStmt->execute([$hashedNewPassword, $userId]);
        
        if (!$updateResult) {
            $response->getBody()->write(json_encode(['message' => 'Failed to update password']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
        
        $response->getBody()->write(json_encode(['message' => 'Password changed successfully']));
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (PDOException $e) {
        error_log("Database error in change-password: " . $e->getMessage());
        $response->getBody()->write(json_encode(['message' => 'Internal Server Error']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    } catch (Exception $e) {
        error_log("General error in change-password: " . $e->getMessage());
        $response->getBody()->write(json_encode(['message' => 'Internal Server Eroor']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

// Get user profile information
$app->get('/profile', function (Request $request, Response $response) use ($secret) {
    // Validate authorization header
    $authHeader = $request->getHeaderLine('Authorization');
    $authResult = validateAuthHeader($authHeader);
    
    if (!$authResult['success']) {
        $response->getBody()->write(json_encode(['error' => $authResult['error']]));
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
    
    $userId = $authResult['user_id'];
    $db = getDB();
    
    try {
        // Get user profile information
        $query = "SELECT id, name, role, email, pref_lang FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }
        
        // Format the response
        $userProfile = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
            'email' => $user['email'],
            'pref_language' => $user['pref_lang']
        ];
        
        // Return success response
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $userProfile
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (Exception $e) {
        error_log("Error in user profile: " . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'Internal server error']));
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
});

$app->put('/language', function (Request $request, Response $response) use ($secret) {
    $db = getDB();

    // Validate authorization header
    $authHeader = $request->getHeaderLine('Authorization');
    $authResult = validateAuthHeader($authHeader);
    
    if (!$authResult['success']) {
        $response->getBody()->write(json_encode(['message' => $authResult['error']]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }
    
    $userId = $authResult['user_id'];

    try {
        $data = $request->getParsedBody();
        $language = strtoupper(trim($data['language'] ?? ''));

        // Validate language
        $validLanguages = ['ENGLISH', 'TAMIL'];
        if (!in_array($language, $validLanguages)) {
            $response->getBody()->write(json_encode(['message' => 'Only ENGLISH or TAMIL is allowed']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Update language in database
        $stmt = $db->prepare("UPDATE users SET pref_lang = ? WHERE id = ?");
        $stmt->execute([$language, $userId]);

        $response->getBody()->write(json_encode([
            'message' => 'Language preference updated successfully'
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        error_log("Error in language update: " . $e->getMessage());
        $response->getBody()->write(json_encode(['message' => 'An error occurred']));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});


// Get all unique soil types for dropdown
$app->get('/soil-types', function (Request $request, Response $response) {
    // Validate authorization header
    $authHeader = $request->getHeaderLine('Authorization');
    $authResult = validateAuthHeader($authHeader);
    
    if (!$authResult['success']) {
        $response->getBody()->write(json_encode(['error' => $authResult['error']]));
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
    
    $db = getDB();
    
    try {
        // Query to get unique soil types with lowest ID for each duplicate
        $query = "
            SELECT 
                MIN(id) as id,
                english_soil_type,
                tamil_soil_type
            FROM crop_suggestion_soil_types 
            GROUP BY english_soil_type, tamil_soil_type
            ORDER BY english_soil_type ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $soilTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the response
        $formatted_results = [];
        foreach ($soilTypes as $soilType) {
            $formatted_results[] = [
                'id' => (int)$soilType['id'],
                'english_name' => $soilType['english_soil_type'],
                'tamil_name' => $soilType['tamil_soil_type']
            ];
        }
        
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $formatted_results,
            'count' => count($formatted_results)
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (Exception $e) {
        error_log("Error in dropdown/soil-types: " . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'Internal server error']));
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
});

// Get all unique climatic conditions for dropdown
$app->get('/climatic-conditions', function (Request $request, Response $response) {
    // Validate authorization header
    $authHeader = $request->getHeaderLine('Authorization');
    $authResult = validateAuthHeader($authHeader);
    
    if (!$authResult['success']) {
        $response->getBody()->write(json_encode(['error' => $authResult['error']]));
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
    
    $db = getDB();
    
    try {
        // Query to get unique climatic conditions with lowest ID for each duplicate
        $query = "
            SELECT 
                MIN(id) as id,
                english_climatic_condition,
                tamil_climatic_condition
            FROM crop_suggestion_climatic_condition 
            GROUP BY english_climatic_condition, tamil_climatic_condition
            ORDER BY english_climatic_condition ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $climaticConditions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the response
        $formatted_results = [];
        foreach ($climaticConditions as $condition) {
            $formatted_results[] = [
                'id' => (int)$condition['id'],
                'english_name' => $condition['english_climatic_condition'],
                'tamil_name' => $condition['tamil_climatic_condition']
            ];
        }
        
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $formatted_results,
            'count' => count($formatted_results)
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (Exception $e) {
        error_log("Error in dropdown/climatic-conditions: " . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'Internal server error']));
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
});



$app->get('/filter-crops', function (Request $request, Response $response) {
    // Validate authorization header
    $authHeader = $request->getHeaderLine('Authorization');
    $authResult = validateAuthHeader($authHeader);
    
    if (!$authResult['success']) {
        $response->getBody()->write(json_encode(['error' => $authResult['error']]));
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
    
    $db = getDB();
    $queryParams = $request->getQueryParams();
    
    // Get filter values from query parameters and handle empty strings
    $crop_name = !empty($queryParams['crop_name']) ? trim($queryParams['crop_name']) : null;
    $month = isset($queryParams['month']) && $queryParams['month'] !== '' ? (int)$queryParams['month'] : null;
    $climate = !empty($queryParams['climatic_condition']) ? trim($queryParams['climatic_condition']) : null;
    $soil_type = !empty($queryParams['soil_type']) ? trim($queryParams['soil_type']) : null;
    
    // Debug logging
    error_log("Filter values - crop_name: " . ($crop_name ?? 'null') . ", month: " . ($month ?? 'null') . ", climate: " . ($climate ?? 'null') . ", soil_type: " . ($soil_type ?? 'null'));
    
    // Validate month parameter
    if ($month !== null && ($month < 1 || $month > 12)) {
        $response->getBody()->write(json_encode(['error' => 'Month must be between 1 and 12']));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
    
    // Pagination parameters
    $page = isset($queryParams['page']) ? (int)$queryParams['page'] : 1;
    $perPage = isset($queryParams['perPage']) ? (int)$queryParams['perPage'] : 10;
    
    // Check if all records should be returned
    $returnAllRecords = ($perPage === -1);
    
    // Calculate offset only if pagination is needed
    $offset = $returnAllRecords ? 0 : ($page - 1) * $perPage;
    
    // Initialize parameters array
    $params = [];
    
    // Build dynamic query with proper filtering
    $query = "SELECT DISTINCT cs.id, cs.english_name, cs.tamil_name, cs.image FROM crop_suggestion cs WHERE 1 = 1";
    $countQuery = "SELECT COUNT(DISTINCT cs.id) as total_count FROM crop_suggestion cs WHERE 1 = 1";
    
    // Apply crop name filter
    if ($crop_name) {
        $query .= " AND (cs.english_name LIKE ? OR cs.tamil_name LIKE ?)";
        $countQuery .= " AND (cs.english_name LIKE ? OR cs.tamil_name LIKE ?)";
        $params[] = "%$crop_name%";
        $params[] = "%$crop_name%";
    }
    
    // Apply month filter
    if ($month) {
        $query .= " AND cs.id IN (SELECT crop_suggestion_id FROM crop_suggestion_months WHERE month = ?)";
        $countQuery .= " AND cs.id IN (SELECT crop_suggestion_id FROM crop_suggestion_months WHERE month = ?)";
        $params[] = $month;
    }
    
    // Apply soil type filter - exact match only
    if ($soil_type) {
        $query .= " AND cs.id IN (
            SELECT crop_suggestion_id 
            FROM crop_suggestion_soil_types 
            WHERE english_soil_type = ? OR tamil_soil_type = ?
        )";
        $countQuery .= " AND cs.id IN (
            SELECT crop_suggestion_id 
            FROM crop_suggestion_soil_types 
            WHERE english_soil_type = ? OR tamil_soil_type = ?
        )";
        $params[] = $soil_type;
        $params[] = $soil_type;
    }
    
    // Apply climate filter - exact match only
    if ($climate) {
        $query .= " AND cs.id IN (
            SELECT crop_suggestion_id 
            FROM crop_suggestion_climatic_condition 
            WHERE english_climatic_condition = ? OR tamil_climatic_condition = ?
        )";
        $countQuery .= " AND cs.id IN (
            SELECT crop_suggestion_id 
            FROM crop_suggestion_climatic_condition 
            WHERE english_climatic_condition = ? OR tamil_climatic_condition = ?
        )";
        $params[] = $climate;
        $params[] = $climate;
    }
    
    try {
        // Debug logging
        error_log("Final query: " . $query);
        error_log("Query params: " . print_r($params, true));
        
        // Execute count query
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $totalCount = isset($countResult['total_count']) ? (int)$countResult['total_count'] : 0;
        
        // Add pagination to main query only if not returning all records
        if (!$returnAllRecords) {
            $query .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }
        
        // Execute main query to get crop IDs
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $crops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted_results = [];
        
        // For each crop, get all its details
        foreach ($crops as $crop) {
            $cropId = $crop['id'];
            
            // Get all months for this crop
            $monthQuery = "SELECT DISTINCT month FROM crop_suggestion_months WHERE crop_suggestion_id = ? ORDER BY month";
            $monthStmt = $db->prepare($monthQuery);
            $monthStmt->execute([$cropId]);
            $months = array_map('intval', $monthStmt->fetchAll(PDO::FETCH_COLUMN));
            
            // Get all soil types for this crop
            $soilQuery = "SELECT DISTINCT english_soil_type, tamil_soil_type FROM crop_suggestion_soil_types WHERE crop_suggestion_id = ? ORDER BY english_soil_type";
            $soilStmt = $db->prepare($soilQuery);
            $soilStmt->execute([$cropId]);
            $soilTypes = [];
            while ($soilRow = $soilStmt->fetch(PDO::FETCH_ASSOC)) {
                $soilTypes[] = [
                    'english' => $soilRow['english_soil_type'],
                    'tamil' => $soilRow['tamil_soil_type']
                ];
            }
            
            // Get all climatic conditions for this crop
            $climateQuery = "SELECT DISTINCT english_climatic_condition, tamil_climatic_condition FROM crop_suggestion_climatic_condition WHERE crop_suggestion_id = ? ORDER BY english_climatic_condition";
            $climateStmt = $db->prepare($climateQuery);
            $climateStmt->execute([$cropId]);
            $climaticConditions = [];
            while ($climateRow = $climateStmt->fetch(PDO::FETCH_ASSOC)) {
                $climaticConditions[] = [
                    'english' => $climateRow['english_climatic_condition'],
                    'tamil' => $climateRow['tamil_climatic_condition']
                ];
            }
            
            $formatted_results[] = [
                'id' => $crop['id'],
                'english_name' => $crop['english_name'],
                'tamil_name' => $crop['tamil_name'],
                'image' => $crop['image'],
                'months' => $months,
                'soil_types' => $soilTypes,
                'climatic_conditions' => $climaticConditions
            ];
        }
        
        // Prepare pagination response
        $paginationResponse = [
            'total_count' => $totalCount,
            'current_page' => $returnAllRecords ? 1 : $page,
            'per_page' => $perPage  // Keep original perPage value (-1 or actual number)
        ];
        
        // Add total_pages only if pagination is used
        if (!$returnAllRecords) {
            $totalPages = ceil($totalCount / $perPage);
            $paginationResponse['total_pages'] = $totalPages;
        } else {
            // When returning all records, total_pages should be 1
            $paginationResponse['total_pages'] = 1;
        }
        
        // Return the results with pagination info
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $formatted_results,
            'pagination' => $paginationResponse
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (Exception $e) {
        error_log("Error in filter-crops: " . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'Internal server error']));
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
});