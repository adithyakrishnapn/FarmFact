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