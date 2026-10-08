<template>
  <div class="versions-page">
    <el-card shadow="never" class="main-card">
      <div class="page-header">
        <div>
          <div class="page-title">版本推送管理</div>
          <div class="page-sub">上传最新安装包并推送客户端下载；可开启强制更新提示</div>
        </div>
        <div class="header-actions">
          <el-select
            v-model="projectId"
            placeholder="选择项目"
            style="width: 200px"
            :loading="projectsLoading"
            @change="onProjectChange"
          >
            <el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
          <el-button
            v-if="canPublish && projectId"
            type="primary"
            :icon="Upload"
            @click="openUploadDialog"
          >发布新版本</el-button>
          <el-button :icon="Refresh" circle @click="refresh" :disabled="!projectId" />
        </div>
      </div>

      <el-alert
        v-if="isAgent"
        title="代理账号仅可查看版本列表，不可发布/修改/删除版本"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />

      <el-table
        :data="list"
        v-loading="loading"
        stripe
        border
        row-key="id"
        class="data-table"
        :max-height="560"
      >
        <el-table-column prop="version" label="版本号" width="120">
          <template #default="{ row }">
            <el-tag v-if="row.is_latest == 1" type="success" size="small" style="margin-right:6px">最新</el-tag>
            <span class="mono-text">{{ row.version }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="file_name" label="文件名" min-width="200" show-overflow-tooltip />
        <el-table-column label="大小" width="100" align="center">
          <template #default="{ row }">{{ formatSize(row.file_size) }}</template>
        </el-table-column>
        <el-table-column prop="download_count" label="下载次数" width="100" align="center" />
        <el-table-column prop="is_force" label="强制推送" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_force == 1 ? 'danger' : 'info'" size="small">
              {{ row.is_force == 1 ? '强制' : '普通' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="min_client_version" label="最低版本" width="110" align="center">
          <template #default="{ row }">{{ row.min_client_version || '-' }}</template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status == 1 ? 'success' : 'info'" size="small">
              {{ row.status == 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="changelog" label="更新说明" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">{{ row.changelog || '-' }}</template>
        </el-table-column>
        <el-table-column prop="created_at" label="发布时间" width="170">
          <template #default="{ row }">{{ formatBj(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="240" fixed="right" align="center">
          <template #default="{ row }">
            <template v-if="canPublish">
              <el-button
                v-if="row.is_latest != 1"
                size="small"
                type="success"
                link
                @click="handleSetLatest(row)"
              >设为最新</el-button>
              <el-button
                size="small"
                :type="row.is_force == 1 ? 'info' : 'danger'"
                link
                @click="handleToggleForce(row)"
              >{{ row.is_force == 1 ? '取消强制' : '强制推送' }}</el-button>
              <el-button size="small" type="primary" link @click="openEditDialog(row)">编辑</el-button>
              <el-button size="small" type="danger" link @click="handleDelete(row)">删除</el-button>
            </template>
            <span v-else class="readonly-text">只读</span>
          </template>
        </el-table-column>
      </el-table>

      <el-empty v-if="!loading && !list.length" description="暂无版本，请先发布安装包" />
    </el-card>

    <!-- 上传发布弹窗 -->
    <el-dialog
      v-model="uploadVisible"
      title="发布新版本"
      width="560px"
      :close-on-click-modal="false"
      destroy-on-close
      @closed="resetUploadForm"
    >
      <el-form ref="uploadFormRef" :model="uploadForm" :rules="uploadRules" label-width="110px">
        <el-form-item label="项目">
          <el-input :model-value="currentProjectName" disabled />
        </el-form-item>
        <el-form-item label="版本号" prop="version">
          <el-input v-model="uploadForm.version" placeholder="如 1.2.0" />
        </el-form-item>
        <el-form-item label="安装包" prop="file">
          <el-upload
            class="package-uploader"
            drag
            :auto-upload="false"
            :limit="1"
            accept=".zip,.tar,.gz,.tgz,.7z,.exe,.dmg,.apk,.msi,.pkg,.rar,.bin"
            :on-change="handleFileChange"
            :on-remove="handleFileRemove"
            ref="uploadRef"
          >
            <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
            <div class="el-upload__text">拖拽文件到此处，或<em>点击选择</em></div>
            <template #tip>
              <div class="el-upload__tip">
                支持 zip/tar/gz/7z/exe/dmg/apk/msi/pkg/rar/bin，最大 200MB
              </div>
            </template>
          </el-upload>
        </el-form-item>
        <el-form-item label="更新说明">
          <el-input v-model="uploadForm.changelog" type="textarea" :rows="4" placeholder="本次更新内容" />
        </el-form-item>
        <el-form-item label="强制推送">
          <el-switch v-model="uploadForm.is_force" :active-value="1" :inactive-value="0" />
          <span class="form-tip">开启后客户端将收到强制更新提示（仅提示，不阻断授权）</span>
        </el-form-item>
        <el-form-item label="最低强制版本">
          <el-input v-model="uploadForm.min_client_version" placeholder="选填，如 1.0.0；低于此版本强制更新" />
        </el-form-item>
        <el-form-item label="设为最新">
          <el-switch v-model="uploadForm.is_latest" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="uploadVisible = false">取消</el-button>
        <el-button type="primary" :loading="uploadLoading" @click="handleUpload">发布</el-button>
      </template>
    </el-dialog>

    <!-- 编辑弹窗 -->
    <el-dialog v-model="editVisible" title="编辑版本" width="480px" :close-on-click-modal="false" destroy-on-close>
      <el-form :model="editForm" label-width="110px">
        <el-form-item label="版本号">
          <el-input :model-value="editForm.version" disabled />
        </el-form-item>
        <el-form-item label="更新说明">
          <el-input v-model="editForm.changelog" type="textarea" :rows="4" />
        </el-form-item>
        <el-form-item label="强制推送">
          <el-switch v-model="editForm.is_force" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="最低强制版本">
          <el-input v-model="editForm.min_client_version" placeholder="选填" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="editForm.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" :loading="editLoading" @click="handleEditSubmit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Upload, Refresh, UploadFilled } from '@element-plus/icons-vue'
import request from '@/api'
import versionApi from '@/api/versions'

const route = useRoute()
const userStore = useUserStore()
const user = computed(() => userStore.user)
const isAgent = computed(() => user.value?.role === 'agent')
const canPublish = computed(() => ['admin', 'project_admin'].includes(user.value?.role))

const projects = ref([])
const projectsLoading = ref(false)
const projectId = ref(route.query.project_id ? Number(route.query.project_id) : null)
const list = ref([])
const loading = ref(false)

const uploadVisible = ref(false)
const uploadLoading = ref(false)
const uploadFormRef = ref(null)
const uploadRef = ref(null)
const uploadFile = ref(null)
const uploadForm = ref({
  version: '',
  changelog: '',
  is_force: 0,
  min_client_version: '',
  is_latest: 1
})
const uploadRules = {
  version: [{ required: true, message: '请输入版本号', trigger: 'blur' }],
  file: [{ required: true, message: '请选择安装包文件', trigger: 'change' }]
}

const editVisible = ref(false)
const editLoading = ref(false)
const editForm = ref({ id: null, version: '', changelog: '', is_force: 0, min_client_version: '', status: 1 })

const currentProjectName = computed(() => {
  const p = projects.value.find(i => Number(i.id) === Number(projectId.value))
  return p ? p.name : ''
})

function formatBj(value) {
  if (!value || value === '0000-00-00 00:00:00') return '-'
  const raw = String(value).trim()
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(raw)) return raw
  const d = new Date(raw.includes('T') || raw.includes('+') || raw.includes('Z') ? raw : raw.replace(' ', 'T') + '+08:00')
  if (Number.isNaN(d.getTime())) return raw
  const pad = n => String(n).padStart(2, '0')
  const bj = new Date(d.getTime() + 8 * 3600 * 1000)
  return `${bj.getUTCFullYear()}-${pad(bj.getUTCMonth() + 1)}-${pad(bj.getUTCDate())} ${pad(bj.getUTCHours())}:${pad(bj.getUTCMinutes())}:${pad(bj.getUTCSeconds())}`
}

function formatSize(bytes) {
  const n = Number(bytes || 0)
  if (!n) return '-'
  if (n < 1024) return n + ' B'
  if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB'
  if (n < 1024 * 1024 * 1024) return (n / 1024 / 1024).toFixed(2) + ' MB'
  return (n / 1024 / 1024 / 1024).toFixed(2) + ' GB'
}

async function fetchProjects() {
  projectsLoading.value = true
  try {
    const res = await request.get('/projects/all')
    let data = res.data.list || res.data || []
    if (!Array.isArray(data)) data = []
    projects.value = data
    if (!projectId.value && data.length) {
      projectId.value = Number(data[0].id)
      await refresh()
    }
  } catch {
    projects.value = []
  } finally {
    projectsLoading.value = false
  }
}

async function refresh() {
  if (!projectId.value) {
    list.value = []
    return
  }
  loading.value = true
  try {
    const res = await versionApi.list(projectId.value)
    list.value = Array.isArray(res.data) ? res.data : []
  } catch (e) {
    console.error(e)
    list.value = []
  } finally {
    loading.value = false
  }
}

function onProjectChange() {
  refresh()
}

function openUploadDialog() {
  if (!projectId.value) {
    ElMessage.warning('请先选择项目')
    return
  }
  uploadVisible.value = true
}

function resetUploadForm() {
  uploadForm.value = { version: '', changelog: '', is_force: 0, min_client_version: '', is_latest: 1 }
  uploadFile.value = null
  if (uploadRef.value) uploadRef.value.clearFiles()
}

function handleFileChange(file) {
  uploadFile.value = file.raw
  uploadForm.value.file = file.raw
}

function handleFileRemove() {
  uploadFile.value = null
  uploadForm.value.file = null
}

async function handleUpload() {
  if (!uploadFormRef.value) return
  await uploadFormRef.value.validate(async (valid) => {
    if (!valid) return
    if (!uploadFile.value) {
      ElMessage.warning('请选择安装包文件')
      return
    }
    uploadLoading.value = true
    try {
      const fd = new FormData()
      fd.append('version', uploadForm.value.version)
      fd.append('file', uploadFile.value)
      fd.append('changelog', uploadForm.value.changelog || '')
      fd.append('is_force', uploadForm.value.is_force)
      fd.append('min_client_version', uploadForm.value.min_client_version || '')
      fd.append('is_latest', uploadForm.value.is_latest)
      await versionApi.create(projectId.value, fd)
      ElMessage.success('版本发布成功')
      uploadVisible.value = false
      refresh()
    } catch {} finally {
      uploadLoading.value = false
    }
  })
}

function openEditDialog(row) {
  editForm.value = {
    id: row.id,
    version: row.version,
    changelog: row.changelog || '',
    is_force: Number(row.is_force) || 0,
    min_client_version: row.min_client_version || '',
    status: Number(row.status) || 1
  }
  editVisible.value = true
}

async function handleEditSubmit() {
  editLoading.value = true
  try {
    await versionApi.update(editForm.value.id, {
      changelog: editForm.value.changelog,
      is_force: editForm.value.is_force,
      min_client_version: editForm.value.min_client_version,
      status: editForm.value.status
    })
    ElMessage.success('更新成功')
    editVisible.value = false
    refresh()
  } catch {} finally {
    editLoading.value = false
  }
}

async function handleToggleForce(row) {
  try {
    const next = Number(row.is_force) === 1 ? 0 : 1
    const tip = next === 1
      ? '确定对该版本开启强制推送？客户端将收到强制更新提示。'
      : '确定取消该版本的强制推送？'
    await ElMessageBox.confirm(tip, '提示', { type: 'warning' })
    await versionApi.toggleForce(row.id, next)
    ElMessage.success(next === 1 ? '已开启强制推送' : '已取消强制推送')
    refresh()
  } catch {}
}

async function handleSetLatest(row) {
  try {
    await ElMessageBox.confirm(`确定将 ${row.version} 设为最新版本并推送？`, '提示', { type: 'warning' })
    await versionApi.setLatest(row.id)
    ElMessage.success('已设为最新版本')
    refresh()
  } catch {}
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确定删除版本 ${row.version}？将同时删除安装包文件。`, '提示', { type: 'warning' })
    await versionApi.remove(row.id)
    ElMessage.success('删除成功')
    refresh()
  } catch {}
}

onMounted(() => {
  fetchProjects()
})
</script>

<style scoped>
.versions-page { max-width: 1600px; margin: 0 auto; }
.main-card { border-radius: 16px; border: 1px solid #f0f0f4; }
.main-card:deep(.el-card__body) { padding: 24px; }
.page-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  margin-bottom: 20px; flex-wrap: wrap; gap: 12px;
}
.page-title { font-size: 18px; font-weight: 700; color: #1d1d1f; }
.page-sub { font-size: 13px; color: #9ca3af; margin-top: 4px; }
.header-actions { display: flex; gap: 10px; align-items: center; }
.header-actions :deep(.el-button) { border-radius: 10px; font-weight: 500; }
.data-table { border-radius: 12px; overflow: hidden; }
.data-table:deep(.el-table__header th) { background: #f8f9fb; font-weight: 600; color: #374151; font-size: 13px; border-color: #f0f0f4; }
.data-table:deep(.el-table__body td) { font-size: 13px; color: #374151; border-color: #f5f5f7; }
.mono-text { font-family: 'SF Mono', Cascadia Code, Courier New, monospace; font-size: 13px; }
.form-tip { margin-left: 10px; font-size: 12px; color: #9ca3af; }
.readonly-text { color: #9ca3af; font-size: 12px; }
.package-uploader { width: 100%; }
.package-uploader:deep(.el-upload-dragger) { border-radius: 12px; }
@media (max-width: 768px) {
  .main-card:deep(.el-card__body) { padding: 14px; }
  .header-actions { width: 100%; flex-wrap: wrap; }
}
</style>
