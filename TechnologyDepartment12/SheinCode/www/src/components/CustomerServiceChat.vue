<template>
  <div class="chat-wrapper" :class="{ 'is-visible': userStore.showChat }">
    <div class="chat-mask" @click="closeChat"></div>
    <div class="chat-container">
      <header class="chat-header">
        <div class="header-left">
          <div class="avatar-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </div>
          <div class="header-info">
            <h2>Online customer service</h2>
            <p>{{ connected ? 'Contact us' : 'Connecting...' }}</p>
          </div>
        </div>
        <button class="close-btn" @click="closeChat">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>
      </header>

      <div class="chat-body" ref="chatBody">
        <button v-if="hasMore" class="load-more" @click="loadOlder">Load earlier messages</button>
        <div class="message-list">
          <div v-for="msg in messages" :key="msg.id" class="message-item"
            :class="msg.sender_type === 'user' ? 'sent' : 'received'">
            <div v-if="msg.sender_type !== 'user'" class="msg-avatar">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
            </div>
            <div v-if="msg.message_type === 'image'" class="msg-image">
              <img :src="msg.content" alt="chat image" />
            </div>
            <div v-else class="msg-bubble">{{ msg.content }}</div>
          </div>
        </div>
      </div>

      <footer class="chat-footer">
        <div class="input-area">
          <input type="text" v-model="inputText" placeholder="Type a message..." @keyup.enter="sendText" />
        </div>
        <div v-if="showEmoji" class="emoji-panel">
          <button v-for="emoji in emojis" :key="emoji" @click="sendEmoji(emoji)">{{ emoji }}</button>
        </div>
        <div class="toolbar">
          <div class="toolbar-icons">
            <button class="tool-btn" @click="showEmoji = !showEmoji">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                <line x1="9" y1="9" x2="9.01" y2="9"></line>
                <line x1="15" y1="9" x2="15.01" y2="9"></line>
              </svg>
            </button>
            <button class="tool-btn" @click="imageInput?.click()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                <polyline points="21 15 16 10 5 21"></polyline>
              </svg>
            </button>
            <input ref="imageInput" class="file-input" type="file" accept="image/*" @change="sendImage" />
          </div>
          <button class="send-btn" :disabled="!connected" @click="sendText">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path>
            </svg>
          </button>
        </div>
        <div class="about-text">About this account</div>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  customerServiceChat,
  ensureChatConversationByType,
  fetchChatMessages,
  uploadChatImage,
} from '@/api/chat'
import { buildChatWsUrl, createChatHeartbeatPayload, createChatMessagePayload } from '@/services/chat-socket'
import { useUserStore } from '@/store/user'
import { resolveAbsoluteAssetUrl } from '@/utils/url'

const userStore = useUserStore()
const CHAT_REOPEN_KEY = 'customer_service_chat_open'
const inputText = ref('')
const messages = ref([])
const conversation = ref(null)
const chatBody = ref(null)
const imageInput = ref(null)
const connected = ref(false)
const hasMore = ref(false)
const page = ref(1)
const showEmoji = ref(false)
const emojis = ['😀', '😊', '👍', '🙏', '❤️', '🔥', '✅', '💰']
let socket = null
let heartbeatTimer = null
let connectRunId = 0

const currentUserId = computed(() => {
  const info = userStore.userInfo || {}
  return String(info.user_id || info.userId || info.uid || info.id || localStorage.getItem('user_id') || '')
})

const currentUserName = computed(() => {
  const info = userStore.userInfo || {}
  return info.nickname || info.phone || 'Customer'
})

const currentUserAvatar = computed(() => userStore.userInfo?.avatar || '')

const closeChat = () => {
  sessionStorage.removeItem(CHAT_REOPEN_KEY)
  userStore.toggleChat(false)
}

const wsUrl = () => {
  const token = localStorage.getItem('ws_token') || localStorage.getItem('token') || ''
  const wsBaseUrl = import.meta.env.VITE_WS_CHAT_BASE_URL || ''
  return buildChatWsUrl(token, wsBaseUrl)
}

