<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDbConnection();

    if ($method === 'GET') {
        $type     = $_GET['type']   ?? '';
        $status   = $_GET['status'] ?? 'active';
        $wilaya   = $_GET['wilaya'] ?? '';
        $limit    = min((int)($_GET['limit'] ?? 10), 50);

        $where = ['a.ends_at > NOW() OR a.ends_at IS NULL'];
        $params = [];

        if ($status) {
            $where[] = 'a.status = :status';
            $params[':status'] = $status;
        }
        if ($type) {
            $where[] = 'a.ad_type = :type';
            $params[':type'] = $type;
        }
        if ($wilaya) {
            $where[] = '(a.target_wilaya = :wilaya OR a.target_wilaya IS NULL)';
            $params[':wilaya'] = $wilaya;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.title,
                a.title_ar,
                a.description,
                a.image_url,
                a.target_url,
                a.ad_type,
                a.target_audience,
                a.impressions,
                a.clicks,
                a.starts_at,
                a.ends_at,
                a.created_at
            FROM advertisements a
            $whereClause
            ORDER BY a.created_at DESC
            LIMIT :limit
        ");

        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendSuccess([
            'advertisements' => $ads,
            'total'          => count($ads)
        ]);

    } elseif ($method === 'POST') {
        $user = requireAuth();
        $data = getBody();

        $required = ['title', 'ad_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                sendError("حقل $field مطلوب", 400);
                return;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO advertisements
                (advertiser_user_id, title, title_ar, description, image_url,
                 target_url, ad_type, target_audience, target_wilaya,
                 starts_at, ends_at, budget, status)
            VALUES
                (:user_id, :title, :title_ar, :desc, :img,
                 :url, :type, :audience, :wilaya,
                 :starts, :ends, :budget, 'pending')
        ");

        $stmt->execute([
            ':user_id'  => $user['id'],
            ':title'    => $data['title'],
            ':title_ar' => $data['title_ar'] ?? null,
            ':desc'     => $data['description'] ?? null,
            ':img'      => $data['image_url'] ?? null,
            ':url'      => $data['target_url'] ?? null,
            ':type'     => $data['ad_type'],
            ':audience' => $data['target_audience'] ?? 'all',
            ':wilaya'   => $data['target_wilaya'] ?? null,
            ':starts'   => $data['starts_at'] ?? null,
            ':ends'     => $data['ends_at'] ?? null,
            ':budget'   => $data['budget'] ?? null,
        ]);

        sendSuccess([
            'id'      => (int)$pdo->lastInsertId(),
            'message' => 'تم إرسال الإعلان بنجاح وهو في انتظار مراجعة الإدارة'
        ], 201);

    } else {
        sendError('Method not allowed', 405);
    }

} catch (PDOException $e) {
    sendError('خطأ في قاعدة البيانات', 500);
}
