import request from './request'

export const getTodayTasks = () => request.get('/task/today')
export const checkCanStart = () => request.get('/task/check-start')
export const startTask = () => request.post('/task/start')
export const getOrders = (params) => request.get('/task/orders', { params })
export const submitOrder = (data) => request.post('/task/submit', data)
