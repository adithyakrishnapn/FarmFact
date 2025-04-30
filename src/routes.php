<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Services\Mailer;

$secret = $_ENV['JWT_SECRET'];


/**$app->post('/signup', function ($request, $response) use ($container) {
    $db = getDB();
    $data = $request->getParsedBody();
    $name = $data['name'];
    $email = $data['email'];
    $password = $data['password'];
    $role = $data['role'];

    // Validate email domain
    $allowedDomains = ['gmail.com', 'yahoo.com', 'outlook.com'];
    $domain = explode('@', $email)[1] ?? '';

    if (!in_array(strtolower($domain), $allowedDomains)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid email domain']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // Check if email already exists in DB
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'Email already exists']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
    }

    // Check if username already exists in DB (for 'name' field)
    $stmt = $db->prepare("SELECT id FROM users WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        $response->getBody()->write(json_encode(['error' => 'Username already exists']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Insert new user into DB
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$name, $email, $hashedPassword, $role]);

    // Generate OTP
    $otp = rand(100000, 999999);
    $stmt = $db->prepare("INSERT INTO otp_verifications (email, otp, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$email, $otp]);

    // Send OTP
    $mailer = $container->get(Mailer::class);
    $sent = $mailer->sendOTP($email, $otp);

    if (!$sent) {
        $response->getBody()->write(json_encode(['error' => 'Failed to send OTP']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    $response->getBody()->write(json_encode(['message' => 'Signup successful, OTP sent to email']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
}); **/


