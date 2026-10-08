<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\VersionService;

/**
 * 项目版本/安装包管理控制器
 * 发布/修改仅 admin + project_admin；agent 只读
 */
class ProjectVersionController extends Controller
{
    private const ALLOWED_EXT = ['zip', 'tar', 'gz', 'tgz', '7z', 'exe', 'dmg', 'apk', 'msi', 'pkg', 'rar', 'bin'];
    private const MAX_SIZE = 200 * 1024 * 1024; // 200MB

    /**
     * 版本列表
     * GET /api/projects/{projectId}/versions
     */
    public function list(int $projectId): void
    {
        $user = $this->getCurrentUser() ?: [];
        if (!VersionService::assertProjectAccess($projectId, $user)) {
            $this->error('无权访问该项目', 403);
        }

        $db = Database::getInstance();
        $list = $db->fetchAll(
            "SELECT v.*, p.name AS project_name
             FROM {$db->table('project_versions')} v
             LEFT JOIN {$db->table('projects')} p ON p.id = v.project_id
             WHERE v.project_id = ?
             ORDER BY v.id DESC",
            [$projectId]
        );

        $this->success($list);
    }

    /**
     * 版本详情
     * GET /api/projects/{projectId}/versions/{id}
     */
    public function detail(int $projectId, int $id): void
    {
        $user = $this->getCurrentUser() ?: [];
        if (!VersionService::assertProjectAccess($projectId, $user)) {
            $this->error('无权访问该项目', 403);
        }

        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT v.*, p.name AS project_name
             FROM {$db->table('project_versions')} v
             LEFT JOIN {$db->table('projects')} p ON p.id = v.project_id
             WHERE v.id = ? AND v.project_id = ?",
            [$id, $projectId]
        );
        if (!$row) {
            $this->error('版本不存在', 404);
        }

