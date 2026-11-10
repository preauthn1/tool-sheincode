<template>
  <div class="mobile-page home-page">
    <NavBar title="Partner Hub" :showBack="false">
      <template #right>
        <button class="message-button" @click="router.push('/support')">MSG</button>
      </template>
    </NavBar>

    <section class="banner" @touchstart.passive="startBannerDrag" @touchmove.passive="moveBannerDrag"
      @touchend="endBannerDrag" @mousedown="startBannerDrag" @mousemove="moveBannerDrag" @mouseup="endBannerDrag"
      @mouseleave="endBannerDrag">
      <div ref="bannerTrack" class="banner-track" :class="{ dragging: bannerDragging, 'no-transition': !bannerTransition }"
        :style="bannerTrackStyle" @transitionend="onBannerTransitionEnd">
        <div v-for="(item, index) in displayBanners" :key="`${item.title}-${index}`" class="banner-item">
          <img :src="item.image" :alt="item.title" />
          <div>
            <span>{{ item.kicker }}</span>
            <h1>{{ item.title }}</h1>
          </div>
        </div>
      </div>
      <div class="banner-dots">
        <button v-for="(_, index) in banners" :key="index" :class="{ active: activeBanner === index }"
          @click="setActiveBanner(index)"></button>
      </div>
    </section>

    <section class="daily-section">
      <div class="module-title">
        <div>
          <h2>Daily tasks</h2>
          <p>Urgent orders have higher priority</p>
        </div>
        <span>{{ completedCount }}/{{ totalCount }}</span>
      </div>

      <div class="product-marquee">
        <div class="product-track">
          <article v-for="item in marqueeProducts" :key="item.key" class="product-tile">
            <img :src="item.image" :alt="item.name" />
            <h3>{{ item.name }}</h3>
            <p>${{ item.price }}</p>
          </article>
        </div>
      </div>

      <button class="start-button" @click="goTasks">
        <!-- <span class="point">◆</span> -->
        Start orders
      </button>
    </section>

    <section class="notice-bar">
      <span class="speaker">!</span>
      <div>
        <p>{{ currentMessage }}</p>
      </div>
    </section>

    <section class="wallet-panel panel">
      <div>
        <span>Account balance</span>
        <strong>${{ formatMoney(userInfo.balance) }}</strong>
      </div>
      <div>
        <span>Today income</span>
        <strong>${{ formatMoney(userInfo.daily_commission) }}</strong>
      </div>
      <div>
        <span>Invitation code</span>
        <strong>{{ userInfo.my_invite_code || '-' }}</strong>
      </div>
    </section>

    <section class="partner-section">
      <div class="module-title">
        <div>
          <h2>Partnership</h2>
          <p>Brand Partner Friendly</p>
        </div>
      </div>
      <div class="brand-grid">
        <a
          v-for="brand in brands"
          :key="brand.id || brand.name"
          class="brand-cell"
          :href="brand.url ? normalizePartnerUrl(brand.url) : undefined"
          :target="brand.url ? '_blank' : undefined"
          :rel="brand.url ? 'noopener noreferrer' : undefined"
        >
          <img v-if="brand.logo" :src="resolvePartnerImageUrl(brand.logo)" :alt="brand.name" />
          <span v-else>{{ brand.name }}</span>
        </a>
      </div>
    </section>

    <section class="service-section">
      <div class="module-title">
        <div>
          <h2>Service center</h2>
          <p>If you have questions, contact support</p>
        </div>
      </div>

      <button class="service-row" @click="inviteOpen = true">
        <span>Invite friends</span>
        <b>›</b>
      </button>
      <button class="service-row" @click="router.push('/support?topic=faq')">
        <span>FAQ</span>
        <b>›</b>
      </button>
      <button class="service-row" @click="router.push('/terms')">
        <span>Terms agreements</span>
        <b>›</b>
      </button>
      <button class="service-row" @click="router.push('/about')">
        <span>About the partner</span>
        <b>›</b>
      </button>
    </section>

    <div v-if="inviteOpen" class="modal-mask" @click.self="inviteOpen = false">
      <section class="invite-modal">
        <button class="invite-close" @click="inviteOpen = false">×</button>
        <h2>Welcome to Costco</h2>
        <p>Invite friends to get<br />commission</p>
        <img class="invite-image" src="../assets/invite.png" alt="" />
        <strong>Promotion code:&nbsp;&nbsp;{{ userInfo.my_invite_code || '-' }}</strong>
        <button class="invite-copy" @click="copyInviteCode">Copy invitation link</button>
      </section>
    </div>

    <div v-if="showNotice || showPayment" class="modal-mask" @click.self="closeInfoModals">
      <section class="info-modal">
        <header>
          <h2>{{ modalTitle }}</h2>
        </header>
        <p>{{ modalMessage }}</p>
        <button class="start-button" @click="closeInfoModals">close</button>
      </section>
    </div>

    <TabBar />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import NavBar from '@/components/NavBar.vue'
