<?php
namespace App\Services;

use App\Core\Database;

/**
 * 项目版本/安装包服务
 */
class VersionService
{
    /**
     * 语义化版本比较
     * @return int -1 a<b, 0 a==b, 1 a>b
     */
    public static function compare(string $a, string $b): int
    {
        return version_compare(self::normalize($a), self::normalize($b));
    }

    /**
     * 规范化版本号：剥离前缀 v/V，预发布后缀
     */
    public static function normalize(string $version): string
    {
        $version = trim($version);
        if ($version === '') {
            return '0.0.0';
        }
        if ($version[0] === 'v' || $version[0] === 'V') {
            $version = substr($version, 1);
        }
        // 预发布：1.2.0-beta.1 -> 1.2.0-beta
        return $version;
    }

    /**
     * 获取项目最新可用版本
     */
    public static function getLatest(int $projectId): ?array
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM {$db->table('project_versions')}
             WHERE project_id = ? AND status = 1 AND is_latest = 1
             ORDER BY id DESC LIMIT 1",
            [$projectId]
        );
        return $row ?: null;
    }

    /**
     * 构造客户端更新提示载荷
     *
     * @return array {
     *   update_available: bool, update_required: bool,
     *   current_version: string, latest_version: string,
     *   version: ?string, download_url: ?string, file_size: int,
     *   checksum: string, changelog: ?string,
     *   min_client_version: string, is_force: int
     * }
     */
    public static function buildUpdatePayload(int $projectId, string $clientVersion = ''): array
    {
        $db = Database::getInstance();
        $latest = self::getLatest($projectId);

        $clientVersion = self::normalize($clientVersion);
        $hasClient = trim($clientVersion) !== '' && $clientVersion !== '0.0.0';

        if (!$latest) {
            return [
                'update_available'    => false,
                'update_required'     => false,
                'current_version'     => $clientVersion,
                'latest_version'      => '',
                'version'             => null,
                'download_url'        => null,
                'file_size'           => 0,
                'checksum'            => '',
                'changelog'           => null,
                'min_client_version'  => '',
                'is_force'            => 0,
                'install_id'          => null,
            ];
        }

        $latestVersion = self::normalize($latest['version']);
        $minVersion = self::normalize($latest['min_client_version'] ?? '');
        $isForce = (int) $latest['is_force'];

        $updateAvailable = !$hasClient || self::compare($latestVersion, $clientVersion) > 0;
        $belowMin = $minVersion !== '' && $minVersion !== '0.0.0'
            && self::compare($clientVersion, $minVersion) < 0;
        $updateRequired = ($isForce === 1 && $updateAvailable) || ($belowMin && $updateAvailable);

        return [
            'update_available'    => $updateAvailable,
            'update_required'     => $updateRequired,
            'current_version'     => $clientVersion,
            'latest_version'      => $latest['version'],
            'version'             => $latest['version'],
            'download_url'        => $latest['file_path'],
            'file_size'           => (int) $latest['file_size'],
            'checksum'            => $latest['checksum'] ?? '',
            'changelog'           => $latest['changelog'],
            'min_client_version'  => $latest['min_client_version'] ?? '',
            'is_force'            => $isForce,
            'install_id'          => null,
        ];
    }

    /**
     * 数据范围：项目管理员/管理员可见；代理只读（由控制器限制写操作）
     */
    public static function scope(array $user): array
    {
        $role = $user['role'] ?? '';
        if ($role === 'admin') {
            return ['where' => 'p.deleted_at IS NULL', 'params' => []];
        }
        if ($role === 'project_admin') {
            $projectIds = $user['project_ids'] ?? [];
            if (empty($projectIds)) {
                return ['where' => '1=0', 'params' => []];
            }
            $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
            return [
                'where' => "p.deleted_at IS NULL AND p.id IN ({$placeholders})",
                'params' => array_map('intval', $projectIds),
            ];
        }

        // 代理：有卡密记录的项目
        $db = Database::getInstance();
        $agentIds = PermissionService::getAgentIdsUnder((int) ($user['id'] ?? 0));
        if (empty($agentIds)) {
            return ['where' => '1=0', 'params' => []];
        }
        $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
        return [
            'where' => "p.deleted_at IS NULL AND p.id IN (
                SELECT DISTINCT c.project_id FROM {$db->table('cards')} c WHERE c.use_user_id IN ({$placeholders})
            )",
            'params' => array_map('intval', $agentIds),
        ];
    }

    /**
     * 是否允许发布/修改版本（仅 admin / project_admin）
     */
    public static function canPublish(?string $role): bool
    {
        return in_array($role, ['admin', 'project_admin'], true);
    }

    /**
     * 校验项目访问权限
     */
    public static function assertProjectAccess(int $projectId, array $user): bool
    {
        $role = $user['role'] ?? '';
        if ($role === 'admin') {
            return true;
        }
        if ($role === 'project_admin') {
            $projectIds = $user['project_ids'] ?? [];
            return in_array($projectId, array_map('intval', $projectIds), true);
        }
        if ($role === 'agent') {
            $db = Database::getInstance();
            $agentIds = PermissionService::getAgentIdsUnder((int) ($user['id'] ?? 0));
            if (empty($agentIds)) {
                return false;
            }
            $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
            $count = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM {$db->table('cards')} WHERE project_id = ? AND use_user_id IN ({$placeholders})",
                array_merge([$projectId], $agentIds)
            );
            return $count > 0;
        }
        return false;
    }
}
