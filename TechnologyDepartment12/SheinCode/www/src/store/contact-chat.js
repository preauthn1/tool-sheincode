import { defineStore } from 'pinia'
import { ref } from 'vue'
import ChatSocketClient, { createChatHeartbeatPayload } from '@/services/chat-socket.js'
import { resolveAbsoluteAssetUrl } from '@/utils/url'
import {
  customerServiceChat,
  ensureChatConversationByType,
  fetchChatMessages,
} from '@/api/chat.js'

const HEARTBEAT_INTERVAL = 25000
const HEARTBEAT_TIMEOUT = 0
const IDLE_DISCONNECT_MS = 5 * 60 * 1000

let chatSocketClient = null
let idleDisconnectTimer = null

const getToken = () => localStorage.getItem('ws_token') || localStorage.getItem('token') || ''

const parseSocketPayload = (rawPayload) => {
  try {
    return JSON.parse(rawPayload)
  } catch (error) {
    console.warn('service chat message parse failed:', error)
    return null
  }
}

const getMessageExtras = (msg) => {
  if (!msg?.extras) return {}
  if (typeof msg.extras === 'string') {
    try {
      return JSON.parse(msg.extras)
    } catch {
      return {}
    }
  }
  return msg.extras
}

const isHeartbeatMessage = (msg) => {
  const extras = getMessageExtras(msg)
  return msg?.content === '[heartbeat]' || extras.messageKind === 'heartbeat'
}

const parseCreateTime = (value) => {
  if (value == null || value === '') return Date.now()
  if (typeof value === 'number') return value
  const numeric = Number(value)
  if (!Number.isNaN(numeric)) return numeric
  const parsed = new Date(String(value).replace(' ', 'T')).getTime()
  return Number.isNaN(parsed) ? Date.now() : parsed
}

const unwrapResponseData = (response) => response?.data?.data ?? response?.data ?? response

