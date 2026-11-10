import assert from 'node:assert/strict'
import { resolveAssetUrlWithBase } from './assetUrl.mjs'

assert.equal(
  resolveAssetUrlWithBase('/uploads/goods/product.png', '/webapi'),
  '/uploads/goods/product.png',
)

assert.equal(
  resolveAssetUrlWithBase('/webapi/uploads/goods/product.png', '/webapi'),
  '/uploads/goods/product.png',
)

assert.equal(
  resolveAssetUrlWithBase('/api/admin/uploads/goods/product.png', '/webapi'),
  '/uploads/goods/product.png',
)

assert.equal(
  resolveAssetUrlWithBase('/system/settings', '/webapi'),
  '/webapi/system/settings',
)

assert.equal(
  resolveAssetUrlWithBase('https://example.com/product.png', '/webapi'),
  'https://example.com/product.png',
)

console.log('assetUrl tests passed')
