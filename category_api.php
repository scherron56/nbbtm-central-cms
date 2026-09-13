<?php
// category_api.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/auth.php';

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        case 'get_categories':
            $entityType = trim($_GET['entity_type'] ?? '');
            $onlyActive = isset($_GET['only_active']) && (int)$_GET['only_active'] === 1;
            
            $sql = "SELECT doc_category_id, entity_type, category_name, description, is_active FROM doc_categories WHERE 1=1";
            $types = "";
            $params = [];

            if (!empty($entityType)) {
                $sql .= " AND entity_type = ?";
                $types .= "s";
                $params[] = $entityType;
            }
            if ($onlyActive) {
                $sql .= " AND is_active = 1";
            }
            $sql .= " ORDER BY entity_type ASC, category_name ASC";

            $stmt = $db->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $categories = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo json_encode(
                ['success' => true, 'categories' => $categories],
                JSON_INVALID_UTF8_SUBSTITUTE
            );
            exit;

        case 'get_category':
            $id = (int)($_GET['doc_category_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM doc_categories WHERE doc_category_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $cat = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            echo json_encode(['success' => true, 'category' => $cat]);
            break;

        case 'save_category':
            requireAdmin();

            $id = !empty($_POST['doc_category_id']) ? (int)$_POST['doc_category_id'] : null;
            $entityType = trim($_POST['entity_type'] ?? '');
            $categoryName = trim($_POST['category_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (empty($entityType) || empty($categoryName)) {
                echo json_encode(['success' => false, 'error' => 'Entity Scope and Category Name are required.']);
                break;
            }

            if (empty($id)) {
                $stmt = $db->prepare("INSERT INTO doc_categories (entity_type, category_name, description, is_active) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $entityType, $categoryName, $description, $isActive);
                $stmt->execute();
                $id = $db->insert_id;
                $stmt->close();
                echo json_encode(['success' => true, 'message' => 'Document category created successfully.', 'doc_category_id' => $id]);
            } else {
                $stmt = $db->prepare("UPDATE doc_categories SET entity_type = ?, category_name = ?, description = ?, is_active = ? WHERE doc_category_id = ?");
                $stmt->bind_param("sssii", $entityType, $categoryName, $description, $isActive, $id);
                $stmt->execute();
                $stmt->close();
                echo json_encode(['success' => true, 'message' => 'Document category updated successfully.', 'doc_category_id' => $id]);
            }
            break;

        case 'delete_category':
            requireAdmin();

            $id = (int)($_POST['doc_category_id'] ?? 0);
            
            // Check if documents currently use this category
            $chk = $db->prepare("SELECT COUNT(*) AS total FROM app_attachments WHERE doc_category_id = ?");
            $chk->bind_param("i", $id);
            $chk->execute();
            $inUse = $chk->get_result()->fetch_assoc()['total'] ?? 0;
            $chk->close();

            if ($inUse > 0) {
                echo json_encode(['success' => false, 'error' => "Cannot delete: $inUse document(s) are assigned to this category. Deactivate it instead."]);
                break;
            }

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