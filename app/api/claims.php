<?php
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$conn = getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getClaims($conn);
        break;
    case 'POST':
        createClaim($conn);
        break;
    case 'PUT':
        updateClaim($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

$conn->close();

// ── GET Claims ────────────────────────────────────────────
function getClaims($conn) {
    $userId = $_GET['user_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $claimId = $_GET['claim_id'] ?? null;

    if ($claimId) {
        $stmt = $conn->prepare(
            "SELECT ic.*, u.fname, u.lname, u.contact_number,
                    COALESCE(a.barangay,'') as barangay, COALESCE(a.municipality,'') as municipality,
                    r.description as report_description, r.field_location, r.status as report_status
             FROM tbl_insurance_claim ic
             JOIN tbl_user u ON ic.user_id = u.user_id
             JOIN tbl_disease_report r ON ic.report_id = r.report_id
             LEFT JOIN tbl_address a ON u.user_id = a.user_id
             WHERE ic.claim_id = ?"
        );
        $stmt->bind_param("i", $claimId);
        $stmt->execute();
        $result = $stmt->get_result();
        $claim = $result->fetch_assoc();

        if ($claim) {
            echo json_encode($claim);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Claim not found']);
        }
        $stmt->close();
        return;
    }

    $sql = "SELECT ic.*, u.fname, u.lname, u.contact_number,
                   COALESCE(a.barangay,'') as barangay, COALESCE(a.municipality,'') as municipality,
                   r.field_location, r.status as report_status
            FROM tbl_insurance_claim ic
            JOIN tbl_user u ON ic.user_id = u.user_id
            JOIN tbl_disease_report r ON ic.report_id = r.report_id
            LEFT JOIN tbl_address a ON u.user_id = a.user_id
            WHERE 1=1";
    $params = [];
    $types = "";

    if ($userId) {
        $sql .= " AND ic.user_id = ?";
        $params[] = $userId;
        $types .= "i";
    }
    if ($status) {
        $sql .= " AND ic.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $sql .= " ORDER BY ic.created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $claims = [];
    while ($row = $result->fetch_assoc()) {
        $claims[] = $row;
    }

    echo json_encode($claims);
    $stmt->close();
}

// ── POST Create Claim ─────────────────────────────────────
function createClaim($conn) {
    $data = json_decode(file_get_contents('php://input'), true);

    $required = ['user_id', 'report_id', 'damage_type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }

    // Verify report is in Verified status
    $check = $conn->prepare("SELECT status FROM tbl_disease_report WHERE report_id = ?");
    $check->bind_param("i", $data['report_id']);
    $check->execute();
    $reportResult = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$reportResult) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        return;
    }
    if ($reportResult['status'] !== 'Verified') {
        http_response_code(400);
        echo json_encode(['error' => 'Report must be verified before filing an insurance claim']);
        return;
    }

    $rsbsa = $data['rsbsa_num'] ?? '';
    $policy = $data['policy_num'] ?? '';

    $stmt = $conn->prepare(
        "INSERT INTO tbl_insurance_claim (user_id, report_id, rsbsa_num, policy_num, damage_type, status)
         VALUES (?, ?, ?, ?, ?, 'Pending')"
    );
    $stmt->bind_param("iisss", $data['user_id'], $data['report_id'], $rsbsa, $policy, $data['damage_type']);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            'message' => 'Insurance claim submitted',
            'claim_id' => $stmt->insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to submit claim: ' . $stmt->error]);
    }
    $stmt->close();
}

// ── PUT Update Claim Status ───────────────────────────────
function updateClaim($conn) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['claim_id']) || empty($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'claim_id and status are required']);
        return;
    }

    $valid = ['Pending', 'Verified', 'Paid', 'Rejected'];
    if (!in_array($data['status'], $valid)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status. Use: ' . implode(', ', $valid)]);
        return;
    }

    $stmt = $conn->prepare("UPDATE tbl_insurance_claim SET status = ? WHERE claim_id = ?");
    $stmt->bind_param("si", $data['status'], $data['claim_id']);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['message' => 'Claim status updated']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Claim not found or status unchanged']);
    }
    $stmt->close();
}