import TabBar from '@/components/TabBar.vue'
import { useUserStore } from '@/store/user'
import { checkCanStart, getTodayTasks } from '@/api/task'
import { getSystemPartners, getSystemSettings } from '@/api/user'
import { resolveAssetUrl } from '@/utils/url'

const router = useRouter()
const userStore = useUserStore()
const userInfo = computed(() => userStore.userInfo)
const completedCount = ref(0)
const totalCount = ref(0)
const activeBanner = ref(0)
const visualBannerIndex = ref(1)
const bannerTrack = ref(null)
const bannerTransition = ref(true)
const bannerDragging = ref(false)
const bannerStartX = ref(0)
const bannerDeltaX = ref(0)
const messageIndex = ref(0)
const inviteOpen = ref(false)
const showNotice = ref(false)
const showPayment = ref(false)
const partnerSites = ref([])
const systemSettings = ref({
  site_name: 'Partner Hub',
  customer_service_url: '',
  customer_service_hours: '',
  about_us: '',
  register_agreement: '',
  terms_agreements: '',
  popup_enabled: 0,
  popup_title: '',
  popup_content: '',
})
let bannerTimer
let messageTimer

const banners = [
  {
    kicker: 'New member activities',
    title: 'Daily order optimization',
    image: 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=900&q=80',
  },
  {
    kicker: 'Partner program',
    title: 'Complete tasks and settle income',
    image: 'https://images.unsplash.com/photo-1556742502-ec7c0e9f34b1?auto=format&fit=crop&w=900&q=80',
  },
  {
    kicker: 'Premium products',
    title: 'Matched orders from global brands',
    image: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=80',
  },
]

const products = [
  {
    name: 'Premium Leather Shoulder Bag',
    price: '825.00',
    image: 'https://images.unsplash.com/photo-1591561954557-26941169b49e?auto=format&fit=crop&w=500&q=80',
  },
  {
    name: 'Diamond Dial Stainless Watch',
    price: '2724.00',
    image: 'https://images.unsplash.com/photo-1524592094714-0f0654e20314?auto=format&fit=crop&w=500&q=80',
  },
  {
    name: 'Gold Chain Necklace',
    price: '997.00',
    image: 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?auto=format&fit=crop&w=500&q=80',
  },
  {
    name: 'Luxury Mini Satchel Bag',
    price: '825.60',
    image: 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=500&q=80',
  },
  {
    name: 'Pure Gold Radiance Eye Cream',
    price: '882.00',
    image: 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=500&q=80',
  },
]

const marqueeProducts = computed(() => [...products, ...products].map((item, index) => ({ ...item, key: `${item.name}-${index}` })))

const fallbackBrands = ['AURUM', 'GV2', 'MARC', 'NOVA', 'PRIME', 'LOUIS', 'FENDI', 'GARMIN', 'MERLIN']
  .map((name) => ({ name, url: '', logo: '' }))
const messages = [
  'congratulations! user****314 has been upgraded to VIP3',
  'congratulations! user****552 has completed daily orders',
  'congratulations! user****784 has been upgraded to VIP2',
  'congratulations! user****454 received task income',
]

const noticeMessages = computed(() => {
  const content = systemSettings.value.popup_content?.trim()
  if (!content) return messages
  return content.split(/\r?\n/).map((item) => item.trim()).filter(Boolean)
})
const currentMessage = computed(() => noticeMessages.value[messageIndex.value % noticeMessages.value.length])
const brands = computed(() => partnerSites.value.length ? partnerSites.value : fallbackBrands)
const displayBanners = computed(() => {
  if (banners.length <= 1) return banners
  return [banners[banners.length - 1], ...banners, banners[0]]
})
const bannerTrackStyle = computed(() => ({
  transform: `translate3d(calc(-${visualBannerIndex.value * 100}% + ${bannerDeltaX.value}px), 0, 0)`,
}))
const formatMoney = (value) => Number(value || 0).toFixed(2)
const modalTitle = computed(() => {
  if (showPayment.value) return 'Manual payment'
  return systemSettings.value.popup_title || 'Notice'
})
const modalMessage = computed(() => {
  if (showPayment.value) return 'This test system does not use automatic payment channels. Deposit instructions and withdrawal payments are handled manually by customer service.'
  return systemSettings.value.popup_content || 'Welcome. If you encounter account, task or balance issues, please contact customer service.'
})

