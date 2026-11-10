import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getUserInfo } from '@/api/user'
import { useContactChatStore } from '@/store/contact-chat'

export const useUserStore = defineStore('user', () => {
  const userInfo = ref({
    id: null,
    user_id: null,
    phone: '',
    nickname: '',
    account_role: 'customer',
    agent_id: 0,
    paired_user_id: null,
    managed_by: null,
    my_invite_code: '',
    invite_code: '',
    balance: 0,
    frozen_balance: 0,
    task_commission: 0,
    team_commission: 0,
    total_commission: 0,
    total_withdrawn: 0,
    wallet_currency: 'BTC',
    wallet_address: '',
    wallet_note: '',
    consecutive_days: 0,
    daily_commission: 0,
  })

  const setToken = (token) => {
    localStorage.setItem('token', token)
  }

  const setUserInfo = (info) => {
    userInfo.value = { ...userInfo.value, ...info }
    const userId = info?.user_id || info?.userId || info?.uid || info?.id
    if (userId) {
      localStorage.setItem('user_id', String(userId))
    }
  }

  const fetchUserInfo = async () => {
    try {
      const res = await getUserInfo()
      if (res.data.code === 200) {
        setUserInfo(res.data.data)
      }
    } catch (error) {
      console.error('获取用户信息失败', error)
    }
  }

  const clearUserInfo = () => {
    const contactChatStore = useContactChatStore()
    contactChatStore.resetChatState()
    localStorage.removeItem('token')
    localStorage.removeItem('ws_token')
    localStorage.removeItem('user_id')
    userInfo.value = {
      id: null,
      user_id: null,
      phone: '',
      nickname: '',
      account_role: 'customer',
      agent_id: 0,
      paired_user_id: null,
      managed_by: null,
      my_invite_code: '',
      invite_code: '',
      balance: 0,
      frozen_balance: 0,
      task_commission: 0,
      team_commission: 0,
      total_commission: 0,
      total_withdrawn: 0,
      wallet_currency: 'BTC',
      wallet_address: '',
      wallet_note: '',
      consecutive_days: 0,
      daily_commission: 0,
    }
  }

  const showChat = ref(false)
  const toggleChat = (val) => {
    showChat.value = val !== undefined ? val : !showChat.value
  }

  return { userInfo, showChat, setToken, setUserInfo, fetchUserInfo, clearUserInfo, toggleChat }
})
