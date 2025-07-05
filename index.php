<?php
// Display all errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;


// Import required classes
use DI\Container;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Slim\Psr7\Response;

// Load dependencies
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/db.php';

// Set the test cookie that the hosting provider might be looking for
if (!isset($_COOKIE['__test'])) {
    setcookie('__test', '1', time() + 21600, '/', '', false, false);
}

try {
    // Load environment variables
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();
    }

    // Create DI container and app
    $container = new Container();
    AppFactory::setContainer($container);
    $app = AppFactory::create();
    
    // Add middleware for parsing request body
    $app->addBodyParsingMiddleware();
    
    // Add error middleware
    $errorMiddleware = $app->addErrorMiddleware(true, true, true);
    
    // Add CORS middleware with cookie handling
    $app->add(function ($request, $handler) {
        // Pre-flight response for OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response();
            return $response
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        
        // Handle the request
        $response = $handler->handle($request);
        
        // Add CORS headers to the response
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    });
    
    // Handle preflight OPTIONS requests
    $app->options('/{routes:.+}', function ($request, $response, $args) {
        return $response;
    });
    
    // Load routes
    require __DIR__ . '/src/Api/routes.php';
    require __DIR__ . '/src/Api/fertilizer.php';
    require __DIR__ . '/src/Api/weather.php';

    //serve static image files
    $app->get('/images/{filename}', function (RequestInterface $request, ResponseInterface $response, array $args) {
        $filename = basename($args['filename']); // prevent directory traversal
        $filePath = __DIR__ . '/public/images/' . $filename;

        if (!file_exists($filePath)) {
            $response->getBody()->write('File not found');
            return $response->withStatus(404);
        }

        $mimeType = mime_content_type($filePath);
        $response = $response->withHeader('Content-Type', $mimeType);
        $response->getBody()->write(file_get_contents($filePath));

        return $response;
    });


    
    // Run the application
    $app->run();
} catch (Exception $e) {
    // Handle any exceptions
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}