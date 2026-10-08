<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\InstallationService;
use App\Services\PermissionService;

/**
 * 安装记录管理控制器
 */
class InstallationController extends Controller
{
    /**
     * 安装列表
     * GET /api/installations
     */
    public function list(): void
    {
        $db = Database::getInstance();
        $user = $this->getCurrentUser() ?: [];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = min((int) ($_GET['page_size'] ?? 20), 100);
        $offset = ($page - 1) * $pageSize;

        $filters = [
            'project_id' => $_GET['project_id'] ?? '',
            'status'     => $_GET['status'] ?? '',
            'keyword'    => trim($_GET['keyword'] ?? ''),
            'date_from'  => $_GET['date_from'] ?? '',
            'date_to'    => $_GET['date_to'] ?? '',
        ];

        $built = InstallationService::buildListWhere($user, $filters);
        $where = $built['where'];
        $params = $built['params'];

        $total = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM {$db->table('installations')} i WHERE 1=1 {$where}",
            $params
        );

        $list = $db->fetchAll(
            "SELECT i.*, p.name AS project_name
             FROM {$db->table('installations')} i
             LEFT JOIN {$db->table('projects')} p ON p.id = i.project_id
             WHERE 1=1 {$where}
             ORDER BY i.id DESC
             LIMIT {$offset}, {$pageSize}",
            $params
        );

