<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Services\Mailer;

// Include the token validation functions
require_once __DIR__ . '/validate_token.php';

$secret = $_ENV['JWT_SECRET'];

$app->get('/fertilizer', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Check fertilizers");
    return $response;
});



$app->get('/filter-fertilizers', function (Request $request, Response $response) {
    // Validate authorization header
    $authHeader = $request->getHeaderLine('Authorization');
    $authResult = validateAuthHeader($authHeader); // Assuming this function exists
    
    if (!$authResult['success']) {
        $response->getBody()->write(json_encode(['error' => $authResult['error']]));
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
    
    $db = getDB(); // Assuming this function exists
    $queryParams = $request->getQueryParams();
    
    // Get filter values from query parameters
    $crop_name = !empty($queryParams['crop_name']) ? trim($queryParams['crop_name']) : null;
    $month = isset($queryParams['month']) && $queryParams['month'] !== '' ? (int)$queryParams['month'] : null;
    $climatic_condition = !empty($queryParams['climatic_condition']) ? trim($queryParams['climatic_condition']) : null;
    
    // Debug logging
    error_log("Recommendation filters - crop_name: " . ($crop_name ?? 'null') . ", month: " . ($month ?? 'null') . ", climatic_condition: " . ($climatic_condition ?? 'null'));
    
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
    $returnAllRecords = ($perPage === -1);
    $offset = $returnAllRecords ? 0 : ($page - 1) * $perPage;
    
    // Initialize parameters array for query binding
    $params = [];
    
    // Base query: Select distinct fertilizers and join with the recommendations and crops tables.
    // We use LEFT JOINs to ensure we can filter flexibly.
    $baseQuery = "
        FROM fertilizer_suggestion fs
        JOIN fertilizer_recommendations fr ON fs.id = fr.fertilizer_suggestion_id
        JOIN crop_suggestion cs ON fr.crop_suggestion_id = cs.id
        WHERE 1 = 1
    ";
    
    // Build the dynamic WHERE clause
    $whereClause = "";
    
    if ($crop_name) {
        $whereClause .= " AND (cs.english_name LIKE ? OR cs.tamil_name LIKE ?)";
        $params[] = "%$crop_name%";
        $params[] = "%$crop_name%";
    }
    
    if ($month) {
        $whereClause .= " AND fr.month = ?";
        $params[] = $month;
    }
    
    if ($climatic_condition) {
        // Assuming climatic_condition is a single text field in the recommendations table
        $whereClause .= " AND fr.climatic_condition = ?";
        $params[] = $climatic_condition;
    }

    // Construct the final queries
    $query = "SELECT DISTINCT fs.id, fs.english_name, fs.tamil_name, fs.image " . $baseQuery . $whereClause;
    $countQuery = "SELECT COUNT(DISTINCT fs.id) as total_count " . $baseQuery . $whereClause;

    try {
        // Execute count query
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();
        
        // Add pagination to main query only if not returning all records
        if (!$returnAllRecords) {
            $query .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }
        
        // Execute main query to get the list of recommended fertilizers
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $fertilizers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare pagination response
        $paginationResponse = [
            'total_count' => $totalCount,
            'current_page' => $returnAllRecords ? 1 : $page,
            'per_page' => $perPage,
        ];

        if (!$returnAllRecords) {
            $paginationResponse['total_pages'] = ceil($totalCount / $perPage);
        } else {
            $paginationResponse['total_pages'] = 1;
        }
        
        // Return the results
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $fertilizers,
            'pagination' => $paginationResponse
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (Exception $e) {
        error_log("Error in recommend-fertilizers: " . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'Internal server error']));
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
});