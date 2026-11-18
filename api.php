<?php
/**
 * REST API for Aquarium Maintenance Tracker
 * Handles all data operations
 */

// Prevent any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

// Load environment configuration
$config = require __DIR__ . '/env.php';
require_once __DIR__ . '/auth.php';

if (!isAuthenticated($config)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Set headers for JSON API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
function getDbConnection() {
    global $config;
    try {
        $pdo = new PDO(
            "mysql:host={$config['DB_HOST']};dbname={$config['DB_NAME']};charset=utf8mb4",
            $config['DB_USER'],
            $config['DB_PASS'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed', 'message' => $e->getMessage()]);
        exit;
    }
}

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$request = $_GET['endpoint'] ?? '';

// Route the request
try {
    $pdo = getDbConnection();
    
    switch ($request) {
        case 'activities':
            if ($method === 'GET') {
                getActivities($pdo);
            }
            break;
            
        case 'latest_maintenance':
            if ($method === 'GET') {
                getLatestMaintenance($pdo);
            }
            break;
            
        case 'inventory':
            if ($method === 'GET') {
                getInventoryStatus($pdo);
            }
            break;
            
        case 'record_maintenance':
            if ($method === 'POST') {
                recordMaintenance($pdo);
            }
            break;
            
        case 'update_inventory':
            if ($method === 'POST') {
                updateInventory($pdo);
            }
            break;
            
        case 'maintenance_history':
            if ($method === 'GET') {
                getMaintenanceHistory($pdo);
            }
            break;
            
        case 'upcoming_maintenance':
            if ($method === 'GET') {
                getUpcomingMaintenance($pdo);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'message' => $e->getMessage()]);
}

// ================================================================
// API Functions
// ================================================================

/**
 * Get all maintenance activities
 */
function getActivities($pdo) {
    $stmt = $pdo->query("SELECT * FROM maintenance_activities ORDER BY code");
    $activities = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $activities]);
}

/**
 * Get latest maintenance status for all activities (Dashboard view)
 */
function getLatestMaintenance($pdo) {
    $sql = "SELECT 
                ma.id,
                ma.code,
                ma.description,
                ma.frequency_weeks,
                ma.is_filter_material,
                ma.material_group,
                ml.performed_date AS last_performed,
                ml.next_due_date,
                DATEDIFF(ml.next_due_date, CURRENT_DATE()) AS days_until_due,
                CASE 
                    WHEN ml.next_due_date IS NULL THEN 'never_done'
                    WHEN DATEDIFF(ml.next_due_date, CURRENT_DATE()) < 0 THEN 'overdue'
                    WHEN DATEDIFF(ml.next_due_date, CURRENT_DATE()) <= 6 THEN 'due_soon'
                    ELSE 'ok'
                END AS status,
                CASE 
                    WHEN ma.is_filter_material THEN fi.current_stock
                    ELSE NULL
                END AS current_stock
            FROM maintenance_activities ma
            LEFT JOIN (
                SELECT 
                    activity_id,
                    performed_date,
                    next_due_date,
                    ROW_NUMBER() OVER (PARTITION BY activity_id ORDER BY performed_date DESC) as rn
                FROM maintenance_log
            ) ml ON ma.id = ml.activity_id AND ml.rn = 1
            LEFT JOIN filter_inventory fi ON ma.material_group = fi.material_group AND ma.is_filter_material = 1
            ORDER BY 
                CASE 
                    WHEN ml.next_due_date IS NULL THEN 999999
                    ELSE DATEDIFF(ml.next_due_date, CURRENT_DATE()) 
                END ASC, 
                ma.code";
    
    $stmt = $pdo->query($sql);
    $activities = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $activities]);
}

/**
 * Get inventory status for filter materials
 */
function getInventoryStatus($pdo) {
    $sql = "SELECT 
                fi.material_group,
                fi.material_name,
                fi.current_stock,
                fi.min_stock_alert,
                CASE 
                    WHEN fi.current_stock <= 0 THEN 'out_of_stock'
                    WHEN fi.current_stock <= fi.min_stock_alert THEN 'low_stock'
                    ELSE 'in_stock'
                END AS stock_status,
                fi.unit,
                fi.last_updated,
                GROUP_CONCAT(ma.code ORDER BY ma.code SEPARATOR ', ') as activity_codes
            FROM filter_inventory fi
            LEFT JOIN maintenance_activities ma ON fi.material_group = ma.material_group
            GROUP BY fi.id, fi.material_group, fi.material_name, fi.current_stock, fi.min_stock_alert, fi.unit, fi.last_updated
            ORDER BY 
                CASE 
                    WHEN fi.current_stock <= 0 THEN 1
                    WHEN fi.current_stock <= fi.min_stock_alert THEN 2
                    ELSE 3
                END,
                fi.material_group";
    
    $stmt = $pdo->query($sql);
    $inventory = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $inventory]);
}

/**
 * Record a maintenance activity
 */
