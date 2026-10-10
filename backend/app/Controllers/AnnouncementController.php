<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;

/**
 * 公告推送管理控制器
 */
class AnnouncementController extends Controller
{
    /**
     * 公告列表（管理端）
     * GET /api/announcements
     */
    public function list(): void
    {
        $db = Database::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = min((int) ($_GET['page_size'] ?? 20), 100);
        $offset = ($page - 1) * $pageSize;
        $keyword = trim($_GET['keyword'] ?? '');
        $type = $_GET['type'] ?? '';
        $target = $_GET['target'] ?? '';
        $status = $_GET['status'] ?? '';

        $where = 'WHERE 1=1';
        $params = [];

        if ($keyword !== '') {
            $where .= ' AND (title LIKE ? OR content LIKE ?)';
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        if ($type !== '' && in_array($type, ['system', 'update', 'promo', 'notice'], true)) {
            $where .= ' AND type = ?';
            $params[] = $type;
        }
        if ($target !== '' && in_array($target, ['all', 'admin', 'agent', 'client'], true)) {
            $where .= ' AND target = ?';
            $params[] = $target;
        }
        if ($status !== '') {
            $where .= ' AND status = ?';
            $params[] = (int) $status;
        }

        $total = (int) $db->fetchColumn("SELECT COUNT(*) FROM {$db->table('announcements')} {$where}", $params);
        $list = $db->fetchAll(
            "SELECT * FROM {$db->table('announcements')} {$where}
             ORDER BY is_top DESC, id DESC
             LIMIT {$offset}, {$pageSize}",
            $params
        );

        $this->success([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * 创建公告
     * POST /api/announcements
     */
    public function create(): void
    {
        $role = $this->getUserRole();
        if (!in_array($role, ['admin', 'project_admin'], true)) {
            $this->error('仅管理员可发布公告', 403);
        }

        $input = $this->getJsonInput();
        $validator = new Validator($input);
        if (!$validator->validate([
            'title' => 'required|min:1|max:200',
            'content' => 'required|min:1',
        ])) {
            $this->error($validator->getFirstError());
        }

        $type = in_array($input['type'] ?? '', ['system', 'update', 'promo', 'notice'], true)
            ? $input['type'] : 'notice';
        $target = in_array($input['target'] ?? '', ['all', 'admin', 'agent', 'client'], true)
            ? $input['target'] : 'all';
        $isTop = !empty($input['is_top']) ? 1 : 0;
        $isPopup = !empty($input['is_popup']) ? 1 : 0;
        $status = isset($input['status']) ? ((int) $input['status'] === 1 ? 1 : 0) : 1;
        $projectId = !empty($input['project_id']) ? (int) $input['project_id'] : null;
        $linkUrl = trim((string) ($input['link_url'] ?? ''));
        $startAt = !empty($input['start_at']) ? $input['start_at'] : null;
        $endAt = !empty($input['end_at']) ? $input['end_at'] : null;

        // 项目管理员只能发到自己的项目
        if ($role === 'project_admin') {
            $user = $this->getCurrentUser();
            $projectIds = $user['project_ids'] ?? [];
            if ($projectId === null || !in_array($projectId, array_map('intval', $projectIds), true)) {
                $this->error('只能发布到您绑定的项目', 403);
            }
        }

        $db = Database::getInstance();
        $id = $db->insert(
            "INSERT INTO {$db->table('announcements')}
             (title, content, type, target, project_id, is_top, is_popup, link_url, status, start_at, end_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                trim($input['title']),
                $input['content'],
                $type,
                $target,
                $projectId,
                $isTop,
                $isPopup,
                $linkUrl,
                $status,
                $startAt,
                $endAt,
                (int) $this->getUserId(),
            ]
        );

        logger('announcement_create', 'announcement', $id, [
            'title' => $input['title'],
            'type' => $type,
            'target' => $target,
        ]);

        $row = $db->fetch("SELECT * FROM {$db->table('announcements')} WHERE id = ?", [$id]);
        $this->success($row, '公告发布成功');
    }

    /**
     * 更新公告
     * PUT /api/announcements/{id}
     */
    public function update(int $id): void
    {
        $role = $this->getUserRole();
        if (!in_array($role, ['admin', 'project_admin'], true)) {
            $this->error('仅管理员可修改公告', 403);
        }

        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM {$db->table('announcements')} WHERE id = ?", [$id]);
        if (!$row) {
            $this->error('公告不存在', 404);
        }

        if ($role === 'project_admin') {
            $user = $this->getCurrentUser();
            $projectIds = array_map('intval', $user['project_ids'] ?? []);
            if ($row['project_id'] === null || !in_array((int) $row['project_id'], $projectIds, true)) {
                $this->error('无权修改该公告', 403);
            }
        }

        $input = $this->getJsonInput();
        $title = array_key_exists('title', $input) ? trim((string) $input['title']) : $row['title'];
        $content = array_key_exists('content', $input) ? (string) $input['content'] : $row['content'];
        if ($title === '' || $content === '') {
            $this->error('标题和内容不能为空');
        }

        $type = array_key_exists('type', $input) && in_array($input['type'], ['system', 'update', 'promo', 'notice'], true)
            ? $input['type'] : $row['type'];
        $target = array_key_exists('target', $input) && in_array($input['target'], ['all', 'admin', 'agent', 'client'], true)
            ? $input['target'] : $row['target'];
        $isTop = array_key_exists('is_top', $input) ? (!empty($input['is_top']) ? 1 : 0) : (int) $row['is_top'];
        $isPopup = array_key_exists('is_popup', $input) ? (!empty($input['is_popup']) ? 1 : 0) : (int) $row['is_popup'];
        $status = array_key_exists('status', $input) ? ((int) $input['status'] === 1 ? 1 : 0) : (int) $row['status'];
        $linkUrl = array_key_exists('link_url', $input) ? trim((string) $input['link_url']) : ($row['link_url'] ?? '');
        $startAt = array_key_exists('start_at', $input) ? ($input['start_at'] ?: null) : $row['start_at'];
        $endAt = array_key_exists('end_at', $input) ? ($input['end_at'] ?: null) : $row['end_at'];

        $db->execute(
            "UPDATE {$db->table('announcements')}
             SET title = ?, content = ?, type = ?, target = ?, is_top = ?, is_popup = ?,
                 link_url = ?, status = ?, start_at = ?, end_at = ?
             WHERE id = ?",
            [$title, $content, $type, $target, $isTop, $isPopup, $linkUrl, $status, $startAt, $endAt, $id]
        );

        logger('announcement_update', 'announcement', $id, ['title' => $title, 'status' => $status]);
        $this->success($db->fetch("SELECT * FROM {$db->table('announcements')} WHERE id = ?", [$id]), '更新成功');
    }

    /**
     * 删除公告
     * DELETE /api/announcements/{id}
     */
    public function delete(int $id): void
    {
        $role = $this->getUserRole();
        if (!in_array($role, ['admin', 'project_admin'], true)) {
            $this->error('仅管理员可删除公告', 403);
        }

        $db = Database::getInstance();
        $row = $db->fetch("SELECT id, title, project_id FROM {$db->table('announcements')} WHERE id = ?", [$id]);
        if (!$row) {
            $this->error('公告不存在', 404);
        }

        if ($role === 'project_admin') {
            $user = $this->getCurrentUser();
            $projectIds = array_map('intval', $user['project_ids'] ?? []);
            if ($row['project_id'] === null || !in_array((int) $row['project_id'], $projectIds, true)) {
                $this->error('无权删除该公告', 403);
            }
        }

        $db->execute("DELETE FROM {$db->table('announcements')} WHERE id = ?", [$id]);
        logger('announcement_delete', 'announcement', $id, ['title' => $row['title']]);
        $this->success(null, '公告已删除');
    }

    /**
     * 批量删除
     * POST /api/announcements/batch-delete
     */
    public function batchDelete(): void
    {
        $role = $this->getUserRole();
        if (!in_array($role, ['admin', 'project_admin'], true)) {
            $this->error('仅管理员可删除公告', 403);
        }

        $input = $this->getJsonInput();
        $ids = array_filter(array_map('intval', (array) ($input['ids'] ?? [])));
        if (empty($ids)) {
            $this->error('请选择要删除的公告');
        }

        $db = Database::getInstance();
        $deleted = 0;
        foreach ($ids as $id) {
            $row = $db->fetch("SELECT id, project_id FROM {$db->table('announcements')} WHERE id = ?", [$id]);
            if (!$row) {
                continue;
            }
            if ($role === 'project_admin') {
                $user = $this->getCurrentUser();
                $projectIds = array_map('intval', $user['project_ids'] ?? []);
                if ($row['project_id'] === null || !in_array((int) $row['project_id'], $projectIds, true)) {
                    continue;
                }
            }
            $db->execute("DELETE FROM {$db->table('announcements')} WHERE id = ?", [$id]);
            $deleted++;
        }

        $this->success(['deleted' => $deleted], "已删除 {$deleted} 条公告");
    }

    /**
     * 公告详情
     * GET /api/announcements/{id}
     */
    public function detail(int $id): void
    {
        $db = Database::getInstance();
        $row = $db->fetch("SELECT * FROM {$db->table('announcements')} WHERE id = ?", [$id]);
        if (!$row) {
            $this->error('公告不存在', 404);
        }
        $this->success($row);
    }
}
