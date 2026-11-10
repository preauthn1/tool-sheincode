const MAX_WIDTH = 540

const setRem = () => {
  const width = document.documentElement.clientWidth
  const finalWidth = Math.min(width, MAX_WIDTH)
  document.documentElement.style.fontSize = `${finalWidth / 10}px`
}

export const setupRem = () => {
  setRem()
  window.addEventListener('resize', setRem)
  window.addEventListener('orientationchange', setRem)
}
