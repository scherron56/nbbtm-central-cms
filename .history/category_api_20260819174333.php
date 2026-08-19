<?php
// category_api.php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/db.php';
require_once 'include/auth.php'; // Ensures authentication middleware is active[cite: 6]

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        // --- FETCH CATEGORIES (By Entity or All) ---
        case 'get_categories':
            $entityType = trim($_GET['entity_type'] ?? '');
            
            if (!empty($entityType)) {
                $stmt = $db->prepare("SELECT doc_category_id, entity_type, category_name, description, is_active FROM doc_categories WHERE entity_type = ? ORDER BY category_name ASC");
                $stmt->bind_param("s", $entityType);
            } else {
                $stmt = $db->prepare("SELECT doc_category_id, entity_type, category_name, description, is_active FROM doc_categories ORDER BY entity_type ASC, category_name ASC");
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode(['success' => true, 'categories' => $result->fetch_all(MYSQLI_ASSOC)]);
            $stmt->close();
            break;

        // --- FETCH SINGLE CATEGORY ---
        case 'get_category':
            $id = intval($_GET['doc_category_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM doc_categories WHERE doc_category_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $cat = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            echo json_encode(['success' => true, 'category' => $cat]);
            break;

        // --- SAVE OR UPDATE CATEGORY (Admin Only) ---
        case 'save_category':
            requireAdmin(); // Enforce admin check[cite: 6]

            $id = !empty($_POST['doc_category_id']) ? intval($_POST['doc_category_id']) : null;
            $entityType = trim($_POST['entity_type'] ?? '');
            $categoryName = trim($_POST['category_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (empty($entityType) || empty($categoryName)) {
                echo json_encode(['success' => false, 'error' => 'Entity type and Category Name are required.']);
                break;
            }

            if (empty($id)) {
                $stmt = $db->prepare("INSERT INTO doc_categories (entity_type, category_name, description, is_active) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $entityType, $categoryName, $description, $isActive);
                $stmt->execute();
                $id = $db->insert_id;
                $stmt->close();
                echo json_encode(['success' => true, 'message' => 'Category created successfully.', 'doc_category_id' => $id]);
            } else {
                $stmt = $db->prepare("UPDATE doc_categories SET entity_type = ?, category_name = ?, description = ?, is_active = ? WHERE doc_category_id = ?");
                $stmt->bind_param("sssii", $entityType, $categoryName, $description, $isActive, $id);
                $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => true, 'message' => 'Category updated successfully.', 'doc_category_id' => $id]);
            }
            break;

        // --- DELETE / DEACTIVATE CATEGORY (Admin Only) ---
        case 'delete_category':
            requireAdmin();[cite: 6]

            $id = intval($_POST['doc_category_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM doc_categories WHERE doc_category_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['success' => true, 'message' => 'Category removed successfully.']);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action requested.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}