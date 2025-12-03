<?php
/**
 * GTAW Furniture Catalog - Submission Functions
 * 
 * Functions for managing user-submitted furniture additions/edits.
 */

declare(strict_types=1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'submissions.php') {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Load validator class
require_once __DIR__ . '/validator.php';

// ============================================
// SUBMISSION CONSTANTS
// ============================================

const SUBMISSION_TYPE_NEW = 'new';
const SUBMISSION_TYPE_EDIT = 'edit';

const SUBMISSION_STATUS_PENDING = 'pending';
const SUBMISSION_STATUS_APPROVED = 'approved';
const SUBMISSION_STATUS_REJECTED = 'rejected';

// ============================================
// SUBMISSION FUNCTIONS
// ============================================

/**
 * Get submissions with pagination and filters
 */
function getSubmissions(
    PDO $pdo,
    int $page = 1,
    int $perPage = 20,
    ?string $status = null,
    ?string $type = null,
    ?int $userId = null
): array {
    $perPage = min(max(1, $perPage), MAX_ITEMS_PER_PAGE);
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;
    
    $where = [];
    $params = [];
    
    if ($status !== null) {
        $where[] = 's.status = ?';
        $params[] = $status;
    }
    
    if ($type !== null) {
        $where[] = 's.type = ?';
        $params[] = $type;
    }
    
    if ($userId !== null) {
        $where[] = 's.user_id = ?';
        $params[] = $userId;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Count total
    $countSql = "SELECT COUNT(*) FROM submissions s {$whereClause}";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();
    
    // Get items
    $sql = "
        SELECT s.*, 
               u.username as submitter_username,
               f.name as furniture_name,
               a.username as reviewer_username
        FROM submissions s
        INNER JOIN users u ON s.user_id = u.id
        LEFT JOIN furniture f ON s.furniture_id = f.id
        LEFT JOIN admins a ON s.reviewed_by = a.id
        {$whereClause}
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decode JSON data for each item
    foreach ($items as &$item) {
        $item['data'] = json_decode($item['data'], true);
    }
    
    return [
        'items' => $items,
        'pagination' => createPagination($total, $page, $perPage),
    ];
}

/**
 * Get user's submissions
 */
function getUserSubmissions(PDO $pdo, int $userId, int $page = 1, int $perPage = 20): array
{
    return getSubmissions($pdo, $page, $perPage, null, null, $userId);
}

/**
 * Get pending submissions count
 */
function getPendingSubmissionsCount(PDO $pdo): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE status = ?");
    $stmt->execute([SUBMISSION_STATUS_PENDING]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get submission by ID
 */
function getSubmissionById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT s.*, 
               u.username as submitter_username,
               f.name as furniture_name,
               a.username as reviewer_username
        FROM submissions s
        INNER JOIN users u ON s.user_id = u.id
        LEFT JOIN furniture f ON s.furniture_id = f.id
        LEFT JOIN admins a ON s.reviewed_by = a.id
        WHERE s.id = ?
    ');
    $stmt->execute([$id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($submission) {
        $submission['data'] = json_decode($submission['data'], true);
    }
    
    return $submission ?: null;
}

/**
 * Create new furniture submission
 */
function createSubmission(PDO $pdo, int $userId, string $type, array $data, ?int $furnitureId = null): int
{
    $stmt = $pdo->prepare('
        INSERT INTO submissions (user_id, type, furniture_id, data, status)
        VALUES (?, ?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $userId,
        $type,
        $furnitureId,
        json_encode($data),
        SUBMISSION_STATUS_PENDING,
    ]);
    
    return (int) $pdo->lastInsertId();
}

/**
 * Create a "new furniture" submission
 */
function submitNewFurniture(PDO $pdo, int $userId, array $data): int
{
    return createSubmission($pdo, $userId, SUBMISSION_TYPE_NEW, $data);
}

/**
 * Create an "edit furniture" submission
 */
function submitFurnitureEdit(PDO $pdo, int $userId, int $furnitureId, array $data): int
{
    return createSubmission($pdo, $userId, SUBMISSION_TYPE_EDIT, $data, $furnitureId);
}

/**
 * Approve submission
 * 
 * For 'new' submissions: creates furniture
 * For 'edit' submissions: updates existing furniture
 * Also handles image processing (WebP conversion)
 */
function approveSubmission(PDO $pdo, int $submissionId, int $adminId): bool
{
    // Include image processing functions
    require_once __DIR__ . '/image.php';
    
    $submission = getSubmissionById($pdo, $submissionId);
    if (!$submission || $submission['status'] !== SUBMISSION_STATUS_PENDING) {
        throw new RuntimeException('Submission not found or not pending');
    }
    
    $pdo->beginTransaction();
    
    try {
        $data = $submission['data'];
        $furnitureId = null;
        
        if ($submission['type'] === SUBMISSION_TYPE_NEW) {
            // Create new furniture
            $furnitureId = createFurniture($pdo, $data);
        } elseif ($submission['type'] === SUBMISSION_TYPE_EDIT && $submission['furniture_id']) {
            // Update existing furniture
            updateFurniture($pdo, $submission['furniture_id'], $data);
            $furnitureId = $submission['furniture_id'];
        }
        
        // Process image if URL provided
        if ($furnitureId && !empty($data['image_url'])) {
            $processor = new ImageProcessor();
            $processor->processFurnitureImage($pdo, $furnitureId, $data['image_url']);
        }
        
        // Update submission status
        $stmt = $pdo->prepare('
            UPDATE submissions 
            SET status = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ');
        $result = $stmt->execute([SUBMISSION_STATUS_APPROVED, $adminId, $submissionId]);
        
        if (!$result) {
            throw new RuntimeException('Failed to update submission status');
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        // Re-throw as RuntimeException to maintain consistent error handling
        throw new RuntimeException('Failed to approve submission: ' . $e->getMessage(), 0, $e);
    }
}

/**
 * Reject submission
 * 
 * @throws RuntimeException If database is not available or rejection fails
 */
function rejectSubmission(PDO $pdo, int $submissionId, int $adminId, ?string $notes = null): bool
{
    $stmt = $pdo->prepare('
        UPDATE submissions 
        SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE id = ? AND status = ?
    ');
    
    $result = $stmt->execute([
        SUBMISSION_STATUS_REJECTED, 
        $notes, 
        $adminId, 
        $submissionId,
        SUBMISSION_STATUS_PENDING
    ]);
    
    if (!$result) {
        throw new RuntimeException('Failed to reject submission');
    }
    
    return true;
}

/**
 * Delete submission (for user cancellation or cleanup)
 * 
 * @throws RuntimeException If database is not available or deletion fails
 */
function deleteSubmission(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM submissions WHERE id = ?');
    $result = $stmt->execute([$id]);
    
    if (!$result) {
        throw new RuntimeException('Failed to delete submission');
    }
    
    return true;
}

/**
 * Check if user owns submission
 */
function userOwnsSubmission(PDO $pdo, int $userId, int $submissionId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM submissions WHERE id = ? AND user_id = ?');
    $stmt->execute([$submissionId, $userId]);
    return $stmt->fetch() !== false;
}

/**
 * Validate submission input
 * 
 * Accepts either category_ids (array) or category_id (single) for backwards compatibility.
 * 
 * @param array $input Input data to validate
 * @param string $type Submission type (SUBMISSION_TYPE_NEW or SUBMISSION_TYPE_EDIT)
 * @return array{valid: bool, errors: array<string, string>, data: array<string, mixed>}
 */
function validateSubmissionInput(array $input, string $type): array
{
    $errors = [];
    $data = [];
    
    // Use Validator class for all validations
    $nameResult = Validator::furnitureName($input['name'] ?? '');
    if (!$nameResult['valid']) {
        $errors['name'] = $nameResult['error'];
    } else {
        $data['name'] = $nameResult['data'];
    }
    
    // Category validation - accept array or single ID
    if (isset($input['category_ids']) && is_array($input['category_ids'])) {
        $categoryResult = Validator::categoryIds($input['category_ids']);
        if (!$categoryResult['valid']) {
            $errors['category_ids'] = $categoryResult['error'];
        } else {
            $data['category_ids'] = $categoryResult['data'];
        }
    } else {
        $categoryId = (int) ($input['category_id'] ?? 0);
        $categoryResult = Validator::categoryId($categoryId);
        if (!$categoryResult['valid']) {
            $errors['category_id'] = $categoryResult['error'];
        } else {
            $data['category_ids'] = [$categoryResult['data']];
        }
    }
    
    // Price validation
    $price = (int) ($input['price'] ?? 0);
    $priceResult = Validator::price($price);
    if (!$priceResult['valid']) {
        $errors['price'] = $priceResult['error'];
    } else {
        $data['price'] = $priceResult['data'];
    }
    
    // Image URL validation
    $imageUrlResult = Validator::imageUrl($input['image_url'] ?? '');
    if (!$imageUrlResult['valid']) {
        $errors['image_url'] = $imageUrlResult['error'];
    } else {
        $data['image_url'] = $imageUrlResult['data'];
    }
    
    // Tags validation
    if (isset($input['tags']) && is_array($input['tags'])) {
        $tagsResult = Validator::tags($input['tags']);
        $data['tags'] = $tagsResult['data'];
    }
    
    // Notes: optional for edit submissions
    if ($type === SUBMISSION_TYPE_EDIT && !empty($input['edit_notes'])) {
        $data['edit_notes'] = trim($input['edit_notes']);
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $data,
    ];
}

