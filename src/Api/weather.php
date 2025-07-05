<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Include the token validation functions
require_once __DIR__ . '/../validate_token.php';

// Alternative route for getting weather by coordinates (lat, lon)
$app->get('/weather/coordinates', function (Request $request, Response $response) {
    // Validate authorization header
    $authHeader = $request->getHeaderLine('Authorization');
    $authResult = validateAuthHeader($authHeader);
    
    if (!$authResult['success']) {
        $response->getBody()->write(json_encode(['error' => $authResult['error']]));
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }
    
    $queryParams = $request->getQueryParams();
    
    // Get coordinates
    $lat = !empty($queryParams['lat']) ? floatval($queryParams['lat']) : null;
    $lon = !empty($queryParams['lon']) ? floatval($queryParams['lon']) : null;
    
    // Get optional days parameter (1-10 days, default is 1 for today only)
    $days = isset($queryParams['days']) ? (int)$queryParams['days'] : 1;
    
    // Validate required parameters
    if ($lat === null || $lon === null) {
        $response->getBody()->write(json_encode([
            'error' => 'Missing required parameters. Please provide lat and lon coordinates.'
        ]));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
    
    // Validate coordinate ranges
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        $response->getBody()->write(json_encode([
            'error' => 'Invalid coordinates. Latitude must be between -90 and 90, longitude between -180 and 180.'
        ]));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
    
    // Validate days parameter (WeatherAPI supports 1-10 days for free plan)
    if ($days < 1 || $days > 10) {
        $response->getBody()->write(json_encode([
            'error' => 'Days parameter must be between 1 and 10.'
        ]));
        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json');
    }
    
    // Get WeatherAPI key from environment
    $weatherApiKey = $_ENV['WEATHER_API_KEY'] ?? null;
    
    if (!$weatherApiKey) {
        error_log("Weather API key not found in environment variables");
        $response->getBody()->write(json_encode(['error' => 'Weather service configuration error']));
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
    
    try {
        // Construct location query using coordinates
        $location = "$lat,$lon";
        
        // Debug logging
        error_log("Weather request for coordinates: " . $location . " for " . $days . " days");
        
        // WeatherAPI.com endpoint for current and forecast data
        $apiUrl = "https://api.weatherapi.com/v1/forecast.json";
        $params = [
            'key' => $weatherApiKey,
            'q' => $location,
            'days' => $days,
            'aqi' => 'no',
            'alerts' => 'no'
        ];
        
        $url = $apiUrl . '?' . http_build_query($params);
        
        // Initialize cURL
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: Weather-App/1.0'
            ],
        ]);
        
        $apiResponse = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        
        if ($curlError) {
            error_log("cURL Error: " . $curlError);
            $response->getBody()->write(json_encode(['error' => 'Weather service connection error']));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
        
        if ($httpCode !== 200) {
            error_log("WeatherAPI HTTP Error: " . $httpCode . " - Response: " . $apiResponse);
            $response->getBody()->write(json_encode(['error' => 'Weather service error']));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
        
        $weatherData = json_decode($apiResponse, true);
        
        if (!$weatherData || !isset($weatherData['forecast']['forecastday'])) {
            error_log("Invalid weather data received: " . $apiResponse);
            $response->getBody()->write(json_encode(['error' => 'Invalid weather data received']));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
        
        // Extract temperature data for all requested days starting from 6 AM with 3-hour intervals
        $forecastDays = $weatherData['forecast']['forecastday'];
        $dailyForecasts = [];
        $targetHours = [6, 9, 12, 15, 18, 21];
        
        foreach ($forecastDays as $dayData) {
            $dayDate = $dayData['date'];
            $hourlyData = $dayData['hour'];
            $temperatureHours = [];
            
            foreach ($targetHours as $hour) {
                if (isset($hourlyData[$hour])) {
                    $hourData = $hourlyData[$hour];
                    $temperatureHours[] = [
                        'time' => date('H:i', strtotime($hourData['time'])),
                        'temperature_c' => $hourData['temp_c'],
                        'temperature_f' => $hourData['temp_f']
                    ];
                }
            }
            
            $dailyForecasts[] = [
                'date' => $dayDate,
                'temperature_forecast' => $temperatureHours
            ];
        }
        
        // Prepare response data
        $responseData = [
            'status' => 'success',
            'location' => [
                'name' => $weatherData['location']['name'],
                'region' => $weatherData['location']['region'],
                'country' => $weatherData['location']['country']
            ],
            'forecast' => $dailyForecasts,
            'request_info' => [
                'latitude' => $lat,
                'longitude' => $lon,
                'days_requested' => $days,
                'intervals' => '3 hours',
                'start_time' => '06:00'
            ]
        ];
        
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
        
    } catch (Exception $e) {
        error_log("Error in weather coordinates route: " . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'Internal server error']));
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }
});