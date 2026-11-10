import request from './request'
import { resolveAbsoluteAssetUrl } from '@/utils/url'

const IM_API_BASE_URL = import.meta.env.VITE_IM_API_BASE_URL || ''
const CHAT_UPLOAD_BASE_URL = import.meta.env.VITE_CHAT_UPLOAD_API_BASE_URL || ''

const imConfig = (config = {}) => ({
  ...config,
  baseURL: IM_API_BASE_URL || config.baseURL,
  rawAuthorization: true,
  skipAuthRedirect: true,
})

const CHAT_LANG = 'en'

const withLangParams = (params = {}) => ({
  ...params,
  lang: params.lang || CHAT_LANG,
})

const withLangData = (data = {}) => ({
  ...data,
  lang: data.lang || CHAT_LANG,
})

export const syncChatUserProfile = (data) => request.post('/im/users', withLangData(data), imConfig())

export const putChatUserProfile = (data) => request.put('/im/users', withLangData(data), imConfig())

export const fetchChatConversationList = (params = {}) => (
  request.get('/im/conversations/myService', imConfig({ params: withLangParams(params) }))
)

export const joinChatConversation = (data) => request.post('/im/conversations/join', withLangData(data), imConfig())

export const createChatConversationGroup = (data) => (
  request.post('/im/conversations/cs/start', withLangData(data), imConfig())
)

export const fetchChatMessages = (params = {}) => (
  request.get('/im/conversations/messages', imConfig({ params: withLangParams(params) }))
)

export const markChatConversationRead = (data) => (
  request.post('/im/conversations/read', withLangData(data), imConfig())
)

export const agentChat = (data) => request.post('/im/agent/chat', withLangData(data), imConfig())

export const customerServiceChat = (data) => (
  request.post('/im/conversations/cs/sync-agents', withLangData(data), imConfig())
)

export const uploadSupportImage = (data) => (
  request.post('/support/upload', data, { headers: { 'Content-Type': 'multipart/form-data' } })
)

const pickUploadedFileUrl = (payload) => {
  const candidates = [
    payload?.url,
    payload?.fileUrl,
    payload?.imageUrl,
    payload?.src,
    payload?.path,
    payload?.fullUrl,
  ]

  for (const candidate of candidates) {
    if (typeof candidate === 'string' && candidate.trim()) return candidate.trim()
  }

  return ''
}

export const uploadChatImage = async ({ file, folder = 'img' } = {}) => {
  if (!file) {
    throw new Error('Image file is required')
  }

  const formData = new FormData()
  formData.append('file', file)
  formData.append('folder', folder)

  let response
  try {
   response = await uploadSupportImage(formData)
  } catch (error) {
    
  }

  const rawData = response?.data?.data || response?.data || response
  const fileUrl = resolveAbsoluteAssetUrl(
    pickUploadedFileUrl(rawData) ||
    pickUploadedFileUrl(rawData?.data) ||
    pickUploadedFileUrl(Array.isArray(rawData) ? rawData[0] : null) ||
    pickUploadedFileUrl(Array.isArray(rawData?.data) ? rawData.data[0] : null)
  )

  return {
    ...response,
    fileUrl,
  }
}

export const findChatConversationByType = (conversations = [], conversationType = '') => {
  const targetType = String(conversationType || '').toUpperCase()
  return conversations.find((item) => {
    const type = String(item?.conversationType || item?.type || '').toUpperCase()
    return type === targetType
  }) || null
}

const hasOwn = (target, key) => Object.prototype.hasOwnProperty.call(target || {}, key)

const unwrapResponseData = (response) => {
  const payload = response?.data ?? response
  if (payload && typeof payload === 'object' && hasOwn(payload, 'data')) {
    return payload.data
  }
  return payload
}

const normalizeConversationResult = (payload) => {
  if (!payload) return null
  if (Array.isArray(payload)) return payload[0] || null
  if (Array.isArray(payload.records)) return payload.records[0] || null
  if (Array.isArray(payload.list)) return payload.list[0] || null
  return payload
}

export const ensureChatConversationByType = async ({
  userId,
  conversationName,
  groupId,
  nickname = '',
  avatar = '',
  vipLevel = '',
  pageNum = 1,
  pageSize = 200,
} = {}) => {
  let conversationsResponse = await fetchChatConversationList({
    pageNum,
    pageSize,
    visitorUserId: String(userId),
  })
  let conversation = normalizeConversationResult(unwrapResponseData(conversationsResponse))

  if (conversation || !userId) return conversation

  const createdResponse = await createChatConversationGroup({
    visitorUserId: String(userId),
    visitorName: nickname,
    // visitorAvatar: avatar,
    visitorVipLevel: String(vipLevel || ''),
    ownerUserId: String(userId),
    groupId,
    conversationName,
    version:'nuwpurple',
    memberUserIds: [String(userId)],
  })
  const createdConversation = normalizeConversationResult(unwrapResponseData(createdResponse))
  if (createdConversation?.conversationId || createdConversation?.id) return createdConversation

  conversationsResponse = await fetchChatConversationList({
    pageNum,
    pageSize,
    visitorUserId: String(userId),
  })
  conversation = normalizeConversationResult(unwrapResponseData(conversationsResponse))

  if (conversation) return conversation

  return {
    conversationId: groupId,
    targetId: groupId,
    groupId,
    conversationName,
    conversationType: 'CUSTOMER_SERVICE',
  }
}

export const getSupportConversation = () => request.get('/support/conversation')
export const getSupportMessages = (params) => request.get('/support/messages', { params })
