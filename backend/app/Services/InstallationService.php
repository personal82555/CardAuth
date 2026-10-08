<?php
namespace App\Services;

use App\Core\Database;

/**
 * 安装记录服务
 * 安装次数统计、存活/已删除、最后上线
 */
class InstallationService
{
    /**
     * 上报/登记安装（check-update / verify 成功时调用）
     * 同项目同机器存在 active 记录则更新，否则新建（重装/新设备独立计数）
     *
     * @return array {install_id: int, is_new: bool}
     */
    public static function upsert(
        int $projectId,
        string $machineId,
        string $ip = '',
        string $deviceInfo = '',
        string $clientVersion = '',
        ?int $cardId = null,
        string $cardKey = ''
    ): array {
        $db = Database::getInstance();

        $existing = $db->fetch(
            "SELECT id FROM {$db->table('installations')}
             WHERE project_id = ? AND machine_id = ? AND status = 'active'
             ORDER BY id DESC LIMIT 1",
            [$projectId, $machineId]
        );

        if ($existing) {
            $updateCardId = $cardId;
            $updateCardKey = $cardKey !== '' ? $cardKey : null;
            $updateDevice = $deviceInfo !== '' ? $deviceInfo : null;
            $updateVersion = $clientVersion !== '' ? $clientVersion : null;

            $db->execute(
                "UPDATE {$db->table('installations')}
                 SET ip = ?, device_info = ?, client_version = ?,
                     last_online_at = NOW(),
                     card_id = COALESCE(?, card_id),
                     card_key = COALESCE(?, card_key)
                 WHERE id = ?",
                [
                    $ip !== '' ? $ip : null,
                    $updateDevice,
                    $updateVersion,
                    $updateCardId,
                    $updateCardKey,
                    $existing['id'],
                ]
            );
            return ['install_id' => (int) $existing['id'], 'is_new' => false];
        }

        $installId = $db->insert(
            "INSERT INTO {$db->table('installations')}
             (project_id, card_id, card_key, machine_id, ip, device_info, client_version,
              status, install_count, last_online_at, installed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 1, NOW(), NOW())",
            [
                $projectId,
                $cardId,
                $cardKey,
                $machineId,
                $ip !== '' ? $ip : null,
                $deviceInfo !== '' ? $deviceInfo : null,
                $clientVersion !== '' ? $clientVersion : null,
            ]
        );

        return ['install_id' => $installId, 'is_new' => true];
    }

    /**
     * 仅更新在线时间（心跳）
     */
    public static function heartbeat(
        int $projectId,
        string $machineId,
        string $ip = '',
        string $deviceInfo = '',
        string $clientVersion = '',
        ?int $cardId = null,
        string $cardKey = ''
    ): array {
        return self::upsert($projectId, $machineId, $ip, $deviceInfo, $clientVersion, $cardId, $cardKey);
    }

    /**
     * 数据范围过滤（admin / project_admin / agent）
     * @return array {where: string, params: array}
     */
    public static function scope(array $user): array
    {
        $db = Database::getInstance();
        $role = $user['role'] ?? '';

        if ($role === 'admin') {
            return ['where' => '', 'params' => []];
        }

        if ($role === 'project_admin') {
            $projectIds = $user['project_ids'] ?? [];
            if (empty($projectIds)) {
                return ['where' => ' AND 1=0', 'params' => []];
            }
            $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
            return [
                'where' => " AND i.project_id IN ({$placeholders})",
                'params' => array_map('intval', $projectIds),
            ];
        }

        // 代理：仅本人及下级代理发放卡密相关的安装
        $agentIds = PermissionService::getAgentIdsUnder((int) ($user['id'] ?? 0));
        if (empty($agentIds)) {
            return ['where' => ' AND 1=0', 'params' => []];
        }
        $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
        return [
            'where' => " AND i.card_id IN (SELECT c.id FROM {$db->table('cards')} c WHERE c.use_user_id IN ({$placeholders}))",
            'params' => array_map('intval', $agentIds),
        ];
    }

    /**
     * 构建安装列表/统计 WHERE
     */
    public static function buildListWhere(array $user, array $filters = []): array
    {
        $scope = self::scope($user);
        $where = $scope['where'];
        $params = $scope['params'];

        if (!empty($filters['project_id'])) {
            $where .= ' AND i.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'deleted'], true)) {
            $where .= ' AND i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where .= ' AND (i.machine_id LIKE ? OR i.card_key LIKE ? OR i.ip LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND i.installed_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND i.installed_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        return ['where' => $where, 'params' => $params];
    }

    /**
     * 安装统计（含已删除）
     */
    public static function stats(array $user, ?int $projectId = null): array
    {
        $db = Database::getInstance();
        $whereList = self::buildListWhere($user, $projectId ? ['project_id' => $projectId] : []);
        $where = $whereList['where'];
        $params = $whereList['params'];

        $total = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('installations')} i WHERE 1=1 {$where}",
            $params
        );
        $active = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('installations')} i WHERE i.status = 'active' {$where}",
            $params
        );
        $deleted = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('installations')} i WHERE i.status = 'deleted' {$where}",
            $params
        );
        $todayNew = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('installations')} i WHERE DATE(i.installed_at) = CURDATE() {$where}",
            $params
        );
        $weekNew = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('installations')} i WHERE i.installed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) {$where}",
            $params
        );
        $lastOnline = $db->fetchColumn(
            "SELECT MAX(i.last_online_at) FROM {$db->table('installations')} i WHERE i.last_online_at IS NOT NULL {$where}",
            $params
        );

        $projects = $db->fetchAll(
            "SELECT i.project_id, p.name AS project_name,
                    COUNT(*) AS total_installs,
                    SUM(CASE WHEN i.status = 'active' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN i.status = 'deleted' THEN 1 ELSE 0 END) AS deleted,
                    SUM(CASE WHEN DATE(i.installed_at) = CURDATE() THEN 1 ELSE 0 END) AS today_new,
                    MAX(i.last_online_at) AS last_online_at
             FROM {$db->table('installations')} i
             LEFT JOIN {$db->table('projects')} p ON p.id = i.project_id
             WHERE 1=1 {$where}
             GROUP BY i.project_id, p.name
             ORDER BY total_installs DESC",
            $params
        );

        return [
            'total_installs' => $total,
            'active'         => $active,
            'deleted'        => $deleted,
            'today_new'      => $todayNew,
            'week_new'       => $weekNew,
            'last_online_at' => $lastOnline ?: null,
            'projects'       => array_map(static function (array $row): array {
                return [
                    'project_id'     => (int) $row['project_id'],
                    'project_name'   => $row['project_name'],
                    'total_installs' => (int) $row['total_installs'],
                    'active'         => (int) $row['active'],
                    'deleted'        => (int) $row['deleted'],
                    'today_new'      => (int) $row['today_new'],
                    'last_online_at' => $row['last_online_at'],
                ];
            }, $projects),
        ];
    }
}