const noticeClosedKey = () => {
  const token = localStorage.getItem('token') || 'guest'
  return `home_notice_closed_${token}`
}

const hasClosedNotice = () => sessionStorage.getItem(noticeClosedKey()) === '1'

const normalizePartnerUrl = (url) => {
  if (/^https?:\/\//i.test(url)) return url
  return `https://${url}`
}

const resolvePartnerImageUrl = (url) => {
  return resolveAssetUrl(url)
}

const loadSystemSettings = async () => {
  try {
    const res = await getSystemSettings()
    if (res.data.code !== 200) return
    Object.assign(systemSettings.value, res.data.data || {})
    if (Number(systemSettings.value.popup_enabled) === 1 && systemSettings.value.popup_content && !hasClosedNotice()) {
      showNotice.value = true
    }
  } catch (error) {
    // Keep the static fallback content if the public settings endpoint is unavailable.
  }
}

const loadPartners = async () => {
  try {
    const res = await getSystemPartners()
    if (res.data.code !== 200) return
    partnerSites.value = (res.data.data?.list || []).filter((item) => item.name)
  } catch (error) {
    partnerSites.value = []
  }
}

const loadTasks = async () => {
  try {
    const res = await getTodayTasks()
    if (res.data.code === 200) {
      completedCount.value = res.data.data.completed_count || 0
      totalCount.value = Number(res.data.data.total_count || 0)
    }
  } catch (error) {
    completedCount.value = 0
    totalCount.value = 0
  }
}

const goTasks = async () => {
  try {
    const res = await checkCanStart()
    if (res.data.code === 200) {
      router.push('/tasks')
    } else {
      ElMessage.warning(res.data.msg || 'Account balance must reach the configured minimum to start tasks')
    }
  } catch (error) {
    ElMessage.error('Please login first')
  }
}

const copyInviteCode = async () => {
  const code = userInfo.value.my_invite_code
  if (!code) {
    ElMessage.warning('No invitation code')
    return
  }
  await navigator.clipboard.writeText(code)
  ElMessage.success('Invitation code copied')
}

const closeInfoModals = () => {
  if (showNotice.value) {
    sessionStorage.setItem(noticeClosedKey(), '1')
  }
  showNotice.value = false
  showPayment.value = false
}

const getClientX = (event) => event.touches?.[0]?.clientX ?? event.clientX ?? 0

const resetBannerTimer = () => {
  window.clearInterval(bannerTimer)
  bannerTimer = window.setInterval(() => {
    goToBanner(activeBanner.value + 1)
  }, 4000)
}

const setActiveBanner = (index) => {
  bannerTransition.value = true
  activeBanner.value = index
  visualBannerIndex.value = index + 1
  resetBannerTimer()
}

const goToBanner = (index) => {
  bannerTransition.value = true
  bannerDeltaX.value = 0
  if (index >= banners.length) {
    activeBanner.value = 0
    visualBannerIndex.value = banners.length + 1
  } else if (index < 0) {
    activeBanner.value = banners.length - 1
    visualBannerIndex.value = 0
  } else {
    activeBanner.value = index
    visualBannerIndex.value = index + 1
  }
}

const onBannerTransitionEnd = async (event) => {
  if (event.target !== event.currentTarget) return

  if (visualBannerIndex.value === banners.length + 1) {
    bannerTransition.value = false
    visualBannerIndex.value = 1
  } else if (visualBannerIndex.value === 0) {
    bannerTransition.value = false
    visualBannerIndex.value = banners.length
  } else {
    return
  }

  await nextTick()
  void bannerTrack.value?.offsetHeight
  window.requestAnimationFrame(() => {
    window.requestAnimationFrame(() => {
      bannerTransition.value = true
    })
  })
}

const startBannerDrag = (event) => {
  bannerDragging.value = true
  bannerStartX.value = getClientX(event)
  bannerDeltaX.value = 0
  window.clearInterval(bannerTimer)
}

const moveBannerDrag = (event) => {
  if (!bannerDragging.value) return
  bannerDeltaX.value = getClientX(event) - bannerStartX.value
}

const endBannerDrag = () => {
  if (!bannerDragging.value) return
  const threshold = 45
  if (bannerDeltaX.value <= -threshold) {
    goToBanner(activeBanner.value + 1)
  } else if (bannerDeltaX.value >= threshold) {
    goToBanner(activeBanner.value - 1)
  }
  bannerDragging.value = false
  bannerDeltaX.value = 0
  resetBannerTimer()
}

onMounted(async () => {
  await loadSystemSettings()
  await loadPartners()
  await userStore.fetchUserInfo()
  await loadTasks()
  resetBannerTimer()
  messageTimer = window.setInterval(() => {
    messageIndex.value += 1
  }, 5000)
})

onBeforeUnmount(() => {
  window.clearInterval(bannerTimer)
  window.clearInterval(messageTimer)
})
</script>

<style scoped>
.home-page {
  min-height: 100vh;
  padding-bottom: 88px;
  background: #f4f5f7;
}

.message-button {
  border: 1px solid rgba(255, 255, 255, 0.44);
  border-radius: 999px;
  padding: 7px 10px;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  font-size: 12px;
  font-weight: 900;
}

.banner {
  position: relative;
  overflow: hidden;
  padding: 12px 12px 22px;
  /* background: #ff4b00; */
}

.banner-track {
  display: flex;
  transition: transform 0.35s ease;
  cursor: grab;
  user-select: none;
  will-change: transform;
}

.banner-track.dragging {
  transition: none;
  cursor: grabbing;
}

.banner-track.no-transition {
  transition: none;
}

.banner-item {
  position: relative;
  box-sizing: border-box;
  flex: 0 0 100%;
  height: 190px;
  padding: 0 6px;
  overflow: hidden;
  background: transparent;
}

.banner-item img {
  width: 100%;
  height: 100%;
  border-radius: 8px;
  object-fit: cover;
  opacity: 0.76;
  pointer-events: none;
  user-select: none;
}

.banner-item div {
  position: absolute;
  left: 18px;
  right: 18px;
  bottom: 16px;
  color: #fff;
}

.banner-item span {
  font-size: 13px;
  font-weight: 800;
}

.banner-item h1 {
  margin: 6px 0 0;
  font-size: 24px;
}

.banner-dots {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 7px;
  display: flex;
  justify-content: center;
  gap: 6px;
}

.banner-dots button {
  width: 7px;
  height: 7px;
  border: 0;
  border-radius: 50%;
  background: #c7c3d6;
}

.banner-dots button.active {
  background: #ff4b00;
}

.daily-section,
.partner-section,
.service-section {
  background: #fff;
  padding: 14px;
}

.module-title {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.module-title h2,
.module-title p {
  margin: 0;
}

.module-title h2 {
  font-size: 18px;
}

.module-title p {
  margin-top: 4px;
  color: rgba(0, 0, 0, 0.45);
  font-size: 13px;
}

.module-title>span {
  font-size: 16px;
  color: #ff4b00;
}

.product-marquee {
  position: relative;
  overflow: hidden;
  overflow: clip;
  overscroll-behavior: contain;
  touch-action: pan-y;
  scrollbar-width: none;
  height: 196px;
  margin-top: 14px;
  contain: layout paint;
}

.product-marquee::-webkit-scrollbar {
  display: none;
}

.product-track {
  position: absolute;
  display: flex;
  gap: 12px;
  animation: product-roll 22s linear infinite;
  pointer-events: none;
  user-select: none;
}

.product-tile {
  width: 132px;
  flex: 0 0 auto;
}

.product-tile img {
  width: 132px;
  height: 132px;
  border-radius: 8px;
  object-fit: cover;
}

.product-tile h3 {
  margin: 7px 0 0;
  overflow: hidden;
  color: #1f2933;
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-tile p {
  margin: 4px 0 0;
  color: rgba(0, 0, 0, 0.55);
  font-size: 12px;
  text-align: center;
}

@keyframes product-roll {
  from {
    transform: translateX(0);
  }

  to {
    transform: translateX(-50%);
  }
}

.start-button {
  width: 100%;
  margin-top: 12px;
  border: 0;
  border-radius: 999px;
  background: #ff4b00;
  color: #fff;
  font-weight: 900;
  font-size: 24px;
  padding: 5px 0;
}

.point {
  margin-right: 8px;
  font-size: 11px;
}

.notice-bar {
  display: grid;
  grid-template-columns: 22px minmax(0, 1fr);
  gap: 8px;
  align-items: center;
  height: 44px;
  padding: 0 14px;
  background: #fff;
  overflow: hidden;
}

.speaker {
  display: grid;
  width: 18px;
  height: 18px;
  place-items: center;
  border-radius: 50%;
  background: #ff4b00;
  color: #fff;
  font-size: 12px;
  font-weight: 900;
}

.notice-bar div {
  overflow: hidden;
}

.notice-bar p {
  display: inline-block;
  margin: 0;
  color: #1f2933;
  font-size: 13px;
  white-space: nowrap;
  animation: message-roll 10s linear infinite;
}

@keyframes message-roll {
  from {
    transform: translateX(100%);
  }

  to {
    transform: translateX(-100%);
  }
}

.wallet-panel {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 10px 14px;
  padding: 12px;
}

.wallet-panel span,
.wallet-panel strong {
  display: block;
}

.wallet-panel span {
  color: #7b8794;
  font-size: 11px;
}

.wallet-panel strong {
  margin-top: 6px;
  color: #1f2933;
  font-size: 15px;
  word-break: break-word;
}

.partner-section {
  margin-top: 10px;
}

.brand-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin-top: 14px;
}

.brand-cell {
  display: grid;
  height: 58px;
  place-items: center;
  border-radius: 8px;
  background: linear-gradient(135deg, #fff7ed, #f4f5f7);
  color: #374151;
  font-size: 13px;
  font-weight: 900;
  text-decoration: none;
}

.brand-cell img {
  display: block;
  max-width: 78%;
  max-height: 36px;
  object-fit: contain;
}

.brand-cell span {
  overflow: hidden;
  max-width: 100%;
  padding: 0 8px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.service-section {
  margin-top: 10px;
}

.service-row {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  min-height: 54px;
  border: 0;
  border-bottom: 1px solid #eef0f3;
  background: transparent;
  color: #1f2933;
  font-size: 15px;
  font-weight: 700;
}

.service-row b {
  color: #9aa5b1;
  font-size: 24px;
  line-height: 1;
}

.modal-mask {
  position: fixed;
  z-index: 50;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.45);
}

.invite-modal,
.info-modal {
  width: 100%;
  /* max-width: min(100%, 8.4rem); */
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.22);
}

.invite-modal {
  position: relative;
  display: flex;
  flex-direction: column;
  width: 335px;
  padding: 50px 18px 28px;
  text-align: left;
}

.invite-close {
  position: absolute;
  top: 14px;
  right: 18px;
  width: 22px;
  height: 22px;
  border: 0;
  background: transparent;
  color: #c6c8ce;
  font-weight: 200;
  line-height: 0.75;
}

.invite-modal h2,
.invite-modal p {
  margin: 0;
}

.invite-modal h2 {
  color: #ff4b00;
  font-size: 22px;
  line-height: 1.2;
  font-weight: 900;
}

.invite-modal p {
  margin-top: 12px;
  color: #181b20;
  font-size: 23px;
}

.invite-image {
  display: block;
  width: 250px;
  height: 250px;
  margin: 18px auto 20px;
  object-fit: contain;
}

.invite-modal strong {
  display: block;
  color: #101318;
  font-size: 20px;
  font-weight: 500;
  line-height: 1.25;
  text-align: center;
  overflow-wrap: anywhere;
}

.invite-copy {
  display: block;
  width: 300px;
  height: 58px;
  margin: 30px auto 0;
  border: 0;
  border-radius: 999px;
  background: #ff4b00;
  color: #fff;
  font-size: 20px;
  font-weight: 900;
}

.info-modal {
  padding: 18px;
}

.info-modal header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.info-modal h2,
.info-modal p {
  margin: 0;
}

.info-modal h2 {
  font-size: 18px;
}

.info-modal header button {
  border: 0;
  background: transparent;
  color: #7b8794;
  font-weight: 400;
}

.info-modal p {
  margin-top: 14px;
  color: #52606d;
  font-size: 14px;
  line-height: 1.7;
  white-space: pre-line;
}
</style>
