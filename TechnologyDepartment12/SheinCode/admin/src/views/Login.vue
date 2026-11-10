<template>
  <div class="login-page">
    <div class="login-box">
      <div class="logo">
        <span>TARGET</span>
        <small>后台管理系统</small>
      </div>

      <el-card class="login-card" shadow="never">
        <template #header>
          <div class="card-title">管理员登录</div>
        </template>

        <el-form :model="form" @submit.prevent>
          <el-form-item>
            <el-input v-model.trim="form.username" placeholder="账号" :prefix-icon="User" />
          </el-form-item>
          <el-form-item>
            <el-input
              v-model="form.password"
              type="password"
              placeholder="密码"
              :prefix-icon="Lock"
              show-password
              @keyup.enter="handleLogin"
            />
          </el-form-item>
          <el-button type="primary" :loading="loading" class="login-button" @click="handleLogin">登录</el-button>
        </el-form>
      </el-card>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Lock, User } from '@element-plus/icons-vue'
import { login } from '@/api/user'

const router = useRouter()
const route = useRoute()
const loading = ref(false)
const form = reactive({ username: '', password: '' })

const handleLogin = async () => {
  if (!form.username || !form.password) {
    ElMessage.warning('请填写账号密码')
    return
  }

  loading.value = true
  try {
    const res = await login(form)
    if (res.data.code === 200) {
      localStorage.setItem('admin_token', res.data.data.token)
      localStorage.setItem('admin_user', JSON.stringify(res.data.data.user || {}))
      ElMessage.success('登录成功')
      router.replace(route.query.redirect || '/')
    } else {
      ElMessage.error(res.data.msg || '登录失败')
    }
  } catch (error) {
    console.log(error.response?.data?.msg || error.response?.data?.error || error.message);
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 24px;
  background: #d2d6de;
}

.login-box {
  width: min(380px, 100%);
}

.logo {
  text-align: center;
  margin-bottom: 16px;
  color: #333;
}

.logo span {
  display: block;
  font-size: 34px;
  font-weight: 700;
  letter-spacing: 0;
}

.logo small {
  color: #667085;
}

.login-card {
  border-radius: 4px;
}

.card-title {
  text-align: center;
  font-weight: 700;
}

.login-button {
  width: 100%;
}
</style>