const scrollToBottom = async () => {
  await nextTick()
  if (chatBody.value) chatBody.value.scrollTop = chatBody.value.scrollHeight
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

const parseSocketPayload = (rawPayload) => {
  if (typeof rawPayload !== 'string') return rawPayload
  const trimmed = rawPayload.trim()
  if (!trimmed) return null
  if (['ping', 'pong', 'ack', 'ok'].includes(trimmed.toLowerCase())) return null
  try {
    return JSON.parse(trimmed)
  } catch {
    return null
  }
}

const isControlMessage = (msg) => {
  if (!msg) return true
  const extras = getMessageExtras(msg)
  const content = String(msg.content ?? '').trim()
  const normalizedContent = content.toLowerCase()
  const eventType = String(extras.eventType || msg.eventType || '').toUpperCase()
  const messageKind = String(extras.messageKind || msg.messageKind || '').toLowerCase()
  const chatEventTypes = ['CHAT', 'CHAT_MESSAGE', 'MESSAGE']
  const chatMessageKinds = ['text', 'image', 'cs_message', 'message']
  const hasChatRoute = Boolean(
    msg.conversationId ||
    extras.conversationId ||
    msg.groupId ||
    extras.groupId
  )

  if (msg.cmd != null && Number(msg.cmd) !== 11) return true
  if (msg.cmd === 11 && Number(msg.chatType || 0) === 2 && messageKind === 'heartbeat') return true
  if (content === '[heartbeat]' || messageKind === 'heartbeat') return true
  if (eventType && !chatEventTypes.includes(eventType)) return true
  if (messageKind && !chatMessageKinds.includes(messageKind) && !chatMessageKinds.includes(normalizedContent)) return true
  if (['ping', 'pong', 'ack'].includes(normalizedContent) && !hasChatRoute) return true
  if (['pong', 'ack'].includes(normalizedContent)) return true

  return false
}

const normalizeChatMessage = (msg = {}) => {
  const extras = getMessageExtras(msg)
  const from = String(msg.from || msg.senderId || '')
  const msgType = Number(msg.msgType ?? msg.messageType ?? 0)
  const createTime = msg.createTime || msg.sendTime || Date.now()
  let rawContent = extras.translated || extras.originalContent || msg.content || extras.imageUrl || ''
  if (currentUserId.value == msg.senderId) {
    rawContent = extras.originalContent || extras.imageUrl || ''
    // rawContent = msg.content || extras.translated || extras.originalContent || extras.imageUrl || ''
  } else {
    rawContent = extras.translated || extras.originalContent || msg.content || extras.imageUrl || ''
  }
  const content = msgType === 1 ? resolveAbsoluteAssetUrl(rawContent) : rawContent
  return {
    id: extras.messageId || msg.messageId || msg.id || `${createTime}_${Math.random()}`,
    conversation_id: extras.conversationId || msg.conversationId || conversation.value?.conversationId || '',
    sender_type: (from == currentUserId.value) ? 'user' : 'admin',
    sender_id: from,
    message_type: msgType === 1 ? 'image' : 'text',
    content,
    created_at: createTime,
    extras,
  }
}

const matchesActiveConversation = (msg) => {
  const extras = getMessageExtras(msg)
  const conversationId = String(msg.conversationId || extras.conversationId || '')
  const groupId = String(msg.groupId || extras.groupId || '')
  const activeIds = [
    conversation.value?.conversationId,
    conversation.value?.groupId,
    conversation.value?.targetId,
    conversation.value?.id,
  ].filter(Boolean).map(String)
  return (
    activeIds.includes(conversationId) ||
    activeIds.includes(groupId)
  )
}

const stopHeartbeat = () => {
  if (heartbeatTimer) {
    window.clearInterval(heartbeatTimer)
    heartbeatTimer = null
  }
}

const destroySocket = () => {
  stopHeartbeat()
  if (socket) {
    socket.onopen = null
    socket.onclose = null
    socket.onmessage = null
    socket.onerror = null
    if (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING) {
      socket.close()
    }
    socket = null
  }
  connected.value = false
}

const resetChatSession = () => {
  connectRunId += 1
  destroySocket()
  messages.value = []
  conversation.value = null
  hasMore.value = false
  page.value = 1
  inputText.value = ''
  showEmoji.value = false
}

const sendHeartbeat = () => {
  if (!socket || socket.readyState !== WebSocket.OPEN || !currentUserId.value) return
  socket.send(JSON.stringify(createChatHeartbeatPayload({ from: currentUserId.value })))
}

const loadMessages = async (targetPage = 1) => {
  if (!conversation.value?.conversationId) return
  const res = await fetchChatMessages({ conversationId: conversation.value.conversationId, pageSize: 50 })
  const data = res?.data?.data || res?.data || {}
  const list = Array.isArray(data.records) ? data.records : (Array.isArray(data) ? data : [])
  messages.value = list.filter((item) => !isControlMessage(item)).map(normalizeChatMessage)
  hasMore.value = false
  page.value = targetPage
  if (targetPage === 1) scrollToBottom()
}

const loadOlder = () => loadMessages(page.value + 1)

const connect = async () => {
  const runId = connectRunId
  const token = localStorage.getItem('ws_token') || localStorage.getItem('token') || ''
  if (!token || !currentUserId.value) return

  const currentConversation = await ensureChatConversationByType({
    userId: currentUserId.value,
    groupId: `SERVICE_${currentUserId.value}`,
    conversationName: 'Customer Service',
    nickname: currentUserName.value,
    avatar: currentUserAvatar.value,
    vipLevel: userStore.userInfo?.vipLevel || userStore.userInfo?.level || '',
  })
  if (runId !== connectRunId || !userStore.showChat) return
  conversation.value = {
    ...currentConversation,
    conversationId: currentConversation?.conversationId || currentConversation?.id || '',
    groupId: currentConversation?.groupId || currentConversation?.conversationId || currentConversation?.id || '',
  }
  await loadMessages(1)
  if (runId !== connectRunId || !userStore.showChat) return

  const targetUrl = wsUrl()
  if (!targetUrl) return

  destroySocket()
  socket = new WebSocket(targetUrl)
  socket.onopen = () => {
    if (runId !== connectRunId || !userStore.showChat) {
      destroySocket()
      return
    }
    connected.value = true
    customerServiceChat({ conversationId: conversation.value?.conversationId }).catch(() => { })
    sendHeartbeat()
    heartbeatTimer = window.setInterval(sendHeartbeat, 25000)
  }
  socket.onclose = () => {
    if (runId !== connectRunId) return
    connected.value = false
    stopHeartbeat()
  }
  socket.onmessage = (event) => {
    if (runId !== connectRunId || !userStore.showChat) return
    const packet = parseSocketPayload(event.data)
    if (!packet) return
    if (packet?.cmd === 10) return
    const chatPayload = packet?.data && typeof packet.data === 'object' ? packet.data : packet
    if (isControlMessage(chatPayload)) return
    if (chatPayload?.content && matchesActiveConversation(chatPayload)) {
      messages.value.push(normalizeChatMessage(chatPayload))
      scrollToBottom()
    }
  }
}

const sendSocket = (messageType, content) => {
  if (!socket || socket.readyState !== WebSocket.OPEN || !content) return
  const isImage = messageType === 'image'
  const localId = `service_${isImage ? 'img' : 'text'}_${Date.now()}_${Math.random().toString(16).slice(2)}`
  const payload = createChatMessagePayload({
    from: currentUserId.value,
    to: '',
    groupId: conversation.value?.groupId || conversation.value?.conversationId || '',
    content,
    msgType: isImage ? 1 : 0,
    extras: {
      messageId: '',
      messageSeq: '0',
      eventType: 'CHAT',
      source: 'support-drawer',
      localId,
      conversationId: conversation.value?.conversationId || '',
      messageKind: isImage ? 'image' : 'text',
      imageUrl: isImage ? content : '',
      senderName: currentUserName.value,
    },
  })
  socket.send(JSON.stringify(payload))
  messages.value.push(normalizeChatMessage({
    ...payload,
    senderAvatar: currentUserAvatar.value,
  }))
  scrollToBottom()
}

const sendText = () => {
  const text = inputText.value.trim()
  if (!text) return
  sendSocket('text', text)
  inputText.value = ''
}

const sendEmoji = (emoji) => {
  inputText.value += emoji
  showEmoji.value = false
}

const sendImage = async (event) => {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file || !file.type.startsWith('image/')) return
  const res = await uploadChatImage({ file, folder: 'img' })
  if (res.fileUrl) sendSocket('image', res.fileUrl)
}

