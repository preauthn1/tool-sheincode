import request from './request'

export const getGoods = (params) => request.get('/goods', { params })
export const saveGood = (data) => request.post('/goods/save', data)
export const uploadGoodImage = (data) => request.post('/goods/upload', data, { headers: { 'Content-Type': 'multipart/form-data' } })
export const toggleGoodStatus = (id, status) => request.post('/goods/toggle-status', { id, status })
export const deleteGood = (id) => request.post('/goods/delete', { id })