function recordMaintenance($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['code']) || !isset($input['date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields: code, date']);
        return;
    }
    
    $code = $input['code'];
    $date = $input['date'];
    $notes = $input['notes'] ?? null;
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get activity details
        $stmt = $pdo->prepare("SELECT id, frequency_weeks, is_filter_material, material_group FROM maintenance_activities WHERE code = ?");
        $stmt->execute([$code]);
        $activity = $stmt->fetch();
        
        if (!$activity) {
            throw new Exception("Activity not found: $code");
        }
        
        // Calculate next due date
        $nextDueDate = date('Y-m-d', strtotime($date . ' + ' . $activity['frequency_weeks'] . ' weeks'));
        $daysUntilDue = floor((strtotime($nextDueDate) - time()) / 86400);
        
        // Insert maintenance log
        $stmt = $pdo->prepare("
            INSERT INTO maintenance_log (activity_id, performed_date, next_due_date, days_until_due, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $activity['id'],
            $date,
            $nextDueDate,
            $daysUntilDue,
            $notes
        ]);
        
        // If it's a filter material, decrease inventory
        if ($activity['is_filter_material'] && $activity['material_group']) {
            $stmt = $pdo->prepare("
                UPDATE filter_inventory 
                SET current_stock = GREATEST(current_stock - 1, 0)
                WHERE material_group = ?
            ");
            $stmt->execute([$activity['material_group']]);
            
            // Get updated stock
            $stmt = $pdo->prepare("SELECT id, current_stock FROM filter_inventory WHERE material_group = ?");
            $stmt->execute([$activity['material_group']]);
            $inventory = $stmt->fetch();
            
            if ($inventory) {
                // Log the inventory transaction
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity_change, stock_after, notes)
                    VALUES (?, 'usage', -1, ?, ?)
                ");
                $stmt->execute([
                    $inventory['id'],
                    $inventory['current_stock'],
                    "Used for maintenance on $date"
                ]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Maintenance recorded successfully']);
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update inventory stock
 */
function updateInventory($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['code']) || !isset($input['quantity']) || !isset($input['type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: code, quantity, type']);
        return;
    }
    
    $code = $input['code'];
    $quantity = (int)$input['quantity'];
    $type = $input['type'];
    $notes = $input['notes'] ?? null;
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get activity and inventory by material_group
        $stmt = $pdo->prepare("
            SELECT ma.id as activity_id, ma.material_group, fi.id as inventory_id
            FROM maintenance_activities ma
            JOIN filter_inventory fi ON ma.material_group = fi.material_group
            WHERE ma.code = ?
        ");
        $stmt->execute([$code]);
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new Exception("Activity or inventory not found: $code");
        }
        
        // Update stock
        $stmt = $pdo->prepare("
            UPDATE filter_inventory 
            SET current_stock = current_stock + ?
            WHERE material_group = ?
        ");
        $stmt->execute([$quantity, $result['material_group']]);
        
        // Get new stock level
        $stmt = $pdo->prepare("SELECT current_stock FROM filter_inventory WHERE material_group = ?");
        $stmt->execute([$result['material_group']]);
        $newStock = $stmt->fetchColumn();
        
        // Log transaction
        $stmt = $pdo->prepare("
            INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity_change, stock_after, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $result['inventory_id'],
            $type,
            $quantity,
            $newStock,
            $notes
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Inventory updated successfully']);
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get maintenance history for a specific activity or all activities
 */
function getMaintenanceHistory($pdo) {
    $activityId = $_GET['activity_id'] ?? null;
    $limit = $_GET['limit'] ?? 50;
    
    if ($activityId) {
        $stmt = $pdo->prepare("
            SELECT ml.*, ma.code, ma.description
            FROM maintenance_log ml
            JOIN maintenance_activities ma ON ml.activity_id = ma.id
            WHERE ml.activity_id = ?
            ORDER BY ml.performed_date DESC
            LIMIT ?
        ");
        $stmt->execute([$activityId, $limit]);
    } else {
        $stmt = $pdo->prepare("
            SELECT ml.*, ma.code, ma.description
            FROM maintenance_log ml
            JOIN maintenance_activities ma ON ml.activity_id = ma.id
            ORDER BY ml.performed_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
    }
    
    $history = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $history]);
}

/**
 * Get upcoming maintenance (next N days)
 */
function getUpcomingMaintenance($pdo) {
    $daysAhead = $_GET['days'] ?? 30;
    
    $sql = "SELECT 
                ma.code,
                ma.description,
                ml.performed_date AS last_performed,
                ml.next_due_date,
                DATEDIFF(ml.next_due_date, CURRENT_DATE()) AS days_until_due,
                CASE 
                    WHEN ml.next_due_date IS NULL THEN 'never_done'
                    WHEN DATEDIFF(ml.next_due_date, CURRENT_DATE()) < 0 THEN 'overdue'
                    WHEN DATEDIFF(ml.next_due_date, CURRENT_DATE()) <= 6 THEN 'due_soon'
                    ELSE 'ok'
                END AS status
            FROM maintenance_activities ma
            LEFT JOIN (
                SELECT 
                    activity_id,
                    performed_date,
                    next_due_date,
                    ROW_NUMBER() OVER (PARTITION BY activity_id ORDER BY performed_date DESC) as rn
                FROM maintenance_log
            ) ml ON ma.id = ml.activity_id AND ml.rn = 1
            WHERE DATEDIFF(ml.next_due_date, CURRENT_DATE()) <= ?
            ORDER BY DATEDIFF(ml.next_due_date, CURRENT_DATE()) ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$daysAhead]);
    $upcoming = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $upcoming]);
}
