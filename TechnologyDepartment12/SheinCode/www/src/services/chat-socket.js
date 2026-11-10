const DEFAULT_HEARTBEAT_INTERVAL = 25000
const DEFAULT_HEARTBEAT_TIMEOUT = 15000
const DEFAULT_RECONNECT_INTERVAL = 3000
const DEFAULT_MAX_RECONNECT_INTERVAL = 30000

const WS_CHAT_BASE_URL = import.meta.env.VITE_WS_CHAT_BASE_URL || ''

const ensureWschatPath = (url) => {
  if (!url.pathname || url.pathname === '/') {
    url.pathname = '/wschat'
  }
  return url
}

export const resolveChatWsBaseUrl = (wsBaseUrl = WS_CHAT_BASE_URL) => {
  const configuredBase = String(wsBaseUrl || '').trim()
  const fallbackProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'

  if (!configuredBase) {
    return `${fallbackProtocol}//${window.location.host}/wschat`
  }

  if (configuredBase.startsWith('/')) {
    const url = new URL(configuredBase, `${fallbackProtocol}//${window.location.host}`)
    return ensureWschatPath(url).toString().replace(/\/$/, '')
  }

  const url = new URL(configuredBase)
  if (url.protocol === 'http:') url.protocol = 'ws:'
  if (url.protocol === 'https:') url.protocol = 'wss:'
  return ensureWschatPath(url).toString().replace(/\/$/, '')
}

export const buildChatWsUrl = (token, wsBaseUrl = '') => {
  const baseUrl = resolveChatWsBaseUrl(wsBaseUrl || WS_CHAT_BASE_URL)
  if (!token || !baseUrl) return ''
  const separator = baseUrl.includes('?') ? '&' : '?'
  return `${baseUrl}${separator}token=${encodeURIComponent(token)}`
}

export const createChatMessagePayload = ({
  from,
  to = '',
  content = '',
  msgType = 0,
  chatType = 1,
  groupId = '',
  cmd = 11,
  extras = {},
  createTime = Date.now(),
}) => ({
  from,
  to,
  cmd,
  createTime,
  msgType,
  chatType,
  groupId,
  content,
  extras: { ...extras },
})

export const createChatHeartbeatPayload = ({ from, createTime = Date.now(), extras = {} }) => ({
  from,
  to: '',
  cmd: 11,
  createTime,
  msgType: 0,
  chatType: 2,
  content: '[heartbeat]',
  extras: {
    messageKind: 'heartbeat',
    ...extras,
  },
})

export class ChatSocketClient {
  constructor({
    token,
    wsBaseUrl,
    heartbeatInterval = DEFAULT_HEARTBEAT_INTERVAL,
    heartbeatTimeout = DEFAULT_HEARTBEAT_TIMEOUT,
    reconnectInterval = DEFAULT_RECONNECT_INTERVAL,
    maxReconnectInterval = DEFAULT_MAX_RECONNECT_INTERVAL,
    autoReconnect = true,
    createHeartbeatPayload,
    onOpen,
    onMessage,
    onError,
    onClose,
    onReconnect,
    onReconnectSchedule,
  }) {
    this.token = token
    this.wsBaseUrl = wsBaseUrl
    this.heartbeatInterval = heartbeatInterval
    this.heartbeatTimeout = heartbeatTimeout
    this.reconnectInterval = reconnectInterval
    this.maxReconnectInterval = maxReconnectInterval
    this.autoReconnect = autoReconnect
    this.createHeartbeatPayload = createHeartbeatPayload
    this.onOpen = onOpen
    this.onMessage = onMessage
    this.onError = onError
    this.onClose = onClose
    this.onReconnect = onReconnect
    this.onReconnectSchedule = onReconnectSchedule
    this.socket = null
    this.heartbeatTimer = null
    this.heartbeatTimeoutTimer = null
    this.reconnectTimer = null
    this.reconnectAttempts = 0
    this.manuallyDisconnected = false
    this.lastActivityAt = 0
    this.lastHeartbeatAt = 0
    this.openedAt = 0
    this.lifecycleBound = false
    this.handleVisibilityChange = this.handleVisibilityChange.bind(this)
    this.handleOnline = this.handleOnline.bind(this)
    this.handleOffline = this.handleOffline.bind(this)
  }

  isOpen() {
    return this.socket?.readyState === WebSocket.OPEN
  }

  isConnecting() {
    return this.socket?.readyState === WebSocket.CONNECTING
  }

  bindLifecycleListeners() {
    if (this.lifecycleBound || typeof window === 'undefined') return
    window.addEventListener('online', this.handleOnline)
    window.addEventListener('offline', this.handleOffline)
    document.addEventListener('visibilitychange', this.handleVisibilityChange)
    this.lifecycleBound = true
  }

  unbindLifecycleListeners() {
    if (!this.lifecycleBound || typeof window === 'undefined') return
    window.removeEventListener('online', this.handleOnline)
    window.removeEventListener('offline', this.handleOffline)
    document.removeEventListener('visibilitychange', this.handleVisibilityChange)
    this.lifecycleBound = false
  }

