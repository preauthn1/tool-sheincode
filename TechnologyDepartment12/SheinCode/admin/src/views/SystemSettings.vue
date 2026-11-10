<template>
  <section class="page">
    <div class="page-toolbar">
      <el-button link :icon="Refresh" :loading="loading" @click="loadSettings">刷新</el-button>
    </div>

    <div class="settings-shell">
      <aside class="settings-nav">
        <button
          v-for="item in sections"
          :key="item.key"
          :class="{ active: activeSection === item.key }"
          type="button"
          @click="activeSection = item.key"
        >
          {{ item.label }}
        </button>
      </aside>

      <main class="settings-panel">
        <div v-if="activeSection === 'site'" class="form-section">
          <h2><el-icon><Grid /></el-icon> 基本配置</h2>
          <el-form label-position="top">
            <el-form-item label="站点标题">
              <el-input v-model.trim="form.site_name" />
            </el-form-item>
            <el-form-item label="默认USDT地址">
              <el-input v-model.trim="form.default_usdt_address" />
            </el-form-item>
            <el-form-item label="客服在线时间">
              <el-input v-model.trim="form.customer_service_hours" />
            </el-form-item>
            <el-form-item label="时区设置">
              <el-input v-model.trim="form.timezone" />
            </el-form-item>
            <el-form-item label="客服链接">
              <el-input v-model.trim="form.customer_service_url" />
            </el-form-item>
            <el-form-item label="注册赠送金额">
              <el-input-number v-model="form.register_bonus" :min="0" :precision="2" />
            </el-form-item>
          </el-form>
        </div>

        <div v-else-if="activeSection === 'system'" class="form-section">
          <h2><el-icon><Setting /></el-icon> 系统配置</h2>
          <el-form label-position="top" class="two-cols">
            <el-form-item label="最低接单余额">
              <el-input-number v-model="form.task_min_balance" :min="0" :precision="2" />
            </el-form-item>
            <el-form-item label="普通商品下浮差额">
              <el-input-number v-model="form.normal_order_price_down_diff" :min="0" :precision="2" />
            </el-form-item>
            <el-form-item label="每日任务数量">
              <el-input-number v-model="form.daily_task_count" :min="0" :precision="0" />
            </el-form-item>
            <el-form-item label="默认佣金比例(%)">
              <el-input-number v-model="form.default_commission_rate" :min="0" :precision="2" />
            </el-form-item>
            <el-form-item label="邀请奖励比例(%)">
              <el-input-number v-model="form.invite_reward_rate" :min="0" :precision="2" />
            </el-form-item>
            <el-form-item label="最低提现金额">
              <el-input-number v-model="form.withdraw_min_amount" :min="0" :precision="2" />
            </el-form-item>
            <el-form-item label="最高提现金额">
              <el-input-number v-model="form.withdraw_max_amount" :min="0" :precision="2" />
            </el-form-item>
            <el-form-item label="维护模式">
              <el-switch v-model="form.maintenance_mode" :active-value="1" :inactive-value="0" />
            </el-form-item>
          </el-form>
        </div>

        <div v-else-if="activeSection === 'about'" class="form-section">
          <h2><el-icon><Document /></el-icon> 关于我们</h2>
          <el-input v-model="form.about_us" type="textarea" :rows="14" />
        </div>

        <div v-else-if="activeSection === 'registerAgreement'" class="form-section">
          <h2><el-icon><Tickets /></el-icon> 注册协议</h2>
          <el-input v-model="form.register_agreement" type="textarea" :rows="14" />
        </div>

        <div v-else-if="activeSection === 'terms'" class="form-section">
          <h2><el-icon><Tickets /></el-icon> 条款与协议</h2>
          <el-input v-model="form.terms_agreements" type="textarea" :rows="18" />
        </div>

        <div v-else class="form-section">
          <h2><el-icon><Bell /></el-icon> 弹窗内容</h2>
          <el-form label-position="top">
            <el-form-item label="启用弹窗">
              <el-switch v-model="form.popup_enabled" :active-value="1" :inactive-value="0" />
            </el-form-item>
            <el-form-item label="弹窗标题">
              <el-input v-model.trim="form.popup_title" />
            </el-form-item>
            <el-form-item label="弹窗内容">
              <el-input v-model="form.popup_content" type="textarea" :rows="10" />
            </el-form-item>
          </el-form>
        </div>

        <div class="form-actions">
          <el-button type="primary" :loading="saving" @click="submitSettings">保存配置</el-button>
        </div>
      </main>
    </div>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Bell, Document, Grid, Refresh, Setting, Tickets } from '@element-plus/icons-vue'
