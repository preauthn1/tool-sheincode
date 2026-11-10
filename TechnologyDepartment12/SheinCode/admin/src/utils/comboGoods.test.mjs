import assert from 'node:assert/strict'
import { buildComboGoods } from './comboGoods.mjs'

const goods = buildComboGoods(
  [
    { id: 10, name: '000', price: 1000 },
    { id: 3, name: 'jjj', price: 100 },
  ],
  1000,
  10,
)

assert.deepEqual(
  goods.map((item) => ({ id: item.id, quantity: item.quantity, total_price: item.total_price })),
  [{ id: 10, quantity: 1, total_price: 1000 }],
)

const fallbackGoods = buildComboGoods([{ id: 4, name: 'near', price: 995 }], 1000, 10)
assert.deepEqual(
  fallbackGoods.map((item) => ({ id: item.id, quantity: item.quantity, total_price: item.total_price })),
  [{ id: 4, quantity: 1, total_price: 995 }],
)

console.log('comboGoods tests passed')
