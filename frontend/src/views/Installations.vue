<template>
  <div class="installations-page">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-total">
          <div class="stat-icon"><el-icon :size="32"><Download /></el-icon></div>
          <div class="stat-info">
            <div class="stat-value">{{ stats?.total_installs ?? 0 }}</div>
            <div class="stat-label">总安装次数</div>
          </div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-active">
          <div class="stat-icon"><el-icon :size="32"><CircleCheck /></el-icon></div>
          <div class="stat-info">
            <div class="stat-value">{{ stats?.active ?? 0 }}</div>
            <div class="stat-label">存活安装</div>
          </div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-deleted">
          <div class="stat-icon"><el-icon :size="32"><CircleClose /></el-icon></div>
          <div class="stat-info">
            <div class="stat-value">{{ stats?.deleted ?? 0 }}</div>
            <div class="stat-label">已删除</div>
          </div>
        </div>
      </el-col>
      <el-col :xs="12" :sm="6" :md="6" :lg="6">
        <div class="stat-card stat-online">
          <div class="stat-icon"><el-icon :size="32"><Timer /></el-icon></div>
          <div class="stat-info">
            <div class="stat-value stat-value-sm">{{ formatBj(stats?.last_online_at) }}</div>
            <div class="stat-label">最后上线时间</div>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- 项目维度统计 -->
    <el-card shadow="never" class="main-card project-stats-card" v-if="stats?.projects?.length">
      <div class="section-title">项目安装统计（含已删除）</div>
      <el-table :data="stats.projects" stripe border class="data-table" :max-height="280">
        <el-table-column prop="project_name" label="项目" min-width="140" />
        <el-table-column prop="total_installs" label="安装次数" width="110" align="center" />
        <el-table-column prop="active" label="存活" width="90" align="center">
          <template #default="{ row }"><el-tag type="success" size="small">{{ row.active }}</el-tag></template>
        </el-table-column>
        <el-table-column prop="deleted" label="已删除" width="90" align="center">
          <template #default="{ row }"><el-tag type="danger" size="small">{{ row.deleted }}</el-tag></template>
        </el-table-column>
        <el-table-column prop="today_new" label="今日新增" width="100" align="center" />
                <el-table-column prop="last_online_at" label="最后上线" width="180">
          <template #default="{ row }">{{ formatBj(row.last_online_at) }}</template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-card shadow="never" class="main-card">
      <!-- 筛选 -->
      <div class="toolbar">
        <div class="toolbar-left">
          <el-select v-model="filters.project_id" placeholder="全部项目" clearable style="width: 160px" @change="handleFilterChange">
            <el-option v-for="p in projects" :key="p.id" :label="p.name" :value="p.id" />
          </el-select>
          <el-select v-model="filters.status" placeholder="全部状态" clearable style="width: 120px" @change="handleFilterChange">
            <el-option label="存活" value="active" />
            <el-option label="已删除" value="deleted" />
          </el-select>
          <el-input v-model="filters.keyword" placeholder="机器码/卡密/IP" clearable style="width: 180px" @clear="handleFilterChange" @keyup.enter="handleFilterChange" />
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 260px"
            @change="handleFilterChange"
          />
          <el-button type="primary" :icon="Search" @click="handleFilterChange">搜索</el-button>
          <el-button :icon="Refresh" @click="resetFilters">重置</el-button>
        </div>
        <div class="toolbar-right">
          <el-button type="success" :icon="Download" @click="handleExport">导出</el-button>
          <el-button type="danger" :icon="Delete" :disabled="!selectedRows.length" @click="handleBatchDelete">
            批量删除 ({{ selectedRows.length }})
          </el-button>
          <el-button :icon="Refresh" circle @click="refreshAll" />
        </div>
      </div>

      <!-- 表格 -->
      <el-table
        :data="list"
        v-loading="loading"
        stripe
        border
        highlight-current-row
        @selection-change="handleSelectionChange"
        row-key="id"
        :max-height="tableMaxHeight"
        class="data-table"
      >
        <el-table-column type="selection" width="50" align="center" fixed="left" />
        <el-table-column prop="id" label="ID" width="70" align="center" />
        <el-table-column prop="project_name" label="项目" width="130" show-overflow-tooltip>
          <template #default="{ row }">{{ row.project_name || row.project_id }}</template>
        </el-table-column>
        <el-table-column prop="machine_id" label="机器指纹" min-width="180" show-overflow-tooltip>
          <template #default="{ row }"><span class="mono-text">{{ row.machine_id }}</span></template>
        </el-table-column>
        <el-table-column prop="card_key" label="卡密" min-width="160" show-overflow-tooltip>
          <template #default="{ row }"><span class="mono-text">{{ row.card_key || '-' }}</span></template>
        </el-table-column>
        <el-table-column prop="ip" label="IP" width="130">
          <template #default="{ row }"><span class="mono-text">{{ row.ip || '-' }}</span></template>
        </el-table-column>
        <el-table-column prop="client_version" label="客户端版本" width="110" align="center">
          <template #default="{ row }">{{ row.client_version || '-' }}</template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
              {{ row.status === 'active' ? '存活' : '已删除' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="installed_at" label="安装时间" width="170">
          <template #default="{ row }">{{ formatBj(row.installed_at) }}</template>
        </el-table-column>
        <el-table-column prop="last_online_at" label="最后上线" width="170">
          <template #default="{ row }">{{ formatBj(row.last_online_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" link :icon="View" @click="showDetail(row)">详情</el-button>
            <el-button
              v-if="row.status === 'active' && canManage"
              size="small"
              type="danger"
              link
              @click="handleDelete(row)"
            >删除</el-button>
            <el-button
              v-if="row.status === 'deleted' && canManage"
              size="small"
              type="success"
              link
              @click="handleRestore(row)"
            >恢复</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="pageSize"
          :page-sizes="[10, 20, 50, 100, 200]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="fetchData"
        />
      </div>
    </el-card>

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailVisible" title="安装详情" width="560px" destroy-on-close>
      <el-descriptions :column="1" border v-if="detailRow">
        <el-descriptions-item label="ID">{{ detailRow.id }}</el-descriptions-item>
        <el-descriptions-item label="项目">{{ detailRow.project_name || detailRow.project_id }}</el-descriptions-item>
        <el-descriptions-item label="机器指纹"><span class="mono-text">{{ detailRow.machine_id }}</span></el-descriptions-item>
        <el-descriptions-item label="卡密"><span class="mono-text">{{ detailRow.card_key || '-' }}</span></el-descriptions-item>
        <el-descriptions-item label="IP">{{ detailRow.ip || '-' }}</el-descriptions-item>
        <el-descriptions-item label="设备信息">{{ detailRow.device_info || '-' }}</el-descriptions-item>
        <el-descriptions-item label="客户端版本">{{ detailRow.client_version || '-' }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="detailRow.status === 'active' ? 'success' : 'danger'" size="small">
            {{ detailRow.status === 'active' ? '存活' : '已删除' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="安装时间">{{ formatBj(detailRow.installed_at) }}</el-descriptions-item>
        <el-descriptions-item label="最后上线">{{ formatBj(detailRow.last_online_at) }}</el-descriptions-item>
        <el-descriptions-item label="删除时间">{{ formatBj(detailRow.deleted_at) }}</el-descriptions-item>
      </el-descriptions>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, Delete, Download, View, CircleCheck, CircleClose, Timer } from '@element-plus/icons-vue'
import request from '@/api'
import installationApi from '@/api/installations'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const user = computed(() => userStore.user)
const canManage = computed(() => ['admin', 'project_admin'].includes(user.value?.role))

const list = ref([])
const loading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const selectedRows = ref([])
const tableMaxHeight = ref(500)
const stats = ref(null)
const projects = ref([])
const dateRange = ref(null)

const filters = ref({
  project_id: route.query.project_id ? Number(route.query.project_id) : '',
  status: '',
  keyword: ''
})

const detailVisible = ref(false)
const detailRow = ref(null)

/** 统一展示为北京时间（后端已按 +08:00 返回；此处兜底） */
function formatBj(value) {
  if (!value || value === '0000-00-00 00:00:00') return '-'
  const raw = String(value).trim()
  // 已是无时区的北京时间字符串则直接展示
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(raw)) return raw
  const d = new Date(raw.includes('T') || raw.includes('+') || raw.includes('Z') ? raw : raw.replace(' ', 'T') + '+08:00')
  if (Number.isNaN(d.getTime())) return raw
  const pad = n => String(n).padStart(2, '0')
  const bj = new Date(d.getTime() + 8 * 3600 * 1000)
  return `${bj.getUTCFullYear()}-${pad(bj.getUTCMonth() + 1)}-${pad(bj.getUTCDate())} ${pad(bj.getUTCHours())}:${pad(bj.getUTCMinutes())}:${pad(bj.getUTCSeconds())}`
}

function buildParams() {
  const params = {
    page: page.value,
    page_size: pageSize.value,
    project_id: filters.value.project_id || '',
    status: filters.value.status || '',
    keyword: filters.value.keyword || ''
  }
  if (dateRange.value?.length === 2) {
    params.date_from = dateRange.value[0]
    params.date_to = dateRange.value[1]
  }
  Object.keys(params).forEach(k => {
    if (params[k] === '' || params[k] === null || params[k] === undefined) delete params[k]
  })
  return params
}

async function fetchList() {
  loading.value = true
  try {
    const res = await request.get('/installations', { params: buildParams() })
    list.value = res.data.list || []
    total.value = res.data.total || 0
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const params = {}
    if (filters.value.project_id) params.project_id = filters.value.project_id
    const res = await installationApi.stats(params)
    stats.value = res.data
  } catch (e) {
    console.error(e)
  }
}

async function fetchProjects() {
  try {
    const res = await request.get('/projects/all')
    projects.value = res.data.list || res.data || []
    if (!Array.isArray(projects.value)) projects.value = []
  } catch {
    projects.value = []
  }
}

function handleFilterChange() {
  page.value = 1
  fetchList()
  fetchStats()
}

function resetFilters() {
  filters.value = { project_id: '', status: '', keyword: '' }
  dateRange.value = null
  handleFilterChange()
}

function handleSelectionChange(rows) {
  selectedRows.value = rows
}

function handleSizeChange() {
  page.value = 1
  fetchList()
}

function showDetail(row) {
  detailRow.value = row
  detailVisible.value = true
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm('确定将该安装记录标记为已删除？', '提示', { type: 'warning' })
    await installationApi.remove(row.id)
    ElMessage.success('已删除')
    refreshAll()
  } catch {}
}

async function handleRestore(row) {
  try {
    await installationApi.restore(row.id)
    ElMessage.success('已恢复为存活')
    refreshAll()
  } catch {}
}

async function handleBatchDelete() {
  if (!selectedRows.value.length) return
  try {
    await ElMessageBox.confirm(`确定删除选中的 ${selectedRows.value.length} 条安装记录吗？`, '批量删除', { type: 'warning' })
    await installationApi.batchDelete(selectedRows.value.map(r => r.id))
    ElMessage.success('批量删除成功')
    selectedRows.value = []
    refreshAll()
  } catch {}
}

function handleExport() {
  const url = installationApi.exportUrl(buildParams())
  window.open(url, '_blank')
  ElMessage.success('导出任务已启动')
}

function refreshAll() {
  fetchList()
  fetchStats()
}

onMounted(() => {
  fetchProjects()
  refreshAll()
  const setTableMax = () => {
    tableMaxHeight.value = Math.max(400, window.innerHeight - 420)
  }
  setTableMax()
  window.addEventListener('resize', setTableMax)
})
</script>

<style scoped>
.installations-page { max-width: 1600px; margin: 0 auto; }
.stats-row { margin-bottom: 20px; }
.stat-card {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 18px; border-radius: 12px; background: #fff;
  border: 1px solid #f0f0f4; transition: all .25s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.05); }
.stat-icon {
  width: 40px; height: 40px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.stat-icon :deep(.el-icon) { font-size: 20px !important; }
.stat-total .stat-icon { background: #eff6ff; color: #3b82f6; }
.stat-active .stat-icon { background: #f0fdf4; color: #22c55e; }
.stat-deleted .stat-icon { background: #fef2f2; color: #ef4444; }
.stat-online .stat-icon { background: #fff7ed; color: #f97316; }
.stat-info { display: flex; flex-direction: column; min-width: 0; }
.stat-value { font-size: 22px; font-weight: 700; color: #1d1d1f; line-height: 1.1; letter-spacing: -.3px; }
.stat-value-sm { font-size: 13px; font-weight: 600; word-break: break-all; }
.stat-label { font-size: 12px; color: #9ca3af; margin-top: 2px; font-weight: 500; }
.main-card { margin-bottom: 20px; border-radius: 16px; border: 1px solid #f0f0f4; }
.main-card:deep(.el-card__body) { padding: 24px; }
.section-title { font-weight: 600; margin-bottom: 14px; color: #374151; }
.toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.toolbar-left, .toolbar-right { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.toolbar :deep(.el-button) { border-radius: 10px; font-weight: 500; }
.data-table { border-radius: 12px; overflow: hidden; }
.data-table:deep(.el-table__header th) { background: #f8f9fb; font-weight: 600; color: #374151; font-size: 13px; border-color: #f0f0f4; }
.data-table:deep(.el-table__body td) { font-size: 13px; color: #374151; border-color: #f5f5f7; }
.mono-text { font-family: 'SF Mono', Cascadia Code, Courier New, monospace; font-size: 12px; }
.pagination-wrapper { margin-top: 20px; display: flex; justify-content: flex-end; }
@media (max-width: 768px) {
  .toolbar { flex-direction: column; align-items: stretch; }
  .toolbar-left, .toolbar-right { width: 100%; }
  .main-card:deep(.el-card__body) { padding: 14px; }
}
</style>