        $this->success([
            'list'      => $list,
            'total'     => $total,
            'page'      => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 安装统计（含已删除）
     * GET /api/installations/stats
     */
    public function stats(): void
    {
        $user = $this->getCurrentUser() ?: [];
        $projectId = !empty($_GET['project_id']) ? (int) $_GET['project_id'] : null;

        $this->success(InstallationService::stats($user, $projectId));
    }

    /**
     * 安装详情
     * GET /api/installations/{id}
     */
    public function detail(int $id): void
    {
        $db = Database::getInstance();
        $user = $this->getCurrentUser() ?: [];

        $row = $db->fetch(
            "SELECT i.*, p.name AS project_name
             FROM {$db->table('installations')} i
             LEFT JOIN {$db->table('projects')} p ON p.id = i.project_id
             WHERE i.id = ?",
            [$id]
        );
        if (!$row) {
            $this->error('安装记录不存在', 404);
        }

        if (!$this->canAccess((int) $row['project_id'], $user)) {
            $this->error('无权访问该安装记录', 403);
        }

        $this->success($row);
    }

    /**
     * 删除安装（软删除）
     * DELETE /api/installations/{id}
     */
    public function delete(int $id): void
    {
        $db = Database::getInstance();
        $user = $this->getCurrentUser() ?: [];
        $role = $user['role'] ?? '';

        if (!in_array($role, ['admin', 'project_admin'], true)) {
            $this->error('无权删除安装记录', 403);
        }

        $row = $db->fetch(
            "SELECT id, project_id, machine_id FROM {$db->table('installations')} WHERE id = ?",
            [$id]
        );
        if (!$row) {
            $this->error('安装记录不存在', 404);
        }
        if (!$this->canAccess((int) $row['project_id'], $user)) {
            $this->error('无权操作该项目的安装记录', 403);
        }

        $db->execute(
            "UPDATE {$db->table('installations')}
             SET status = 'deleted', deleted_at = NOW()
             WHERE id = ?",
            [$id]
        );

        logger('installation_delete', 'installation', $id, [
            'project_id' => (int) $row['project_id'],
            'machine_id' => $row['machine_id'],
        ]);

        $this->success(null, '已标记为已删除');
    }

    /**
     * 恢复安装
     * POST /api/installations/{id}/restore
     */
    public function restore(int $id): void
    {
        $db = Database::getInstance();
        $user = $this->getCurrentUser() ?: [];
        $role = $user['role'] ?? '';

        if (!in_array($role, ['admin', 'project_admin'], true)) {
            $this->error('无权恢复安装记录', 403);
        }

        $row = $db->fetch(
            "SELECT id, project_id, machine_id FROM {$db->table('installations')} WHERE id = ?",
            [$id]
        );
        if (!$row) {
            $this->error('安装记录不存在', 404);
        }
        if (!$this->canAccess((int) $row['project_id'], $user)) {
            $this->error('无权操作该项目的安装记录', 403);
        }

        $db->execute(
            "UPDATE {$db->table('installations')}
             SET status = 'active', deleted_at = NULL
             WHERE id = ?",
            [$id]
        );

        logger('installation_restore', 'installation', $id, [
            'project_id' => (int) $row['project_id'],
            'machine_id' => $row['machine_id'],
        ]);

        $this->success(null, '已恢复为存活');
    }

    /**
     * 批量删除
     * POST /api/installations/batch-delete
     */
    public function batchDelete(): void
    {
        $db = Database::getInstance();
        $user = $this->getCurrentUser() ?: [];
        $role = $user['role'] ?? '';

        if (!in_array($role, ['admin', 'project_admin'], true)) {
            $this->error('无权删除安装记录', 403);
        }

        $input = $this->getJsonInput();
        $ids = $input['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            $this->error('请选择要删除的安装记录');
        }
        $ids = array_map('intval', $ids);

        $deleted = 0;
        foreach ($ids as $id) {
            $row = $db->fetch(
                "SELECT id, project_id FROM {$db->table('installations')} WHERE id = ?",
                [$id]
            );
            if (!$row || !$this->canAccess((int) $row['project_id'], $user)) {
                continue;
            }
            $db->execute(
                "UPDATE {$db->table('installations')} SET status = 'deleted', deleted_at = NOW() WHERE id = ?",
                [$id]
            );
            $deleted++;
        }

        logger('installation_batch_delete', 'installation', 0, ['count' => $deleted]);
        $this->success(['deleted' => $deleted], "已删除 {$deleted} 条");
    }

    /**
     * 导出安装记录
     * GET /api/installations/export
     */
    public function export(): void
    {
        $db = Database::getInstance();
        $user = $this->getCurrentUser() ?: [];

        $filters = [
            'project_id' => $_GET['project_id'] ?? '',
            'status'     => $_GET['status'] ?? '',
            'keyword'    => trim($_GET['keyword'] ?? ''),
            'date_from'  => $_GET['date_from'] ?? '',
            'date_to'    => $_GET['date_to'] ?? '',
        ];
        $built = InstallationService::buildListWhere($user, $filters);

        $list = $db->fetchAll(
            "SELECT i.id, i.project_id, p.name AS project_name, i.machine_id, i.card_key, i.ip,
                    i.device_info, i.client_version, i.status, i.install_count,
                    i.installed_at, i.last_online_at, i.deleted_at
             FROM {$db->table('installations')} i
             LEFT JOIN {$db->table('projects')} p ON p.id = i.project_id
             WHERE 1=1 {$built['where']}
             ORDER BY i.id DESC
             LIMIT 5000",
            $built['params']
        );

        $headers = [
            'ID', '项目ID', '项目名称', '机器指纹', '卡密', 'IP', '设备信息',
            '客户端版本', '状态', '累计激活', '安装时间', '最后上线', '删除时间',
        ];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=installations_' . date('Ymd_His') . '.csv');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($list as $row) {
            fputcsv($out, [
                $row['id'],
                $row['project_id'],
                $row['project_name'] ?? '',
                $row['machine_id'],
                $row['card_key'] ?? '',
                $row['ip'] ?? '',
                $row['device_info'] ?? '',
                $row['client_version'] ?? '',
                $row['status'],
                $row['install_count'],
                $row['installed_at'],
                $row['last_online_at'] ?? '',
                $row['deleted_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    /**
     * 权限判断
     */
    private function canAccess(int $projectId, array $user): bool
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
