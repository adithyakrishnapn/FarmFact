<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Services\Mailer;

$secret = $_ENV['JWT_SECRET'];


$app->post('/signup', function ($request, $response) use ($container) {
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
});


$app->post('/login', function (Request $request, Response $response) use ($secret) {
    $db = getDB();
    $data = $request->getParsedBody();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($data['password'], $user['password'])) {
        $response->getBody()->write(json_encode(['msg' => 'Invalid credentials']));
        return $response->withStatus(401);
    }

    $payload = [
        'id' => $user['id'],
        'role' => $user['role'],
        'exp' => time() + (7 * 24 * 60 * 60)
    ];
    $jwt = JWT::encode($payload, $secret, 'HS256');

    $response->getBody()->write(json_encode(['token' => $jwt]));
    return $response->withHeader('Content-Type', 'application/json');
});