watch(() => userStore.showChat, (visible) => {
  resetChatSession()
  if (visible) {
    sessionStorage.setItem(CHAT_REOPEN_KEY, '1')
    connect()
  }
})

watch(currentUserId, (userId, oldUserId) => {
  if (userId === oldUserId) return
  resetChatSession()
  if (userStore.showChat && userId) connect()
})

onMounted(() => {
  window.addEventListener('pagehide', resetChatSession)
  window.addEventListener('beforeunload', resetChatSession)
  if (sessionStorage.getItem(CHAT_REOPEN_KEY) === '1' && localStorage.getItem('token')) {
    userStore.toggleChat(true)
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('pagehide', resetChatSession)
  window.removeEventListener('beforeunload', resetChatSession)
  resetChatSession()
})
</script>

<style scoped>
.chat-wrapper {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  pointer-events: none;
}

.chat-wrapper.is-visible {
  pointer-events: auto;
}

.chat-mask {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.2);
  opacity: 0;
  transition: opacity 0.3s;
}

.chat-wrapper.is-visible .chat-mask {
  opacity: 1;
}

.chat-container {
  position: relative;
  width: 100%;
  max-width: var(--app-max-width, 480px);
  height: 85vh;
  margin: 0 auto;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  border-radius: 12px 12px 0 0;
  background: #f8fafc;
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
  transform: translateY(100%);
  transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.chat-wrapper.is-visible .chat-container {
  transform: translateY(0);
}

.chat-header {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  color: #fff;
  background: #e60000;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.avatar-box,
.msg-avatar {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  border-radius: 50%;
}

.avatar-box {
  width: 44px;
  height: 44px;
  color: #000;
  background: #fff;
}

.avatar-box svg {
  width: 28px;
  height: 28px;
}

.header-info h2,
.header-info p {
  margin: 0;
}

.header-info h2 {
  font-size: 16px;
  font-weight: 700;
}

.header-info p {
  margin-top: 2px;
  font-size: 13px;
  opacity: 0.9;
}

.close-btn,
.tool-btn,
.send-btn,
.load-more,
.emoji-panel button {
  border: 0;
  cursor: pointer;
}

.close-btn {
  display: grid;
  place-items: center;
  padding: 8px;
  color: #fff;
  background: transparent;
}

.close-btn svg {
  width: 24px;
  height: 24px;
}

.chat-body {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}

.load-more {
  display: block;
  margin: 0 auto 12px;
  color: #667085;
  font-size: 12px;
  background: transparent;
}

.message-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.message-item {
  display: flex;
  gap: 10px;
  width: 100%;
}

.message-item.sent {
  flex-direction: row-reverse;
}

.msg-avatar {
  width: 36px;
  height: 36px;
  color: #fff;
  background: #a5caff;
}

.msg-avatar svg {
  width: 20px;
  height: 20px;
}

.msg-bubble {
  max-width: 80%;
  padding: 14px 16px;
  color: #333;
  font-size: 14px;
  line-height: 1.5;
  word-break: break-word;
  border-radius: 8px;
  background: #f0f7ff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.message-item.sent .msg-bubble {
  color: #fff;
  background: #e60000;
}

.msg-image {
  max-width: 70%;
  overflow: hidden;
  border-radius: 8px;
}

.msg-image img {
  display: block;
  width: 100%;
  height: auto;
}

.chat-footer {
  flex-shrink: 0;
  padding: 12px 16px;
  background: #fff;
  border-top: 1px solid #eee;
}

.input-area {
  margin-bottom: 12px;
}

.input-area input {
  width: 100%;
  padding: 4px 0;
  color: #333;
  font-size: 15px;
  border: 0;
  outline: 0;
  background: transparent;
}

.input-area input::placeholder {
  color: #999;
}

.emoji-panel {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 12px;
}

.emoji-panel button {
  width: 32px;
  height: 32px;
  font-size: 18px;
  border-radius: 6px;
  background: #f3f4f6;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.toolbar-icons {
  display: flex;
  align-items: center;
  gap: 16px;
}

.tool-btn {
  display: grid;
  place-items: center;
  padding: 0;
  color: #666;
  background: transparent;
}

.tool-btn svg {
  width: 22px;
  height: 22px;
}

.file-input {
  display: none;
}

.send-btn {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  padding: 0;
  color: #fff;
  border-radius: 50%;
  background: #e60000;
}

.send-btn:disabled {
  opacity: 0.45;
}

.send-btn svg {
  width: 18px;
  height: 18px;
}

.about-text {
  margin-top: 16px;
  padding-bottom: env(safe-area-inset-bottom, 8px);
  color: #999;
  font-size: 12px;
  text-align: center;
}
</style>
