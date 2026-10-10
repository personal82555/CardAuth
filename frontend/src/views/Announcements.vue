<template>
  <div class="announcements-page">
    <el-card shadow="never" class="main-card">
      <div class="page-header">
        <div>
          <div class="page-title">公告推送</div>
          <div class="page-sub">发布系统通知/更新/活动公告，客户端与商城可拉取；支持置顶与弹窗</div>
        </div>
        <div class="header-actions">
          <el-button type="primary" :icon="Plus" @click="openCreate">发布公告</el-button>
          <el-button type="danger" :icon="Delete" :disabled="!selected.length" @click="batchRemove">
            批量删除 ({{ selected.length }})
          </el-button>
          <el-button :icon="Refresh" circle @click="refresh" />
        </div>
      </div>

      <div class="toolbar">
        <el-input v-model="filters.keyword" placeholder="标题/内容" clearable style="width: 200px" @clear="handleSearch" @keyup.enter="handleSearch" />
        <el-select v-model="filters.type" placeholder="全部类型" clearable style="width: 120px" @change="handleSearch">
          <el-option label="系统" value="system" />
          <el-option label="更新" value="update" />
          <el-option label="活动" value="promo" />
          <el-option label="通知" value="notice" />
        </el-select>
        <el-select v-model="filters.target" placeholder="推送对象" clearable style="width: 120px" @change="handleSearch">
          <el-option label="全部" value="all" />
          <el-option label="管理端" value="admin" />
          <el-option label="代理端" value="agent" />
          <el-option label="客户端" value="client" />
        </el-select>
        <el-select v-model="filters.status" placeholder="状态" clearable style="width: 110px" @change="handleSearch">
          <el-option label="发布中" :value="1" />
          <el-option label="已下线" :value="0" />
        </el-select>
        <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
      </div>

      <el-table :data="list" v-loading="loading" stripe border row-key="id" class="data-table" @selection-change="onSelect" :max-height="520">
        <el-table-column type="selection" width="50" align="center" fixed="left" />
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            <el-tag v-if="row.is_top == 1" type="warning" size="small" style="margin-right:6px">置顶</el-tag>
            <span class="title-text">{{ row.title }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="type" label="类型" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="target" label="对象" width="90" align="center">
          <template #default="{ row }">{{ targetLabel(row.target) }}</template>
        </el-table-column>
        <el-table-column prop="is_popup" label="弹窗" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_popup == 1 ? 'danger' : 'info'" size="small">{{ row.is_popup == 1 ? '是' : '否' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status == 1 ? 'success' : 'info'" size="small">{{ row.status == 1 ? '发布中' : '下线' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="发布时间" width="170">
          <template #default="{ row }">{{ formatBj(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="openEdit(row)">编辑</el-button>
            <el-button size="small" :type="row.status == 1 ? 'warning' : 'success'" link @click="toggleStatus(row)">
              {{ row.status == 1 ? '下线' : '上线' }}
            </el-button>
            <el-button size="small" type="danger" link @click="removeOne(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="fetchList"
          @current-change="fetchList"
        />
      </div>
    </el-card>

    <!-- 创建/编辑 -->
    <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑公告' : '发布公告'" width="640px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item label="标题" prop="title">
          <el-input v-model="form.title" maxlength="200" show-word-limit placeholder="公告标题" />
        </el-form-item>
        <el-form-item label="内容" prop="content">
          <el-input v-model="form.content" type="textarea" :rows="6" maxlength="2000" show-word-limit placeholder="公告正文，支持换行" />
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="form.type" style="width: 160px">
            <el-option label="通知" value="notice" />
            <el-option label="系统" value="system" />
            <el-option label="更新" value="update" />
            <el-option label="活动" value="promo" />
          </el-select>
        </el-form-item>
        <el-form-item label="推送对象">
          <el-select v-model="form.target" style="width: 160px">
            <el-option label="全部" value="all" />
            <el-option label="管理端" value="admin" />
            <el-option label="代理端" value="agent" />
            <el-option label="客户端" value="client" />
          </el-select>
        </el-form-item>
        <el-form-item label="关联项目">
          <el-select v-model="form.project_id" placeholder="全部项目" clearable style="width: 200px">
            <el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="选项">
          <el-checkbox v-model="form.is_top" :true-value="1" :false-value="0">置顶</el-checkbox>
          <el-checkbox v-model="form.is_popup" :true-value="1" :false-value="0" style="margin-left:16px">客户端弹窗</el-checkbox>
          <el-checkbox v-model="form.status" :true-value="1" :false-value="0" style="margin-left:16px">立即发布</el-checkbox>
        </el-form-item>
        <el-form-item label="跳转链接">
          <el-input v-model="form.link_url" placeholder="选填，https://..." />
        </el-form-item>
        <el-form-item label="起止时间">
          <el-date-picker
            v-model="timeRange"
            type="datetimerange"
            range-separator="至"
            start-placeholder="开始(空=立即)"
            end-placeholder="结束(空=永久)"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 100%"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submit">{{ isEdit ? '保存' : '发布' }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete, Refresh, Search } from '@element-plus/icons-vue'
import request from '@/api'
import announcementApi from '@/api/announcements'

const list = ref([])
const loading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const selected = ref([])
const projects = ref([])

const filters = ref({ keyword: '', type: '', target: '', status: '' })

const dialogVisible = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const submitting = ref(false)
const formRef = ref(null)
const timeRange = ref(null)
const form = ref({
  title: '',
  content: '',
  type: 'notice',
  target: 'all',
  project_id: null,
  is_top: 0,
  is_popup: 0,
  status: 1,
  link_url: ''
})
const rules = {
  title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
  content: [{ required: true, message: '请输入内容', trigger: 'blur' }]
}

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

function typeLabel(t) {
  return ({ system: '系统', update: '更新', promo: '活动', notice: '通知' })[t] || t
}
function typeTag(t) {
  return ({ system: 'primary', update: 'success', promo: 'danger', notice: 'info' })[t] || 'info'
}
function targetLabel(t) {
  return ({ all: '全部', admin: '管理端', agent: '代理端', client: '客户端' })[t] || t
}

async function fetchProjects() {
  try {
    const res = await request.get('/projects/all')
    let data = res.data.list || res.data || []
    projects.value = Array.isArray(data) ? data : []
  } catch {
    projects.value = []
  }
}

async function fetchList() {
  loading.value = true
  try {
    const params = {
      page: page.value,
      page_size: pageSize.value,
      keyword: filters.value.keyword || '',
      type: filters.value.type || '',
      target: filters.value.target || '',
      status: filters.value.status === '' ? '' : filters.value.status
    }
    Object.keys(params).forEach(k => {
      if (params[k] === '' || params[k] === null || params[k] === undefined) delete params[k]
    })
    const res = await announcementApi.list(params)
    list.value = res.data.list || []
    total.value = res.data.total || 0
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function refresh() {
  page.value = 1
  fetchList()
}
function handleSearch() {
  refresh()
}
function onSelect(rows) {
  selected.value = rows
}

function resetForm() {
  form.value = {
    title: '', content: '', type: 'notice', target: 'all', project_id: null,
    is_top: 0, is_popup: 0, status: 1, link_url: ''
  }
  timeRange.value = null
}

function openCreate() {
  isEdit.value = false
  editingId.value = null
  resetForm()
  dialogVisible.value = true
}

function openEdit(row) {
  isEdit.value = true
  editingId.value = row.id
  form.value = {
    title: row.title,
    content: row.content,
    type: row.type,
    target: row.target,
    project_id: row.project_id ? Number(row.project_id) : null,
    is_top: Number(row.is_top) || 0,
    is_popup: Number(row.is_popup) || 0,
    status: Number(row.status) || 0,
    link_url: row.link_url || ''
  }
  timeRange.value = row.start_at || row.end_at ? [row.start_at, row.end_at] : null
  dialogVisible.value = true
}

async function submit() {
  if (!formRef.value) return
  await formRef.value.validate(async valid => {
    if (!valid) return
    submitting.value = true
    try {
      const payload = {
        ...form.value,
        start_at: timeRange.value?.[0] || null,
        end_at: timeRange.value?.[1] || null
      }
      if (isEdit.value) {
        await announcementApi.update(editingId.value, payload)
        ElMessage.success('更新成功')
      } else {
        await announcementApi.create(payload)
        ElMessage.success('公告发布成功')
      }
      dialogVisible.value = false
      fetchList()
    } catch {} finally {
      submitting.value = false
    }
  })
}

async function toggleStatus(row) {
  try {
    const next = row.status == 1 ? 0 : 1
    await announcementApi.update(row.id, { status: next })
    ElMessage.success(next === 1 ? '已上线' : '已下线')
    fetchList()
  } catch {}
}

async function removeOne(row) {
  try {
    await ElMessageBox.confirm(`确定删除公告「${row.title}」？`, '提示', { type: 'warning' })
    await announcementApi.remove(row.id)
    ElMessage.success('已删除')
    fetchList()
  } catch {}
}

async function batchRemove() {
  if (!selected.value.length) return
  try {
    await ElMessageBox.confirm(`确定删除选中的 ${selected.value.length} 条公告？`, '批量删除', { type: 'warning' })
    await announcementApi.batchDelete(selected.value.map(r => r.id))
    ElMessage.success('批量删除成功')
    selected.value = []
    fetchList()
  } catch {}
}

onMounted(() => {
  fetchProjects()
  fetchList()
})
</script>

<style scoped>
.announcements-page { max-width: 1600px; margin: 0 auto; }
.main-card { border-radius: 16px; border: 1px solid #f0f0f4; }
.main-card:deep(.el-card__body) { padding: 24px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
.page-title { font-size: 18px; font-weight: 700; color: #1d1d1f; }
.page-sub { font-size: 13px; color: #9ca3af; margin-top: 4px; }
.header-actions { display: flex; gap: 10px; align-items: center; }
.header-actions :deep(.el-button) { border-radius: 10px; font-weight: 500; }
.toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.toolbar :deep(.el-button) { border-radius: 10px; }
.data-table { border-radius: 12px; overflow: hidden; }
.data-table:deep(.el-table__header th) { background: #f8f9fb; font-weight: 600; color: #374151; font-size: 13px; border-color: #f0f0f4; }
.data-table:deep(.el-table__body td) { font-size: 13px; color: #374151; border-color: #f5f5f7; }
.title-text { color: #1d1d1f; font-weight: 500; }
.pagination-wrapper { margin-top: 16px; display: flex; justify-content: flex-end; }
@media (max-width: 768px) {
  .main-card:deep(.el-card__body) { padding: 14px; }
  .header-actions { width: 100%; flex-wrap: wrap; }
}
</style>