        $this->success($row);
    }

    /**
     * 上传发布版本
     * POST /api/projects/{projectId}/versions  (multipart)
     */
    public function create(int $projectId): void
    {
        $user = $this->getCurrentUser() ?: [];
        $role = $user['role'] ?? '';

        if (!VersionService::canPublish($role)) {
            $this->error('代理账号不可发布版本', 403);
        }
        if (!VersionService::assertProjectAccess($projectId, $user)) {
            $this->error('无权操作该项目', 403);
        }

        $version = trim($_POST['version'] ?? '');
        if ($version === '') {
            $this->error('版本号不能为空');
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->error('请上传安装包文件');
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            $this->error('仅支持 ' . implode('/', self::ALLOWED_EXT) . ' 格式的安装包');
        }
        if ($file['size'] > self::MAX_SIZE) {
            $this->error('安装包大小不能超过 200MB');
        }

        $db = Database::getInstance();
        $exists = $db->fetch(
            "SELECT id FROM {$db->table('project_versions')} WHERE project_id = ? AND version = ?",
            [$projectId, $version]
        );
        if ($exists) {
            $this->error('该版本号已存在');
        }

        $uploadDir = BASE_PATH . '/public/uploads/packages/' . $projectId;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $this->error('创建上传目录失败');
        }

        // 安全文件名：随机 + 原扩展名
        $safeName = 'pkg_' . $projectId . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $filepath = $uploadDir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->error('安装包上传失败，请重试');
        }

        $checksum = '';
        if (is_file($filepath)) {
            $checksum = @hash_file('sha256', $filepath) ?: '';
        }

        $fileSize = (int) $file['size'];
        $isLatest = isset($_POST['is_latest']) ? (int) $_POST['is_latest'] : 1;
        $isForce = isset($_POST['is_force']) ? (int) $_POST['is_force'] : 0;
        $isForce = $isForce === 1 ? 1 : 0;
        $isLatest = $isLatest === 0 ? 0 : 1;
        $minVersion = trim($_POST['min_client_version'] ?? '');
        $changelog = $_POST['changelog'] ?? '';
        $filePath = '/uploads/packages/' . $projectId . '/' . $safeName;

        $db->beginTransaction();
        try {
            if ($isLatest) {
                $db->execute(
                    "UPDATE {$db->table('project_versions')} SET is_latest = 0 WHERE project_id = ?",
                    [$projectId]
                );
            }

            $versionId = $db->insert(
                "INSERT INTO {$db->table('project_versions')}
                 (project_id, version, file_name, file_path, file_size, checksum, changelog,
                  min_client_version, is_force, is_latest, download_count, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?)",
                [
                    $projectId,
                    $version,
                    $file['name'],
                    $filePath,
                    $fileSize,
                    $checksum,
                    $changelog !== '' ? $changelog : null,
                    $minVersion,
                    $isForce,
                    $isLatest,
                    (int) ($user['id'] ?? 0),
                ]
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            @unlink($filepath);
            $this->error('版本发布失败: ' . $e->getMessage());
        }

        logger('version_publish', 'project_version', $versionId, [
            'project_id' => $projectId,
            'version'    => $version,
            'is_force'   => $isForce,
            'is_latest'  => $isLatest,
        ]);

        $row = $db->fetch(
            "SELECT * FROM {$db->table('project_versions')} WHERE id = ?",
            [$versionId]
        );

        $this->success($row, '版本发布成功');
    }

    /**
     * 更新版本信息（changelog / force / min_version / status）
     * PUT /api/versions/{id}
     */
    public function update(int $id): void
    {
        $user = $this->getCurrentUser() ?: [];
        if (!VersionService::canPublish($user['role'] ?? '')) {
            $this->error('代理账号不可修改版本', 403);
        }

        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM {$db->table('project_versions')} WHERE id = ?",
            [$id]
        );
        if (!$row) {
            $this->error('版本不存在', 404);
        }
        if (!VersionService::assertProjectAccess((int) $row['project_id'], $user)) {
            $this->error('无权操作该项目的版本', 403);
        }

        $input = $this->getJsonInput();

        $changelog = array_key_exists('changelog', $input) ? $input['changelog'] : $row['changelog'];
        $minVersion = array_key_exists('min_client_version', $input) ? trim((string) $input['min_client_version']) : ($row['min_client_version'] ?? '');
        $isForce = array_key_exists('is_force', $input) ? ((int) $input['is_force'] === 1 ? 1 : 0) : (int) $row['is_force'];
        $status = array_key_exists('status', $input) ? ((int) $input['status'] === 1 ? 1 : 0) : (int) $row['status'];

        $db->execute(
            "UPDATE {$db->table('project_versions')}
             SET changelog = ?, min_client_version = ?, is_force = ?, status = ?
             WHERE id = ?",
            [
                $changelog !== '' ? $changelog : null,
                $minVersion,
                $isForce,
                $status,
                $id,
            ]
        );

        logger('version_update', 'project_version', $id, [
            'version'  => $row['version'],
            'is_force' => $isForce,
        ]);

        $this->success($db->fetch("SELECT * FROM {$db->table('project_versions')} WHERE id = ?", [$id]), '更新成功');
    }

    /**
     * 切换强制推送
     * PUT /api/versions/{id}/force
     */
    public function toggleForce(int $id): void
    {
        $user = $this->getCurrentUser() ?: [];
        if (!VersionService::canPublish($user['role'] ?? '')) {
            $this->error('代理账号不可修改强制推送', 403);
        }

        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM {$db->table('project_versions')} WHERE id = ?",
            [$id]
        );
        if (!$row) {
            $this->error('版本不存在', 404);
        }
        if (!VersionService::assertProjectAccess((int) $row['project_id'], $user)) {
            $this->error('无权操作该项目的版本', 403);
        }

        $input = $this->getJsonInput();
        if (array_key_exists('is_force', $input)) {
            $isForce = ((int) $input['is_force'] === 1) ? 1 : 0;
        } else {
            $isForce = ((int) $row['is_force'] === 1) ? 0 : 1;
        }

        $db->execute(
            "UPDATE {$db->table('project_versions')} SET is_force = ? WHERE id = ?",
            [$isForce, $id]
        );

        logger('version_force_toggle', 'project_version', $id, [
            'version'  => $row['version'],
            'is_force' => $isForce,
        ]);

        $this->success(
            ['id' => $id, 'is_force' => $isForce],
            $isForce ? '已开启强制推送' : '已关闭强制推送'
        );
    }

    /**
     * 设为最新版本
     * PUT /api/versions/{id}/latest
     */
    public function setLatest(int $id): void
    {
        $user = $this->getCurrentUser() ?: [];
        if (!VersionService::canPublish($user['role'] ?? '')) {
            $this->error('代理账号不可设置最新版本', 403);
        }

        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM {$db->table('project_versions')} WHERE id = ?",
            [$id]
        );
        if (!$row) {
            $this->error('版本不存在', 404);
        }
        if (!VersionService::assertProjectAccess((int) $row['project_id'], $user)) {
            $this->error('无权操作该项目的版本', 403);
        }

        $db->beginTransaction();
        try {
            $db->execute(
                "UPDATE {$db->table('project_versions')} SET is_latest = 0 WHERE project_id = ?",
                [(int) $row['project_id']]
            );
            $db->execute(
                "UPDATE {$db->table('project_versions')} SET is_latest = 1, status = 1 WHERE id = ?",
                [$id]
            );
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            $this->error('设置最新版本失败');
        }

        logger('version_set_latest', 'project_version', $id, ['version' => $row['version']]);
        $this->success(null, '已设为最新版本');
    }

    /**
     * 删除版本
     * DELETE /api/versions/{id}
     */
    public function delete(int $id): void
    {
        $user = $this->getCurrentUser() ?: [];
        if (!VersionService::canPublish($user['role'] ?? '')) {
            $this->error('代理账号不可删除版本', 403);
        }

        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT * FROM {$db->table('project_versions')} WHERE id = ?",
            [$id]
        );
        if (!$row) {
            $this->error('版本不存在', 404);
        }
        if (!VersionService::assertProjectAccess((int) $row['project_id'], $user)) {
            $this->error('无权操作该项目的版本', 403);
        }

        $db->execute("DELETE FROM {$db->table('project_versions')} WHERE id = ?", [$id]);

        // 删除物理文件
        $abs = BASE_PATH . '/public' . $row['file_path'];
        if (is_file($abs)) {
            @unlink($abs);
        }

        logger('version_delete', 'project_version', $id, [
            'project_id' => (int) $row['project_id'],
            'version'    => $row['version'],
        ]);

        $this->success(null, '版本已删除');
    }
}
