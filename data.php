<?php
// data.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Content-Type: application/json");

// Handle OPTIONS request (preflight for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataDir = 'data/'; // Directory to store JSON files
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? null; // Get ID from query parameter for PUT/DELETE

if (empty($type)) {
    echo json_encode(['success' => false, 'message' => 'Missing data type.']);
    http_response_code(400);
    exit();
}

$filePath = $dataDir . $type . '.json';

// Helper function to read data
function readData($filePath) {
    if (file_exists($filePath)) {
        $json = file_get_contents($filePath);
        return json_decode($json, true) ?: [];
    }
    return [];
}

// Helper function to write data
function writeData($filePath, $data) {
    return file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $currentData = readData($filePath);
        echo json_encode($currentData);
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
            http_response_code(400);
            exit();
        }

        $currentData = readData($filePath);
        
        // Generate a unique ID for the new entry
        // This is a simple UUID-like generation. For production, consider a more robust UUID library.
        $input['id'] = uniqid(); 
        $currentData[] = $input; // Add new entry

        if (writeData($filePath, $currentData)) {
            echo json_encode(['success' => true, 'message' => ucfirst($type) . ' added successfully.', 'id' => $input['id']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add ' . $type . '.']);
            http_response_code(500);
        }
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || $id === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input or missing ID for update.']);
            http_response_code(400);
            exit();
        }

        $currentData = readData($filePath);
        $found = false;
        foreach ($currentData as $key => $item) {
            if (isset($item['id']) && $item['id'] == $id) {
                // Update the item, preserving its ID
                $currentData[$key] = array_merge($item, $input);
                $found = true;
                break;
            }
        }

        if ($found && writeData($filePath, $currentData)) {
            echo json_encode(['success' => true, 'message' => ucfirst($type) . ' updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update ' . $type . ' or ID not found.']);
            http_response_code(404); // Not Found if ID is not found
        }
        break;

    case 'DELETE':
        if ($id === null) {
            echo json_encode(['success' => false, 'message' => 'Missing ID for deletion.']);
            http_response_code(400);
            exit();
        }

        $currentData = readData($filePath);
        $initialCount = count($currentData);
        
        // Filter out the item with the matching ID
        $currentData = array_values(array_filter($currentData, function($item) use ($id) {
            return !isset($item['id']) || $item['id'] != $id;
        }));

        if (count($currentData) < $initialCount && writeData($filePath, $currentData)) {
            echo json_encode(['success' => true, 'message' => ucfirst($type) . ' deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete ' . $type . ' or ID not found.']);
            http_response_code(404); // Not Found if ID is not found
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        http_response_code(405);
        break;
}
?>
