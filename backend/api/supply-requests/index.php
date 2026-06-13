<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDbConnection();

    if ($method === 'GET') {
        $user = requireAuth(['pharmacist', 'medical_services', 'med_rep', 'admin']);
        $db = getDB();

        $pharmacyId = isset($_GET['pharmacy_id']) ? (int) $_GET['pharmacy_id'] : null;
        $repId      = isset($_GET['rep_id']) ? (int) $_GET['rep_id'] : null;
        $status     = $_GET['status'] ?? '';
        $urgency    = $_GET['urgency'] ?? '';
        $limit      = min((int) ($_GET['limit'] ?? 20), 100);

        $where  = [];
        $params = [];

        if (isAdmin($user)) {
            if ($pharmacyId) {
                $where[] = 'sr.pharmacy_id = :pharmacy_id';
                $params[':pharmacy_id'] = $pharmacyId;
            }
            if ($repId) {
                $where[] = '(sr.target_rep_id = :rep_id OR sr.assigned_rep_id = :rep_id2)';
                $params[':rep_id']  = $repId;
                $params[':rep_id2'] = $repId;
            }
        } elseif ($user['role'] === 'med_rep') {
            $repId = resolveRepId($db, $user, null);
            $where[] = '(sr.target_rep_id = :rep_id OR sr.assigned_rep_id = :rep_id2)';
            $params[':rep_id']  = $repId;
            $params[':rep_id2'] = $repId;
        } else {
            $pharmacyId = resolvePharmacyId($db, $user, $pharmacyId);
            $where[] = 'sr.pharmacy_id = :pharmacy_id';
            $params[':pharmacy_id'] = $pharmacyId;
        }

        $db->close();

        if ($status) {
            $where[] = 'sr.status = :status';
            $params[':status'] = $status;
        }
        if ($urgency) {
            $where[] = 'sr.urgency = :urgency';
            $params[':urgency'] = $urgency;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("
            SELECT sr.*, p.name AS pharmacy_name, p.wilaya AS pharmacy_wilaya, p.phone AS pharmacy_phone
            FROM supply_requests sr
            LEFT JOIN pharmacies p ON p.id = sr.pharmacy_id
            $whereClause
            ORDER BY FIELD(sr.urgency,'critical','high','medium','low'), sr.created_at DESC
            LIMIT :limit
        ");

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        sendSuccess([
            'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'    => $stmt->rowCount(),
        ]);
    } elseif ($method === 'POST') {
        $user = requireAuth(['pharmacist', 'medical_services', 'admin']);
        $data = getBody();

        if (empty($data['product_name'])) {
            sendError('حقل product_name مطلوب', 400);
        }

        $db = getDB();
        $pharmacyId = resolvePharmacyId(
            $db,
            $user,
            isset($data['pharmacy_id']) ? (int) $data['pharmacy_id'] : null
        );
        $db->close();

        $stmt = $pdo->prepare("
            INSERT INTO supply_requests
                (pharmacy_id, pharmacy_user_id, product_name, product_type,
                 requested_quantity, urgency, notes, target_rep_id, status)
            VALUES
                (:pharmacy_id, :user_id, :product, :type, :qty, :urgency, :notes, :rep_id, 'open')
        ");

        $stmt->execute([
            ':pharmacy_id' => $pharmacyId,
            ':user_id'     => $user['id'],
            ':product'     => $data['product_name'],
            ':type'        => $data['product_type'] ?? 'medicine',
            ':qty'         => (int) ($data['requested_quantity'] ?? 1),
            ':urgency'     => $data['urgency'] ?? 'medium',
            ':notes'       => $data['notes'] ?? null,
            ':rep_id'      => $data['target_rep_id'] ?? null,
        ]);

        sendSuccess([
            'id'      => (int) $pdo->lastInsertId(),
            'message' => 'تم إرسال طلب التوريد بنجاح',
        ], 201);
    } elseif ($method === 'PUT') {
        $user = requireAuth(['pharmacist', 'medical_services', 'med_rep', 'admin']);
        $data = getBody();
        $id   = (int) ($_GET['id'] ?? 0);

        if (!$id) {
            sendError('معرف الطلب مطلوب', 400);
        }

        assertSupplyRequestAccessPdo($pdo, $id, $user);

        $validStatuses = ['open', 'assigned', 'accepted', 'rejected', 'forwarded', 'fulfilled', 'cancelled'];
        $newStatus = $data['status'] ?? null;

        if ($newStatus && !in_array($newStatus, $validStatuses, true)) {
            sendError('حالة غير صحيحة', 400);
        }

        $sets = [];
        $params = [':id' => $id];

        if ($newStatus) {
            $sets[] = 'status = :status';
            $params[':status'] = $newStatus;
        }
        if (!empty($data['assigned_rep_id'])) {
            if ($user['role'] === 'med_rep') {
                $db = getDB();
                $ownRep = resolveRepId($db, $user, null);
                $db->close();
                if ((int) $data['assigned_rep_id'] !== $ownRep) {
                    sendError('غير مصرح', 403);
                }
            }
            $sets[] = 'assigned_rep_id = :rep_id';
            $sets[] = 'assigned_at = NOW()';
            $params[':rep_id'] = (int) $data['assigned_rep_id'];
        }
        if ($newStatus === 'fulfilled') {
            $sets[] = 'fulfilled_at = NOW()';
        }

        if ($sets === []) {
            sendError('لا يوجد بيانات للتحديث', 400);
        }

        $stmt = $pdo->prepare('UPDATE supply_requests SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);

        sendSuccess(['message' => 'تم تحديث الطلب بنجاح']);
    } else {
        sendError('Method not allowed', 405);
    }
} catch (PDOException $e) {
    sendError('خطأ في قاعدة البيانات', 500);
}