  handleOnline() {
    if (this.manuallyDisconnected || this.isOpen() || this.isConnecting()) return
    this.scheduleReconnect(true)
  }

  handleOffline() {
    this.stopHeartbeatTimeout()
  }

  handleVisibilityChange() {
    if (document.visibilityState !== 'visible') return
    if (this.manuallyDisconnected || this.isOpen() || this.isConnecting()) return
    this.scheduleReconnect(true)
  }

  markActivity() {
    this.lastActivityAt = Date.now()
    this.stopHeartbeatTimeout()
  }

  connect() {
    const wsUrl = buildChatWsUrl(this.token, this.wsBaseUrl)
    if (!wsUrl) {
      throw new Error('Missing chat websocket url or token')
    }

    this.manuallyDisconnected = false
    this.clearReconnectTimer()
    this.bindLifecycleListeners()
    this.disconnect()
    this.socket = new WebSocket(wsUrl)

    this.socket.onopen = () => {
      this.openedAt = Date.now()
      this.reconnectAttempts = 0
      this.markActivity()
      this.startHeartbeat()
      this.sendHeartbeat()
      this.onOpen?.()
      this.onReconnect?.()
    }

    this.socket.onmessage = (event) => {
      this.markActivity()
      this.onMessage?.(event.data, event)
    }

    this.socket.onerror = (event) => {
      this.onError?.(event)
    }

    this.socket.onclose = (event) => {
      this.stopHeartbeat()
      this.stopHeartbeatTimeout()
      this.onClose?.(event)
      this.scheduleReconnect()
    }
  }

  send(payload) {
    if (!this.isOpen()) return false
    const rawPayload = typeof payload === 'string' ? payload : JSON.stringify(payload)
    this.socket.send(rawPayload)
    return true
  }

  sendHeartbeat() {
    if (!this.isOpen() || typeof this.createHeartbeatPayload !== 'function') return false
    const heartbeatPayload = this.createHeartbeatPayload()
    if (!heartbeatPayload) return false
    this.lastHeartbeatAt = Date.now()
    const sent = this.send(heartbeatPayload)
    if (sent) this.startHeartbeatTimeout(this.lastHeartbeatAt)
    return sent
  }

  startHeartbeat() {
    this.stopHeartbeat()
    if (!this.heartbeatInterval) return
    this.heartbeatTimer = setInterval(() => {
      this.sendHeartbeat()
    }, this.heartbeatInterval)
  }

  stopHeartbeat() {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer)
      this.heartbeatTimer = null
    }
  }

  startHeartbeatTimeout(heartbeatAt) {
    this.stopHeartbeatTimeout()
    if (!this.heartbeatTimeout) return
    this.heartbeatTimeoutTimer = setTimeout(() => {
      if (!this.isOpen()) return
      if (this.lastActivityAt > heartbeatAt) return
      this.forceReconnect()
    }, this.heartbeatTimeout)
  }

  stopHeartbeatTimeout() {
    if (this.heartbeatTimeoutTimer) {
      clearTimeout(this.heartbeatTimeoutTimer)
      this.heartbeatTimeoutTimer = null
    }
  }

  clearReconnectTimer() {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer)
      this.reconnectTimer = null
    }
  }

  scheduleReconnect(immediate = false) {
    if (
      !this.autoReconnect ||
      this.manuallyDisconnected ||
      this.reconnectTimer ||
      this.isOpen() ||
      this.isConnecting()
    ) {
      return
    }

    const delay = immediate
      ? 0
      : Math.min(
        this.reconnectInterval * Math.pow(2, Math.max(this.reconnectAttempts, 0)),
        this.maxReconnectInterval
      )

    this.onReconnectSchedule?.({
      attempt: this.reconnectAttempts + 1,
      delay,
    })

    this.reconnectTimer = setTimeout(() => {
      this.reconnectTimer = null
      this.reconnectAttempts += 1
      try {
        this.connect()
      } catch (error) {
        this.onError?.(error)
        this.scheduleReconnect()
      }
    }, delay)
  }

  forceReconnect() {
    if (this.manuallyDisconnected) return
    this.disconnect()
    this.scheduleReconnect(true)
  }

  disconnect(shouldCloseSocket = true) {
    this.stopHeartbeat()
    this.stopHeartbeatTimeout()

    if (!this.socket) return

    this.socket.onopen = null
    this.socket.onmessage = null
    this.socket.onerror = null
    this.socket.onclose = null

    if (
      shouldCloseSocket &&
      (this.socket.readyState === WebSocket.OPEN || this.socket.readyState === WebSocket.CONNECTING)
    ) {
      this.socket.close()
    }

    this.socket = null
  }

  destroy() {
    this.manuallyDisconnected = true
    this.clearReconnectTimer()
    this.unbindLifecycleListeners()
    this.disconnect()
    this.reconnectAttempts = 0
  }
}

export default ChatSocketClient
