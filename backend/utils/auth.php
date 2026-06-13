<?php
function loginUser(array $user): void {
    if (!session_id()) session_start();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => $user['id'],
        'name'  => $user['full_name'] ?? $user['name'] ?? '',
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
}

function currentUser(): ?array {
    if (!session_id()) {
        session_set_cookie_params([
            'lifetime' => 86400 * 30,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    return $_SESSION['user'] ?? null;
}

/**
 * Require an authenticated session.
 * Optionally pass an array of allowed roles (backward-compatible).
 */
function requireAuth(array $roles = []): array {
    $user = currentUser();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'غير مصرح', 'message' => 'غير مصرح']);
        exit;
    }
    if (!empty($roles) && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية', 'message' => 'ليس لديك صلاحية']);
        exit;
    }
    return $user;
}

function requireAdmin(): array {
    return requireAuth(['admin']);
}

function requireRole(string ...$roles): array {
    return requireAuth(array_values($roles));
}

function isAdmin(array $user): bool {
    return $user['role'] === 'admin';
}

/**
 * Resolve the med_rep id for the current user.
 */
function resolveRepId(mysqli $db, array $user, ?int $requestedId): int {
    if ($user['role'] === 'admin' && $requestedId) {
        return $requestedId;
    }
    $stmt = $db->prepare('SELECT id FROM med_reps WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return (int) $row['id'];
    if ($requestedId) return $requestedId;
    $row = $db->query('SELECT id FROM med_reps LIMIT 1')->fetch_assoc();
    return $row ? (int) $row['id'] : 1;
}

/**
 * Resolve the pharmacy_id for the current request.
 * For admins an explicit pharmacyId param is accepted.
 * For pharmacists the pharmacy linked to their user_id is used.
 */
function resolvePharmacyId(mysqli $db, array $user, ?int $requestedId): int {
    if ($user['role'] === 'admin' && $requestedId) {
        return $requestedId;
    }
    // Look up the pharmacy owned by this user
    $stmt = $db->prepare('SELECT id FROM pharmacies WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return (int) $row['id'];
    // Fallback: use requested id or first pharmacy
    if ($requestedId) return $requestedId;
    $row = $db->query('SELECT id FROM pharmacies LIMIT 1')->fetch_assoc();
    return $row ? (int) $row['id'] : 1;
}

function getUserContext(mysqli $db, array $user): array {
    $ctx = ['pharmacy_id' => null, 'rep_id' => null, 'lab_id' => null, 'service_id' => null];

    if ($user['role'] === 'pharmacist') {
        $stmt = $db->prepare('SELECT id FROM pharmacies WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) $ctx['pharmacy_id'] = (int) $row['id'];
        $stmt->close();
    }

    if ($user['role'] === 'med_rep') {
        $stmt = $db->prepare('SELECT id FROM med_reps WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) $ctx['rep_id'] = (int) $row['id'];
        $stmt->close();
    }

    if ($user['role'] === 'lab') {
        $stmt = $db->prepare('SELECT id FROM labs WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) $ctx['lab_id'] = (int) $row['id'];
        $stmt->close();
    }

    if ($user['role'] === 'medical_services') {
        $stmt = $db->prepare('SELECT id FROM medical_services WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) $ctx['service_id'] = (int) $row['id'];
        $stmt->close();
    }

    return $ctx;
}