export const useContactChatStore = defineStore('contact-chat', () => {
  const connected = ref(false)
  const connecting = ref(false)
  const activeConversationId = ref('')
  const activeGroupId = ref('')
  const conversationTitle = ref('')
  const messages = ref([])
  const currentSocketUserId = ref('')

  const normalizeMessage = (msg = {}) => {
    const extras = getMessageExtras(msg)
    const senderId = String(msg.from || msg.senderId || '')
    const messageId = extras.messageId || msg.messageId || msg.id || `${msg.createTime || Date.now()}_${Math.random()}`
    const createTime = parseCreateTime(msg.createTime ?? msg.sendTime ?? msg.created_at)
    const msgType = Number(msg.msgType ?? msg.messageType ?? (msg.message_type === 'image' ? 1 : 0))
    const rawContent = extras.translated || extras.originalContent || extras.imageUrl || ''
    const content = msgType === 1 ? resolveAbsoluteAssetUrl(rawContent) : rawContent

    return {
      id: messageId,
      messageId,
      messageSeq: Number(extras.messageSeq || msg.messageSeq || 0),
      conversationId: extras.conversationId || msg.conversationId || activeConversationId.value,
      sender: extras.senderName || msg.senderName || msg.senderNickname || extras.senderNickname || msg.sender || senderId || 'Customer Service',
      senderId,
      senderAvatar: extras.senderAvatar || msg.senderAvatar || '',
      from: senderId,
      cmd: msg.cmd,
      msgType,
      chatType: Number(msg.chatType ?? 1),
      groupId: msg.groupId || extras.groupId || activeGroupId.value,
      content,
      quoteMessageId: String(msg.quoteMessageId || extras.quoteMessageId || ''),
      extras,
      type: extras.eventType || msg.type || 'CHAT',
      createTime,
      pending: Boolean(msg.pending),
      sender_type: String(senderId) === currentSocketUserId.value ? 'user' : 'admin',
      message_type: msgType === 1 ? 'image' : 'text',
      created_at: new Date(createTime).toISOString(),
    }
  }

  const matchesActiveConversation = (msg) => {
    const extras = getMessageExtras(msg)
    const conversationId = msg.conversationId || extras.conversationId || ''
    const groupId = msg.groupId || extras.groupId || ''
    return (
      conversationId === activeConversationId.value ||
      groupId === activeGroupId.value ||
      (!conversationId && groupId && groupId === activeGroupId.value)
    )
  }

  const sortMessages = () => {
    messages.value.sort((a, b) => {
      if (a.messageSeq && b.messageSeq) return a.messageSeq - b.messageSeq
      return a.createTime - b.createTime
    })
  }

  const upsertMessage = (msg) => {
    const normalized = normalizeMessage(msg)
    const localId = normalized.extras?.localId
    const existingIndex = messages.value.findIndex((item) => {
      if (normalized.messageId && item.messageId === normalized.messageId) return true
      return localId && item.extras?.localId === localId
    })

    if (existingIndex > -1) {
      messages.value.splice(existingIndex, 1, {
        ...messages.value[existingIndex],
        ...normalized,
        pending: false,
      })
    } else {
      messages.value.push(normalized)
    }

    sortMessages()
  }

  const removeMessageByLocalId = (localId) => {
    if (!localId) return
    const index = messages.value.findIndex((item) => item.extras?.localId === localId)
    if (index > -1) messages.value.splice(index, 1)
  }

  const fetchHistory = async () => {
    if (!activeConversationId.value) return

    const response = await fetchChatMessages({
      conversationId: activeConversationId.value,
      pageSize: 50,
    })

    const data = unwrapResponseData(response)
    const history = Array.isArray(data?.records) ? data.records : (Array.isArray(data) ? data : [])
    messages.value = history.map((item) => normalizeMessage(item))
    sortMessages()
  }

  const handleSocketMessage = async (rawPayload) => {
    const payload = parseSocketPayload(rawPayload)
    if (!payload || payload?.cmd === 10) return

    const chatPayload = payload?.data && typeof payload.data === 'object' ? payload.data : payload
    if (isHeartbeatMessage(chatPayload) || !matchesActiveConversation(chatPayload)) return

    const extras = getMessageExtras(chatPayload)
    if (extras.eventType === 'MESSAGE_RECALLED' || extras.eventType === 'MESSAGE_UNRECALLED') {
      await fetchHistory()
      return
    }

    if (!chatPayload?.content) return
    upsertMessage(chatPayload)
  }

  const resolveConversationMeta = async ({
    userId,
    nickname = '',
    avatar = '',
    vipLevel = '',
    groupId = '',
    conversationName = 'Customer Service',
  } = {}) => {
    const conversation = await ensureChatConversationByType({
      userId,
      groupId: groupId || `SERVICE_${userId}`,
      conversationName,
      nickname,
      avatar,
      vipLevel,
    })

    activeConversationId.value = String(conversation?.conversationId || conversation?.id || '')
    activeGroupId.value = String(conversation?.groupId || conversation?.conversationId || conversation?.id || '')
    conversationTitle.value = conversation?.conversationName || conversation?.name || conversationName

    return conversation
  }

  const cancelScheduledDisconnect = () => {
    if (idleDisconnectTimer) {
      clearTimeout(idleDisconnectTimer)
      idleDisconnectTimer = null
    }
  }

  const destroySocket = () => {
    cancelScheduledDisconnect()
    if (chatSocketClient) {
      chatSocketClient.destroy()
      chatSocketClient = null
    }
    connected.value = false
    connecting.value = false
    currentSocketUserId.value = ''
  }

  const isSocketAlive = ({ userId } = {}) => {
    const normalizedUserId = String(userId || '')
    return Boolean(chatSocketClient?.isOpen() && currentSocketUserId.value === normalizedUserId)
  }

  const ensureSocketConnection = ({ userId } = {}) => {
    cancelScheduledDisconnect()

    const token = getToken()
    const normalizedUserId = String(userId || '')

    if (!token || !normalizedUserId) return false

    if (isSocketAlive({ userId: normalizedUserId })) {
      connected.value = true
      connecting.value = false
      return true
    }

    destroySocket()
    connecting.value = true
    currentSocketUserId.value = normalizedUserId

    chatSocketClient = new ChatSocketClient({
      token,
      heartbeatInterval: HEARTBEAT_INTERVAL,
      heartbeatTimeout: HEARTBEAT_TIMEOUT,
      createHeartbeatPayload: () => {
        if (!currentSocketUserId.value) return null
        return createChatHeartbeatPayload({ from: currentSocketUserId.value })
      },
      onOpen: () => {
        connected.value = true
        connecting.value = false
        if (activeConversationId.value) {
          customerServiceChat({ conversationId: activeConversationId.value }).catch(() => {})
        }
      },
      onReconnectSchedule: () => {
        connected.value = false
        connecting.value = true
      },
      onMessage: async (rawPayload) => {
        await handleSocketMessage(rawPayload)
      },
      onError: () => {
        connected.value = false
        connecting.value = false
      },
      onClose: () => {
        connected.value = false
        connecting.value = false
      },
    })

    try {
      chatSocketClient.connect()
    } catch (error) {
      destroySocket()
      throw error
    }

    return true
  }

  const scheduleDisconnect = () => {
    cancelScheduledDisconnect()
    idleDisconnectTimer = setTimeout(() => {
      idleDisconnectTimer = null
      destroySocket()
      messages.value = []
      activeConversationId.value = ''
      activeGroupId.value = ''
      conversationTitle.value = ''
    }, IDLE_DISCONNECT_MS)
  }

  const sendPayload = (payload) => {
    if (!chatSocketClient?.isOpen() || !connected.value) return false
    return chatSocketClient.send(payload)
  }

  const resetChatState = () => {
    destroySocket()
    messages.value = []
    activeConversationId.value = ''
    activeGroupId.value = ''
    conversationTitle.value = ''
  }

  return {
    connected,
    connecting,
    activeConversationId,
    activeGroupId,
    conversationTitle,
    messages,
    normalizeMessage,
    upsertMessage,
    removeMessageByLocalId,
    handleSocketMessage,
    resolveConversationMeta,
    fetchHistory,
    isSocketAlive,
    ensureSocketConnection,
    cancelScheduledDisconnect,
    scheduleDisconnect,
    destroySocket,
    sendPayload,
    resetChatState,
  }
})
