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
    $soil_type = !empty($queryParams['soil_type']) ? trim($queryParams['soil_type']) : null;
    
    // Debug logging
    error_log("Filter values - crop_name: " . ($crop_name ?? 'null') . ", month: " . ($month ?? 'null') . ", soil_type: " . ($soil_type ?? 'null'));
    
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
    
    // Build query to find fertilizers that match the filters
    $query = "SELECT DISTINCT fs.id, fs.english_name, fs.tamil_name, fs.image 
              FROM fertilizer_suggestion fs WHERE 1 = 1";
    
    $countQuery = "SELECT COUNT(DISTINCT fs.id) as total_count 
                   FROM fertilizer_suggestion fs WHERE 1 = 1";
    
    // Apply crop filter
    if ($crop_name) {
        $query .= " AND fs.id IN (
            SELECT DISTINCT fertilizer_suggestion_id 
            FROM fertilizer_suggestion_crops 
            WHERE (english_crop_name LIKE ? OR tamil_crop_name LIKE ?)
        )";
        
        $countQuery .= " AND fs.id IN (
            SELECT DISTINCT fertilizer_suggestion_id 
            FROM fertilizer_suggestion_crops 
            WHERE (english_crop_name LIKE ? OR tamil_crop_name LIKE ?)
        )";
        
        $params[] = "%$crop_name%";
        $params[] = "%$crop_name%";
    }
    
    // Apply month filter
    if ($month) {
        $query .= " AND fs.id IN (
            SELECT fertilizer_suggestion_id 
            FROM fertilizer_suggestion_months 
            WHERE month = ?
        )";
        
        $countQuery .= " AND fs.id IN (
            SELECT fertilizer_suggestion_id 
            FROM fertilizer_suggestion_months 
            WHERE month = ?
        )";
        
        $params[] = $month;
    }
    
    // Apply soil type filter - using exact match
    if ($soil_type) {
        $query .= " AND fs.id IN (
            SELECT DISTINCT fertilizer_suggestion_id 
            FROM fertilizer_suggestion_soil_types 
            WHERE (english_soil_type = ? OR tamil_soil_type = ?)
        )";
        
        $countQuery .= " AND fs.id IN (
            SELECT DISTINCT fertilizer_suggestion_id 
            FROM fertilizer_suggestion_soil_types 
            WHERE (english_soil_type = ? OR tamil_soil_type = ?)
        )";
        
        $params[] = $soil_type;
        $params[] = $soil_type;
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
        
        // Execute main query
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted_results = [];
        
        // FIXED SECTION: For each fertilizer, get COMPLETE details (not filtered details)
        foreach ($results as $result) {
            $fertilizerId = $result['id'];
            
            // Get ALL months for this fertilizer (NO CONDITIONAL FILTERING)
            $monthQuery = "SELECT DISTINCT month FROM fertilizer_suggestion_months WHERE fertilizer_suggestion_id = ? ORDER BY month";
            $monthStmt = $db->prepare($monthQuery);
            $monthStmt->execute([$fertilizerId]);
            $months = array_map('intval', $monthStmt->fetchAll(PDO::FETCH_COLUMN));
            
            // Get ALL soil types for this fertilizer (NO CONDITIONAL FILTERING)
            $soilQuery = "SELECT DISTINCT english_soil_type, tamil_soil_type FROM fertilizer_suggestion_soil_types WHERE fertilizer_suggestion_id = ? ORDER BY english_soil_type";
            $soilStmt = $db->prepare($soilQuery);
            $soilStmt->execute([$fertilizerId]);
            $soilTypes = [];
            while ($soilRow = $soilStmt->fetch(PDO::FETCH_ASSOC)) {
                $soilTypes[] = [
                    'english' => $soilRow['english_soil_type'],
                    'tamil' => $soilRow['tamil_soil_type']
                ];
            }
            
            // Get ALL crop names for this fertilizer (NO CONDITIONAL FILTERING)
            $cropQuery = "SELECT DISTINCT english_crop_name, tamil_crop_name FROM fertilizer_suggestion_crops WHERE fertilizer_suggestion_id = ? ORDER BY english_crop_name";
            $cropStmt = $db->prepare($cropQuery);
            $cropStmt->execute([$fertilizerId]);
            $cropNames = [];
            while ($cropRow = $cropStmt->fetch(PDO::FETCH_ASSOC)) {
                $cropNames[] = [
                    'english' => $cropRow['english_crop_name'],
                    'tamil' => $cropRow['tamil_crop_name']
                ];
            }
            
            $formatted_results[] = [
                'id' => (string)$result['id'],
                'english_name' => $result['english_name'],
                'tamil_name' => $result['tamil_name'],
                'image' => $result['image'],
                'months' => $months,
                'soil_types' => $soilTypes,
                'crop_names' => $cropNames
            ];
        }
        
        // Prepare pagination response
        $paginationResponse = [
            'total_count' => $totalCount,
            'current_page' => $returnAllRecords ? 1 : $page,
            'per_page' => $perPage
        ];
        
        // Add total_pages only if pagination is used
        if (!$returnAllRecords) {
            $totalPages = ceil($totalCount / $perPage);
            $paginationResponse['total_pages'] = $totalPages;
        } else {
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
        error_log("Error in filter-fertilizers: " . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'Internal server error']));
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
});


$app->get('/fertilizer-soil-types', function (Request $request, Response $response) {
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
            FROM fertilizer_suggestion_soil_types 
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
        error_log("Error in fertilizer-soil-types: " . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'Internal server error']));
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
});


?>