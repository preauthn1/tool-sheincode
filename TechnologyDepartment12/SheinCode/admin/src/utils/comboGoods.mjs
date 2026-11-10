export function buildComboGoods(list, targetAmount, maxDiff) {
  const target = Number(targetAmount || 0)
  const allowedDiff = Number(maxDiff || 0)

  return list
    .map((item) => {
      const price = Number(item.price || 0)
      return {
        ...item,
        price,
        quantity: 1,
        total_price: Number(price.toFixed(2)),
      }
    })
    .filter((item) => {
      if (!target) return true
      return Math.abs(item.price - target) <= allowedDiff
    })
    .sort((a, b) => Math.abs(a.price - target) - Math.abs(b.price - target))
}
