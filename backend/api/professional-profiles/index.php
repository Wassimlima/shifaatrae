<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDbConnection();

    if ($method === 'GET') {
        $userId      = $_GET['user_id']      ?? null;
        $profileType = $_GET['profile_type'] ?? null;
        $wilaya      = $_GET['wilaya']       ?? null;
        $limit       = min((int)($_GET['limit'] ?? 20), 100);

        $where  = ['pp.is_active = 1'];
        $params = [];

        if ($userId) {
            $where[] = 'pp.user_id = :user_id';
            $params[':user_id'] = (int)$userId;
        }
        if ($profileType) {
            $where[] = 'pp.profile_type = :profile_type';
            $params[':profile_type'] = $profileType;
        }
        if ($wilaya) {
            $where[] = 'pp.wilaya = :wilaya';
            $params[':wilaya'] = $wilaya;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT
                pp.id,
                pp.user_id,
                pp.profile_type,
                pp.business_name,
                pp.business_name_ar,
                pp.description,
                pp.wilaya,
                pp.city,
                pp.phone,
                pp.email,
                pp.logo_url,
                pp.is_verified,
                pp.profile_completeness,
                pp.created_at
            FROM professional_profiles pp
            $whereClause
            ORDER BY pp.is_verified DESC, pp.created_at DESC
            LIMIT :limit
        ");

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendSuccess([
            'profiles' => $profiles,
            'total'    => count($profiles)
        ]);

    } elseif ($method === 'POST') {
        $user = requireAuth();
        $data = getBody();

        $required = ['profile_type', 'business_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                sendError("حقل $field مطلوب", 400);
                return;
            }
        }

        $validTypes = ['pharmacy', 'lab', 'med_rep', 'medical_services'];
        if (!in_array($data['profile_type'], $validTypes)) {
            sendError('نوع الملف الشخصي غير صحيح', 400);
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO professional_profiles
                (user_id, profile_type, business_name, business_name_ar,
                 description, wilaya, city, address, phone, email,
                 website, logo_url, cover_url)
            VALUES
                (:user_id, :type, :name, :name_ar,
                 :desc, :wilaya, :city, :address, :phone, :email,
                 :website, :logo, :cover)
        ");

        $stmt->execute([
            ':user_id'  => $user['id'],
            ':type'     => $data['profile_type'],
            ':name'     => $data['business_name'],
            ':name_ar'  => $data['business_name_ar'] ?? null,
            ':desc'     => $data['description'] ?? null,
            ':wilaya'   => $data['wilaya'] ?? null,
            ':city'     => $data['city'] ?? null,
            ':address'  => $data['address'] ?? null,
            ':phone'    => $data['phone'] ?? null,
            ':email'    => $data['email'] ?? null,
            ':website'  => $data['website'] ?? null,
            ':logo'     => $data['logo_url'] ?? null,
            ':cover'    => $data['cover_url'] ?? null,
        ]);

        sendSuccess([
            'id'      => (int)$pdo->lastInsertId(),
            'message' => 'تم إنشاء الملف المهني بنجاح'
        ], 201);

    } elseif ($method === 'PUT') {
        $user = requireAuth();
        $data = getBody();
        $id   = (int) ($_GET['id'] ?? 0);

        if (!$id) {
            sendError('معرف الملف الشخصي مطلوب', 400);
            return;
        }

        assertProfessionalProfileAccessPdo($pdo, $id, $user);

        $allowed = ['business_name','business_name_ar','description','wilaya',
                    'city','address','phone','email','website','logo_url','cover_url'];
        $sets = [];
        $params = [':id' => (int)$id];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $sets[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($sets)) {
            sendError('لا يوجد بيانات للتحديث', 400);
            return;
        }

        $stmt = $pdo->prepare("UPDATE professional_profiles SET " . implode(', ', $sets) . " WHERE id = :id");
        $stmt->execute($params);

        sendSuccess(['message' => 'تم تحديث الملف المهني بنجاح']);

    } else {
        sendError('Method not allowed', 405);
    }

} catch (PDOException $e) {
    sendError('خطأ في قاعدة البيانات', 500);
}