import { getSettings, saveSettings } from '@/api/user'

const loading = ref(false)
const saving = ref(false)
const activeSection = ref('site')

const sections = [
  { key: 'site', label: '站点配置' },
  { key: 'system', label: '系统配置' },
  { key: 'about', label: '关于我们' },
  { key: 'registerAgreement', label: '注册协议' },
  { key: 'terms', label: '条款与协议' },
  { key: 'popup', label: '弹窗内容' },
]

const form = reactive({
  site_name: 'TARGET',
  default_usdt_address: '',
  customer_service_hours: '9:00-21:00',
  timezone: 'UTC+0',
  customer_service_url: '',
  register_bonus: 10,
  task_min_balance: 50,
  normal_order_price_down_diff: 0,
  daily_task_count: 0,
  default_commission_rate: 1,
  withdraw_min_amount: 100,
  withdraw_max_amount: 100000,
  invite_reward_rate: 25,
  maintenance_mode: 0,
  about_us: '',
  register_agreement: '',
  terms_agreements: '',
  popup_enabled: 0,
  popup_title: '',
  popup_content: '',
})

const numberFields = [
  'register_bonus',
  'task_min_balance',
  'normal_order_price_down_diff',
  'daily_task_count',
  'default_commission_rate',
  'withdraw_min_amount',
  'withdraw_max_amount',
  'invite_reward_rate',
  'maintenance_mode',
  'popup_enabled',
]

const loadSettings = async () => {
  loading.value = true
  try {
    const res = await getSettings()
    if (res.data.code !== 200) throw new Error(res.data.msg)
    Object.entries(res.data.data || {}).forEach(([key, value]) => {
      if (!(key in form)) return
      form[key] = numberFields.includes(key) ? Number(value || 0) : value
    })
  } catch (error) {
    ElMessage.error(error.message || '加载配置失败')
  } finally {
    loading.value = false
  }
}

const submitSettings = async () => {
  saving.value = true
  try {
    const res = await saveSettings({ ...form })
    if (res.data.code !== 200) throw new Error(res.data.msg)
    ElMessage.success('保存成功')
  } catch (error) {
    ElMessage.error(error.message || '保存失败')
  } finally {
    saving.value = false
  }
}

onMounted(loadSettings)
</script>

<style scoped>
.page {
  padding: 0 22px 24px;
}

.page-toolbar {
  height: 76px;
  display: flex;
  align-items: center;
}

.settings-shell {
  display: grid;
  grid-template-columns: 300px minmax(0, 1fr);
  min-height: calc(100vh - 156px);
  background: #fff;
}

.settings-nav {
  padding-top: 4px;
  background: #f8fafc;
  border-right: 1px solid #e3e8ef;
}

.settings-nav button {
  width: 100%;
  height: 58px;
  display: block;
  padding: 0 24px;
  color: #6b7785;
  font-size: 18px;
  text-align: left;
  background: transparent;
  border: 0;
  cursor: pointer;
}

.settings-nav button.active {
  color: #1f2a37;
  background: #fff;
}

.settings-panel {
  min-width: 0;
  padding: 28px 56px 32px;
}

.form-section {
  max-width: 820px;
}

h2 {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 16px;
  color: #738296;
  font-size: 28px;
  font-weight: 400;
}

.form-section :deep(.el-form-item__label) {
  margin-bottom: 8px;
  color: #1f2a37;
  font-size: 18px;
  font-weight: 700;
}

.form-section :deep(.el-input__wrapper),
.form-section :deep(.el-input-number),
.form-section :deep(.el-textarea__inner) {
  width: 100%;
  border-radius: 0;
}

.form-section :deep(.el-input__wrapper) {
  min-height: 48px;
  box-shadow: 0 0 0 1px #d9e0e8 inset;
}

.form-section :deep(.el-input__inner) {
  color: #6b7785;
  font-size: 18px;
}

.two-cols {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 28px;
}

.form-actions {
  display: flex;
  justify-content: flex-start;
  margin-top: 22px;
}
</style>