$app->post('/login', function (Request $request, Response $response) use ($secret) {
    $db = getDB();
    $data = $request->getParsedBody();
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
        'exp' => time() + (7 * 24 * 60 * 60)
    ];
    $jwt = JWT::encode($payload, $secret, 'HS256');

    $response->getBody()->write(json_encode([
        'token' => $jwt,
        'role' => $user['role']   // <-- sending role along with token
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
$app->post('/change-password', function (Request $request, Response $response) {
    $db = getDB();
    $data = $request->getParsedBody();
    $email = $data['email'] ?? null;
    $otp = $data['otp'] ?? null;
    $newPassword = $data['newPassword'] ?? null;

    if (!$email || !$otp || !$newPassword) {
        $response->getBody()->write(json_encode(['message' => 'Email, OTP, and new password are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Validate OTP
    $stmt = $db->prepare("SELECT * FROM otp_verifications 
                          WHERE email = ? AND otp = ? AND purpose = 'password_reset' 
                          AND created_at >= NOW() - INTERVAL 10 MINUTE");
    $stmt->execute([$email, $otp]);
    $otpData = $stmt->fetch();

    if (!$otpData) {
        $response->getBody()->write(json_encode(['message' => 'Invalid or expired OTP']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Update password
    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashed, $email]);

    // Delete OTP after successful password reset
    $stmt = $db->prepare("DELETE FROM otp_verifications WHERE email = ? AND purpose = 'password_reset'");
    $stmt->execute([$email]);

    $response->getBody()->write(json_encode(['message' => 'Password changed successfully']));
    return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
});

$app->post('/language', function (Request $request, Response $response) {
    $db = getDB();
    $data = $request->getParsedBody();
    $language = $data['language'] ?? null;
    $userId = $data['id'] ?? null;

    if (!$language || !$userId) {
        $response->getBody()->write(json_encode(['message' => 'Language and user ID are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Update language preference in the database
    $stmt = $db->prepare("UPDATE users SET pref_lang  = ? WHERE id = ?");
    $stmt->execute([$language, $userId]);

    $response->getBody()->write(json_encode([
        'message' => 'Language preference updated successfully',
        'language' => $language,
        'userId' => $userId
    ]));
    return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
});


$app->post('/filter-crops', function (Request $request, Response $response) {
    $db = getDB();
    $data = $request->getParsedBody();
    
    // Get filter values from the POST data
    $crop_name = $data['crop_name'] ?? null;
    $month = $data['month'] ?? null;
    $climate = $data['climate'] ?? null;
    $soil_type = $data['soil_type'] ?? null;
    
    // Pagination parameters
    $page = isset($data['page']) ? (int)$data['page'] : 1;
    $perPage = isset($data['perPage']) ? (int)$data['perPage'] : 10;
    
    // Calculate offset
    $offset = ($page - 1) * $perPage;
    
    // Initialize parameters array
    $params = [];
    
    // Base query with aggregation of months, soil types, and climatic conditions
    $query = "
        SELECT cs.id, cs.english_name, cs.tamil_name,
               GROUP_CONCAT(DISTINCT csm.month ORDER BY csm.month) AS months,
               GROUP_CONCAT(DISTINCT csst.english_soil_type ORDER BY csst.english_soil_type) AS soil_types,
               GROUP_CONCAT(DISTINCT csc.english_climatic_condition ORDER BY csc.english_climatic_condition) AS climatic_conditions
        FROM crop_suggestion cs
        LEFT JOIN crop_suggestion_months csm ON cs.id = csm.crop_suggestion_id
        LEFT JOIN crop_suggestion_soil_types csst ON cs.id = csst.crop_suggestion_id
        LEFT JOIN crop_suggestion_climatic_condition csc ON cs.id = csc.crop_suggestion_id
        WHERE 1 = 1
    ";
    
    // Apply filters if provided
    if ($crop_name) {
        $query .= " AND cs.english_name LIKE ?";
        $params[] = "%$crop_name%";
    }
    if ($month) {
        $query .= " AND csm.month = ?";
        $params[] = $month;
    }
    if ($soil_type) {
        $query .= " AND csst.english_soil_type LIKE ?";
        $params[] = "%$soil_type%";
    }
    if ($climate) {
        $query .= " AND csc.english_climatic_condition LIKE ?";
        $params[] = "%$climate%";
    }
    
    // Group by crop suggestion ID
    $query .= " GROUP BY cs.id";
    
    // Count total results for pagination (must be done before LIMIT is added)
    $countQuery = "
        SELECT COUNT(DISTINCT cs.id) as total_count
        FROM crop_suggestion cs
        LEFT JOIN crop_suggestion_months csm ON cs.id = csm.crop_suggestion_id
        LEFT JOIN crop_suggestion_soil_types csst ON cs.id = csst.crop_suggestion_id
        LEFT JOIN crop_suggestion_climatic_condition csc ON cs.id = csc.crop_suggestion_id
        WHERE 1 = 1
    ";
    
    // Apply the same filters to count query
    if ($crop_name) {
        $countQuery .= " AND cs.english_name LIKE ?";
    }
    if ($month) {
        $countQuery .= " AND csm.month = ?";
    }
    if ($soil_type) {
        $countQuery .= " AND csst.english_soil_type LIKE ?";
    }
    if ($climate) {
        $countQuery .= " AND csc.english_climatic_condition LIKE ?";
    }
    
    // Execute count query
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalCount = isset($countResult['total_count']) ? (int)$countResult['total_count'] : 0;
    $totalPages = ceil($totalCount / $perPage);
    
    // Add pagination to main query - using direct values, not parameters
    $query .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
    
    // Execute main query
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process the results to turn concatenated strings into arrays
    $formatted_results = [];
    foreach ($results as $row) {
        $formatted_results[] = [
            'id' => $row['id'],
            'english_name' => $row['english_name'],
            'tamil_name' => $row['tamil_name'],
            'months' => !empty($row['months']) ? explode(',', $row['months']) : [],
            'soil_types' => !empty($row['soil_types']) ? explode(',', $row['soil_types']) : [],
            'climatic_conditions' => !empty($row['climatic_conditions']) ? explode(',', $row['climatic_conditions']) : []
        ];
    }
    
    // If no results are found
    if (empty($formatted_results)) {
        $response->getBody()->write(json_encode(['message' => 'No crops found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    
    // Return the results with pagination info
    $response->getBody()->write(json_encode([
        'data' => $formatted_results,
        'pagination' => [
            'total_count' => $totalCount,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'per_page' => $perPage
        ]
    ]));
    
    return $response->withHeader('Content-Type', 'application/json');
});
