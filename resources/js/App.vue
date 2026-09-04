<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'

const auth = ref(null)
const loading = ref(true)
const error = ref('')
const notice = ref('')
const view = ref('overview')
const data = reactive({ wallets: [], currencies: [], recent_transactions: [], recent_trades: [], trading: {}, kyc_submission: null, beneficiaries: [], money_movements: [] })
const admin = reactive({ stats: {}, users: [], currencies: [], kyc_submissions: [], trading_setting: {}, money_movements: [], compliance_cases: [], reconciliation_runs: [] })
const loginForm = reactive({ email: 'admin@atlas.test', password: 'password' })
const transfer = reactive({ from_wallet_id: '', to_wallet_id: '', amount: '', description: '' })
const rate = reactive({ from_currency_id: '', to_currency_id: '', rate: '' })
const trade = reactive({ from_currency_id: '', to_currency_id: '', amount: '' })
const tradeQuote = ref(null)
const kyc = reactive({ legal_name: '', date_of_birth: '', country_code: '', document_type: 'passport', document_number: '', document_front: null, document_back: null })
const profitPercentage = ref('1.5')
const showTransfer = ref(false)
const submitting = ref(false)
const loginStep = ref('password')
const twoFactorCode = ref('')
const security = reactive({ password: '', code: '', setup: null })
const maintenance = ref(false)
const movement = reactive({ mode: 'deposit', wallet_id: '', beneficiary_id: '', amount: '' })
const beneficiary = reactive({ currency_id: '', label: '', destination: '', network: '' })

const icons = {
  grid: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
  wallet: '<path d="M20 7V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v10a2 2 0 0 1-2 2H5a3 3 0 0 1-3-3V6"/><path d="M16 13h2"/>',
  activity: '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
  rates: '<circle cx="12" cy="12" r="9"/><path d="M16 8h-6.5a2.5 2.5 0 0 0 0 5H14a2.5 2.5 0 0 1 0 5H7.5M12 6v14"/>',
  trade: '<path d="M7 7h11l-3-3M17 17H6l3 3"/><path d="M18 7l-3 3M6 17l3-3"/>',
  bank: '<path d="M3 10h18M5 10v8m4-8v8m6-8v8m4-8v8M3 21h18M12 3l9 5H3l9-5z"/>',
  shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="M9 12l2 2 4-4"/>',
  lock: '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
  arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  logout: '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M21 19V5a2 2 0 0 0-2-2h-6"/>',
}

const nav = computed(() => [
  { id: 'overview', label: 'Overview', icon: 'grid' },
  { id: 'wallets', label: 'Wallets', icon: 'wallet' },
  { id: 'activity', label: 'Activity', icon: 'activity' },
  { id: 'payments', label: 'Deposit & withdraw', icon: 'bank' },
  { id: 'trade', label: 'Trade crypto', icon: 'trade' },
  { id: 'kyc', label: 'Verification', icon: 'shield' },
  { id: 'security', label: 'Security', icon: 'lock' },
  ...(auth.value?.role === 'admin' ? [{ id: 'users', label: 'Users', icon: 'users' }, { id: 'rates', label: 'Markets & fees', icon: 'rates' }, { id: 'compliance', label: 'KYC reviews', icon: 'shield' }, { id: 'operations', label: 'Operations', icon: 'bank' }] : []),
])

const totalUsd = computed(() => data.wallets.reduce((sum, w) => sum + Number(w.balance) * Number(w.currency.market_price_usd || 0), 0))
const fmt = (value, currency = 'USD') => { try { return new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: ['BTC','ETH'].includes(currency) ? 8 : 2 }).format(Number(value || 0)) } catch { return `${Number(value || 0).toLocaleString(undefined,{maximumFractionDigits:8})} ${currency}` } }
const date = value => new Intl.DateTimeFormat('en', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(value))
const initials = name => name?.split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase()
const txSign = type => ['withdrawal', 'transfer_out'].includes(type) ? '-' : '+'
const label = value => value?.replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase())

async function loadDashboard() {
  const response = await axios.get('/api/dashboard')
  auth.value = response.data.user
  Object.assign(data, response.data)
  if (!transfer.from_wallet_id && data.wallets.length) transfer.from_wallet_id = data.wallets[0].id
  if (!movement.wallet_id && data.wallets.length) movement.wallet_id = data.wallets[0].id
  if (auth.value.role === 'admin') await loadAdmin()
}
async function loadAdmin() { const response = await axios.get('/api/admin/dashboard'); Object.assign(admin, response.data); profitPercentage.value = response.data.trading_setting?.profit_percentage || '1.5' }
async function login() {
  submitting.value = true; error.value = ''
  try { await axios.get('/sanctum/csrf-cookie'); const response = await axios.post('/api/login', loginForm); if (response.data.two_factor_required) { loginStep.value = 'two_factor'; return } await loadDashboard(); if (response.data.verification_required) notice.value = 'Verify your email address before using wallet features.' }
  catch (e) { error.value = e.response?.data?.message || 'Unable to sign in.' }
  finally { submitting.value = false }
}
async function completeTwoFactor() { submitting.value = true; error.value = ''; try { await axios.post('/api/2fa/challenge', { code: twoFactorCode.value }); loginStep.value = 'password'; twoFactorCode.value = ''; await loadDashboard() } catch (e) { error.value = e.response?.data?.message || 'Invalid authentication code.' } finally { submitting.value = false } }
async function sendVerification() { try { const response = await axios.post('/api/email/verification/send'); notice.value = response.data.message } catch (e) { error.value = e.response?.data?.message || 'Verification email could not be sent.' } }
async function beginTwoFactor() { submitting.value = true; error.value = ''; try { security.setup = (await axios.post('/api/2fa/enable', { password: security.password })).data; security.password = '' } catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || '2FA setup failed.' } finally { submitting.value = false } }
async function confirmTwoFactor() { submitting.value = true; error.value = ''; try { notice.value = (await axios.post('/api/2fa/confirm', { code: security.code })).data.message; security.setup = null; security.code = ''; await loadDashboard() } catch (e) { error.value = e.response?.data?.message || 'Invalid authentication code.' } finally { submitting.value = false } }
async function disableTwoFactor() { submitting.value = true; error.value = ''; try { notice.value = (await axios.delete('/api/2fa', { data: { password: security.password } })).data.message; security.password = ''; await loadDashboard() } catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || '2FA could not be disabled.' } finally { submitting.value = false } }
async function logout() { await axios.post('/api/logout'); auth.value = null; view.value = 'overview' }
async function sendTransfer() {
  submitting.value = true; error.value = ''
  try { await axios.post('/api/transfers', transfer, { headers: { 'Idempotency-Key': crypto.randomUUID() } }); showTransfer.value = false; transfer.amount = ''; transfer.to_wallet_id = ''; notice.value = 'Transfer completed successfully.'; await loadDashboard(); setTimeout(() => notice.value = '', 3500) }
  catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || 'Transfer could not be completed.' }
  finally { submitting.value = false }
}
async function saveRate() {
  submitting.value = true; error.value = ''
  try { await axios.post('/api/admin/rates', rate); notice.value = 'Exchange rate published.'; rate.rate = ''; await loadAdmin() }
  catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Rate could not be saved.' }
  finally { submitting.value = false }
}
async function toggleUser(user) { await axios.patch(`/api/admin/users/${user.id}`, { status: user.status === 'active' ? 'suspended' : 'active' }); await loadAdmin() }
async function getTradeQuote() {
  tradeQuote.value = null; error.value = ''
  if (!trade.from_currency_id || !trade.to_currency_id || !trade.amount) return
  try { tradeQuote.value = (await axios.post('/api/trades/quote', trade)).data }
  catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || 'A quote is not available.' }
}
async function executeTrade() {
  submitting.value = true; error.value = ''
  try { const response = await axios.post('/api/trades', { quote_token: tradeQuote.value?.quote_token }, { headers: { 'Idempotency-Key': crypto.randomUUID() } }); notice.value = response.data.message; trade.amount = ''; tradeQuote.value = null; await loadDashboard() }
  catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || 'Trade could not be completed.' }
  finally { submitting.value = false }
}
async function submitKyc() {
  submitting.value = true; error.value = ''
  try {
    const body = new FormData(); Object.entries(kyc).forEach(([key, value]) => { if (value) body.append(key, value) })
    await axios.post('/api/kyc', body); notice.value = 'Your documents were submitted for review.'; await loadDashboard()
  } catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || 'KYC submission failed.' }
  finally { submitting.value = false }
}
async function reviewKyc(item, status) { await axios.patch(`/api/admin/kyc/${item.id}`, { status }); notice.value = `KYC ${status}.`; await loadDashboard() }
async function saveProfit() {
  submitting.value = true; error.value = ''
  try { const response = await axios.patch('/api/admin/trading-settings', { profit_percentage: profitPercentage.value }); admin.trading_setting = response.data; notice.value = 'Trading profit percentage updated.' }
  catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Profit percentage could not be saved.' }
  finally { submitting.value = false }
}
async function addBeneficiary() { submitting.value = true; error.value = ''; try { await axios.post('/api/beneficiaries', beneficiary); Object.assign(beneficiary, { currency_id: '', label: '', destination: '', network: '' }); notice.value = 'Beneficiary verified and saved.'; await loadDashboard() } catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || 'Beneficiary could not be saved.' } finally { submitting.value = false } }
async function requestMovement() { submitting.value = true; error.value = ''; try { const endpoint = movement.mode === 'deposit' ? '/api/deposits' : '/api/withdrawals'; const payload = { wallet_id: movement.wallet_id, amount: movement.amount, ...(movement.mode === 'withdrawal' ? { beneficiary_id: movement.beneficiary_id } : {}) }; const response = await axios.post(endpoint, payload, { headers: { 'Idempotency-Key': crypto.randomUUID() } }); notice.value = `${label(movement.mode)} request ${response.data.reference} created.`; movement.amount = ''; await loadDashboard() } catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || 'Request could not be created.' } finally { submitting.value = false } }
async function decideMovement(item, action) { const notes = action === 'reject' ? 'Rejected after administrator review.' : null; try { await axios.post(`/api/admin/money-movements/${item.id}/${action}`, notes ? { notes } : {}, { headers: { 'Idempotency-Key': crypto.randomUUID() } }); notice.value = `Movement ${action}d.`; await loadAdmin(); await loadDashboard() } catch (e) { error.value = Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || 'Operation failed.' } }
async function resolveCase(item, status = 'cleared') { try { await axios.patch(`/api/admin/compliance-cases/${item.id}`, { status, resolution: status === 'cleared' ? 'Reviewed and cleared by compliance administrator.' : null }); notice.value = `Case ${status}.`; await loadAdmin() } catch (e) { error.value = e.response?.data?.message || 'Case update failed.' } }
async function runReconciliation() { submitting.value = true; try { const response = await axios.post('/api/admin/reconciliations'); notice.value = `Reconciliation ${response.data.status}: ${response.data.discrepancy_count} discrepancies.`; await loadAdmin() } catch (e) { error.value = e.response?.data?.message || 'Reconciliation failed.' } finally { submitting.value = false } }

onMounted(async () => { window.addEventListener('atlas-maintenance', () => maintenance.value = true); try { await loadDashboard() } catch { auth.value = null } finally { loading.value = false } })
</script>

<template>
  <div v-if="loading" class="loading"><div class="brand-mark">A</div><span>Preparing your wallets…</span></div>

  <main v-else-if="!auth" class="login-page">
    <section class="login-aside">
      <div class="brand"><div class="brand-mark">A</div><span>Atlas</span></div>
      <div class="login-copy">
        <span class="eyebrow light">Money without borders</span>
        <h1>One calm place for every currency.</h1>
        <p>Move, hold, and manage funds across your organization with a clear ledger behind every balance.</p>
      </div>
      <div class="floating-card card-eur"><span>EUR wallet</span><strong>€1,740.00</strong><small>Available now</small></div>
      <div class="floating-card card-usd"><span>USD wallet</span><strong>$4,820.00</strong><small>↑ 8.4% this month</small></div>
      <div class="orb"></div>
      <p class="aside-foot">Protected by session authentication and role-based access</p>
    </section>
    <section class="login-panel">
      <form v-if="loginStep === 'password'" class="login-form" @submit.prevent="login">
        <span class="eyebrow">Welcome back</span>
        <h2>Sign in to Atlas</h2>
        <p class="muted">Use your verified Atlas account to continue.</p>
        <div v-if="error" class="alert">{{ error }}</div>
        <label>Email address<input v-model="loginForm.email" type="email" autocomplete="email" required></label>
        <label>Password<input v-model="loginForm.password" type="password" autocomplete="current-password" required></label>
        <button class="primary wide" :disabled="submitting">{{ submitting ? 'Signing in…' : 'Sign in' }}<svg viewBox="0 0 24 24" v-html="icons.arrow" /></button>
        <div class="demo-note">Use the administrator credentials configured during installation.</div>
      </form>
      <form v-else class="login-form" @submit.prevent="completeTwoFactor">
        <span class="eyebrow">Second step</span><h2>Authentication code</h2><p class="muted">Enter the six-digit code from your authenticator app or a recovery code.</p><div v-if="error" class="alert">{{ error }}</div><label>Authentication code<input v-model="twoFactorCode" inputmode="numeric" autocomplete="one-time-code" required autofocus></label><button class="primary wide" :disabled="submitting">{{ submitting ? 'Verifying…' : 'Verify and sign in' }}</button><button type="button" class="text-link" @click="loginStep='password';error=''">Back to password</button>
      </form>
    </section>
  </main>

  <div v-else class="app-shell">
    <aside class="sidebar">
      <div class="brand"><div class="brand-mark">A</div><span>Atlas</span></div>
      <nav>
        <button v-for="item in nav" :key="item.id" :class="{ active: view === item.id }" @click="view = item.id">
          <svg viewBox="0 0 24 24" v-html="icons[item.icon]" />{{ item.label }}
        </button>
      </nav>
      <div class="side-account"><div class="avatar">{{ initials(auth.name) }}</div><div><strong>{{ auth.name }}</strong><span>{{ auth.role }}</span></div><button title="Sign out" @click="logout"><svg viewBox="0 0 24 24" v-html="icons.logout" /></button></div>
    </aside>

    <main class="workspace">
      <header><div><span class="eyebrow">{{ view === 'overview' ? 'Thursday, 3 September' : 'Atlas workspace' }}</span><h1>{{ view === 'overview' ? `Good afternoon, ${auth.name.split(' ')[0]}` : nav.find(n => n.id === view)?.label }}</h1></div><button class="primary" @click="showTransfer = true"><svg viewBox="0 0 24 24" v-html="icons.arrow" />Send money</button></header>
      <div v-if="notice" class="notice">{{ notice }}</div>
      <div v-if="!auth.email_verified_at" class="email-banner"><div><strong>Verify your email address</strong><span>Wallet, KYC, trading, and administration actions remain locked.</span></div><button class="outline" @click="sendVerification">Send verification email</button></div>

      <template v-if="view === 'overview'">
        <section class="summary-grid">
          <article class="balance-card">
            <div><span class="card-label">Total balance</span><button class="ghost-dot">•••</button></div>
            <strong>{{ fmt(totalUsd) }}</strong><p><b>+6.8%</b> from last month</p>
            <div class="spark"><i v-for="h in [28,42,35,54,48,67,59,76,70,86,80,94]" :key="h" :style="{height: h+'%'}"></i></div>
          </article>
          <article class="quick-card"><span class="card-label">Quick transfer</span><div class="people"><button v-for="person in ['NW','AM','SJ']" :key="person">{{ person }}</button><button class="add"><svg viewBox="0 0 24 24" v-html="icons.plus" /></button></div><p>Recent recipients</p><button class="text-link" @click="showTransfer = true">Start a transfer <svg viewBox="0 0 24 24" v-html="icons.arrow" /></button></article>
        </section>

        <section class="section-head"><div><h2>Your wallets</h2><p>{{ data.wallets.length }} currencies, ready when you are</p></div><button class="outline" @click="view='wallets'">View all</button></section>
        <section class="wallet-row">
          <article v-for="(wallet, i) in data.wallets.slice(0,3)" :key="wallet.id" class="wallet-card" :class="`tone-${i%3}`">
            <div class="currency-icon">{{ wallet.currency.symbol }}</div><span>{{ wallet.currency.name }}</span><strong>{{ fmt(wallet.balance, wallet.currency.code) }}</strong><small>{{ wallet.currency.code }} · Available</small>
          </article>
        </section>
        <section class="activity-card"><div class="section-head inside"><div><h2>Recent activity</h2><p>Your latest wallet movements</p></div><button class="text-link" @click="view='activity'">See all</button></div><div class="tx-list"><div v-for="tx in data.recent_transactions.slice(0,5)" :key="tx.id" class="tx"><div class="tx-icon" :class="txSign(tx.type)==='-'?'out':'in'">{{ txSign(tx.type)==='-'?'↗':'↙' }}</div><div><strong>{{ tx.description || label(tx.type) }}</strong><span>{{ date(tx.created_at) }} · {{ tx.reference }}</span></div><div class="tx-amount" :class="txSign(tx.type)==='-'?'negative':'positive'"><strong>{{ txSign(tx.type) }}{{ fmt(tx.amount, tx.wallet.currency.code) }}</strong><span>{{ tx.wallet.currency.code }}</span></div></div></div></section>
      </template>

      <template v-else-if="view === 'wallets'">
        <section class="wallet-grid"><article v-for="(wallet,i) in data.wallets" :key="wallet.id" class="wallet-detail" :class="`tone-${i%3}`"><div class="wallet-top"><div class="currency-icon">{{ wallet.currency.symbol }}</div><span>{{ wallet.currency.code }}</span></div><small>Available balance</small><strong>{{ fmt(wallet.balance, wallet.currency.code) }}</strong><div class="wallet-actions"><button @click="showTransfer=true; transfer.from_wallet_id=wallet.id">Send</button><button @click="view='activity'">History</button></div></article></section>
      </template>

      <template v-else-if="view === 'activity'">
        <section class="activity-card full"><div class="section-head inside"><div><h2>All transactions</h2><p>Completed transfers and adjustments</p></div><span class="status-pill">Live ledger</span></div><div class="tx-list"><div v-for="tx in data.recent_transactions" :key="tx.id" class="tx"><div class="tx-icon" :class="txSign(tx.type)==='-'?'out':'in'">{{ txSign(tx.type)==='-'?'↗':'↙' }}</div><div><strong>{{ tx.description || label(tx.type) }}</strong><span>{{ date(tx.created_at) }} · {{ label(tx.type) }}</span></div><div class="tx-amount" :class="txSign(tx.type)==='-'?'negative':'positive'"><strong>{{ txSign(tx.type) }}{{ fmt(tx.amount, tx.wallet.currency.code) }}</strong><span>{{ tx.reference }}</span></div></div></div></section>
      </template>

      <template v-else-if="view === 'payments'">
        <section class="payments-grid">
          <form class="form-card payment-form" @submit.prevent="requestMovement">
            <span class="eyebrow">Money movement</span><h2>Deposit or withdraw</h2>
            <div class="mode-switch"><button type="button" :class="{active:movement.mode==='deposit'}" @click="movement.mode='deposit'">Deposit</button><button type="button" :class="{active:movement.mode==='withdrawal'}" @click="movement.mode='withdrawal'">Withdraw</button></div>
            <div v-if="error" class="alert">{{ error }}</div>
            <label>Wallet<select v-model="movement.wallet_id" required><option v-for="w in data.wallets" :key="w.id" :value="w.id">{{ w.currency.code }} · available {{ fmt(w.available_balance,w.currency.code) }}</option></select></label>
            <label v-if="movement.mode==='withdrawal'">Verified beneficiary<select v-model="movement.beneficiary_id" required><option value="" disabled>Select beneficiary</option><option v-for="b in data.beneficiaries.filter(b=>b.currency_id===Number(data.wallets.find(w=>w.id===Number(movement.wallet_id))?.currency_id))" :key="b.id" :value="b.id">{{ b.label }} · {{ b.currency.code }}</option></select></label>
            <label>Amount<input v-model="movement.amount" type="number" min="0.00000001" step="any" required></label>
            <p class="kyc-consent">Deposits remain pending until settlement. Withdrawals reserve funds and require administrator approval. Crypto withdrawals require approved KYC.</p>
            <button class="primary wide" :disabled="submitting">{{ submitting ? 'Creating request…' : `Request ${movement.mode}` }}</button>
          </form>
          <form class="form-card payment-form" @submit.prevent="addBeneficiary">
            <span class="eyebrow">Address book</span><h2>Add beneficiary</h2>
            <label>Currency<select v-model="beneficiary.currency_id" required><option value="" disabled>Select currency</option><option v-for="c in data.currencies" :key="c.id" :value="c.id">{{ c.code }} · {{ c.name }}</option></select></label>
            <label>Label<input v-model="beneficiary.label" placeholder="My bank account or wallet" required></label>
            <label>Destination<input v-model="beneficiary.destination" placeholder="Account, IBAN, or wallet address" required></label>
            <label>Network <small>for crypto</small><input v-model="beneficiary.network" placeholder="Bitcoin, Ethereum…"></label>
            <button class="outline wide" :disabled="submitting">Save verified beneficiary</button>
          </form>
        </section>
        <section class="table-card movement-history"><div class="section-head inside"><div><h2>Deposit and withdrawal requests</h2><p>Pending, reserved, and settled movements</p></div></div><div v-if="!data.money_movements.length" class="empty-state">No requests yet.</div><div v-else class="table movement-table"><div class="table-row table-head"><span>Reference</span><span>Type</span><span>Amount</span><span>Rail</span><span>Status</span></div><div v-for="item in data.money_movements" :key="item.id" class="table-row"><span><b>{{ item.reference.slice(0,8) }}</b><small>{{ date(item.created_at) }}</small></span><span>{{ label(item.direction) }}</span><span>{{ fmt(item.amount,item.wallet.currency.code) }}</span><span>{{ label(item.rail) }}</span><span><em class="status" :class="item.status==='completed'?'active':item.status==='rejected'||item.status==='failed'?'suspended':''">{{ label(item.status) }}</em></span></div></div></section>
      </template>

      <template v-else-if="view === 'trade'">
        <section v-if="auth.kyc_status !== 'approved'" class="verification-gate">
          <div class="shield-large"><svg viewBox="0 0 24 24" v-html="icons.shield" /></div><span class="eyebrow">Identity required</span><h2>Verify your identity to trade</h2><p>Buying, selling, and exchanging crypto is available only after KYC approval.</p><button class="primary" @click="view='kyc'">Start verification</button>
        </section>
        <section v-else class="trade-layout">
          <form class="trade-card" @submit.prevent="tradeQuote ? executeTrade() : getTradeQuote()">
            <div class="trade-title"><div><span class="eyebrow">Crypto desk</span><h2>Buy, sell or swap</h2></div><span class="kyc-badge"><svg viewBox="0 0 24 24" v-html="icons.shield" /> KYC verified</span></div>
            <label>You pay<select v-model="trade.from_currency_id" @change="tradeQuote=null" required><option value="" disabled>Select asset</option><option v-for="c in data.currencies.filter(c=>c.is_tradeable)" :key="c.id" :value="c.id">{{ c.code }} · {{ c.name }}</option></select></label>
            <label>Amount<input v-model="trade.amount" @input="tradeQuote=null" type="number" min="0.00000001" step="any" placeholder="0.00" required></label>
            <button type="button" class="swap-button" @click="[trade.from_currency_id,trade.to_currency_id]=[trade.to_currency_id,trade.from_currency_id];tradeQuote=null">⇅</button>
            <label>You receive<select v-model="trade.to_currency_id" @change="tradeQuote=null" required><option value="" disabled>Select asset</option><option v-for="c in data.currencies.filter(c=>c.is_tradeable)" :key="c.id" :value="c.id">{{ c.code }} · {{ c.name }}</option></select></label>
            <div v-if="error" class="alert">{{ error }}</div>
            <div v-if="tradeQuote" class="quote-box"><div><span>Trade type</span><strong>{{ label(tradeQuote.type) }}</strong></div><div><span>Market rate</span><strong>1 {{ tradeQuote.from_currency.code }} = {{ Number(tradeQuote.market_rate).toLocaleString(undefined,{maximumFractionDigits:8}) }} {{ tradeQuote.to_currency.code }}</strong></div><div><span>Atlas spread ({{ tradeQuote.profit_percentage }}%)</span><strong>{{ Number(tradeQuote.fee_amount).toLocaleString(undefined,{maximumFractionDigits:8}) }} {{ tradeQuote.to_currency.code }}</strong></div><div class="quote-total"><span>You receive</span><strong>{{ Number(tradeQuote.to_amount).toLocaleString(undefined,{maximumFractionDigits:8}) }} {{ tradeQuote.to_currency.code }}</strong></div><small>Locked until {{ new Date(tradeQuote.expires_at).toLocaleTimeString() }}</small></div>
            <button class="primary wide" :disabled="submitting">{{ submitting ? 'Processing…' : tradeQuote ? `Confirm ${tradeQuote.type}` : 'Review quote' }}</button>
            <small class="quote-note">Quotes use admin-managed reference prices. The spread is included in the amount received.</small>
          </form>
          <section class="market-card"><div class="section-head inside"><div><h2>Markets</h2><p>Reference prices in USD</p></div><span class="status-pill">Trading open</span></div><div class="market-list"><div v-for="asset in data.currencies.filter(c=>c.type==='crypto')" :key="asset.id"><i>{{ asset.symbol }}</i><span><strong>{{ asset.name }}</strong><small>{{ asset.code }}</small></span><b>{{ fmt(asset.market_price_usd) }}</b></div></div></section>
        </section>
        <section v-if="data.recent_trades.length" class="table-card trade-history"><div class="section-head inside"><div><h2>Trade history</h2><p>Your latest completed orders</p></div></div><div class="table trade-table"><div class="table-row table-head"><span>Pair</span><span>Type</span><span>Paid</span><span>Received</span><span>Status</span></div><div v-for="item in data.recent_trades" :key="item.id" class="table-row"><span><b>{{ item.from_currency.code }}/{{ item.to_currency.code }}</b></span><span>{{ label(item.type) }}</span><span>{{ Number(item.from_amount).toLocaleString() }} {{ item.from_currency.code }}</span><span>{{ Number(item.to_amount).toLocaleString() }} {{ item.to_currency.code }}</span><span><em class="status active">{{ item.status }}</em></span></div></div></section>
      </template>

      <template v-else-if="view === 'kyc'">
        <section v-if="auth.kyc_status === 'approved'" class="verification-gate approved"><div class="shield-large"><svg viewBox="0 0 24 24" v-html="icons.shield" /></div><span class="eyebrow">Verification complete</span><h2>Your identity is verified</h2><p>You can buy, sell, and exchange supported crypto assets.</p><button class="primary" @click="view='trade'">Go to crypto trading</button></section>
        <section v-else-if="auth.kyc_status === 'pending'" class="verification-gate pending"><div class="shield-large">⌛</div><span class="eyebrow">Review in progress</span><h2>Your documents are being reviewed</h2><p>Trading will unlock automatically after an administrator approves your submission.</p></section>
        <form v-else class="kyc-form" @submit.prevent="submitKyc"><div class="section-head inside"><div><span class="eyebrow">Secure verification</span><h2>Know your customer</h2><p>Submit a government-issued identity document. Files are kept in private storage.</p></div></div><div class="kyc-fields"><div v-if="error" class="alert span-two">{{ error }}</div><label>Full legal name<input v-model="kyc.legal_name" required></label><label>Date of birth<input v-model="kyc.date_of_birth" type="date" required></label><label>Country code<input v-model="kyc.country_code" maxlength="2" placeholder="US" required></label><label>Document type<select v-model="kyc.document_type"><option value="passport">Passport</option><option value="national_id">National ID</option><option value="drivers_license">Driver’s license</option></select></label><label class="span-two">Document number<input v-model="kyc.document_number" required></label><label>Document front<input type="file" accept="image/jpeg,image/png,application/pdf" @change="kyc.document_front=$event.target.files[0]" required></label><label>Document back <small>if applicable</small><input type="file" accept="image/jpeg,image/png,application/pdf" @change="kyc.document_back=$event.target.files[0]"></label><p class="kyc-consent span-two">By submitting, you confirm that the information is accurate and belongs to you.</p><button class="primary span-two" :disabled="submitting">{{ submitting ? 'Submitting…' : 'Submit for verification' }}</button></div></form>
      </template>

      <template v-else-if="view === 'security'">
        <section class="security-grid">
          <article class="security-card">
            <div class="security-icon"><svg viewBox="0 0 24 24" v-html="icons.lock" /></div>
            <span class="eyebrow">Account protection</span><h2>Two-factor authentication</h2>
            <p>Add an authenticator-app code to your password. Recovery codes work once and should be stored offline.</p>
            <div v-if="error" class="alert">{{ error }}</div>
            <template v-if="auth.two_factor_confirmed_at">
              <div class="security-status active"><span>Enabled</span><small>Required for sensitive wallet and administrator actions.</small></div>
              <form class="security-form" @submit.prevent="disableTwoFactor"><label>Current password<input v-model="security.password" type="password" autocomplete="current-password" required></label><button class="danger-outline" :disabled="submitting">Disable two-factor authentication</button></form>
            </template>
            <template v-else-if="!security.setup">
              <div class="security-status"><span>Not enabled</span><small>Set up 2FA before moving funds or submitting KYC.</small></div>
              <form class="security-form" @submit.prevent="beginTwoFactor"><label>Current password<input v-model="security.password" type="password" autocomplete="current-password" required></label><button class="primary" :disabled="submitting">Create authenticator secret</button></form>
            </template>
            <template v-else>
              <div class="setup-secret"><span>Authenticator secret</span><strong>{{ security.setup.secret }}</strong><small>Add this secret to any TOTP authenticator app.</small></div>
              <div class="recovery-box"><span>Save these recovery codes</span><code v-for="code in security.setup.recovery_codes" :key="code">{{ code }}</code></div>
              <form class="security-form" @submit.prevent="confirmTwoFactor"><label>Six-digit code<input v-model="security.code" inputmode="numeric" autocomplete="one-time-code" required></label><button class="primary" :disabled="submitting">Confirm and enable</button></form>
            </template>
          </article>
          <article class="security-card">
            <div class="security-icon"><svg viewBox="0 0 24 24" v-html="icons.shield" /></div>
            <span class="eyebrow">Identity channel</span><h2>Email verification</h2>
            <p>Security alerts, transaction notices, export links, and KYC decisions are delivered to this address.</p>
            <div class="security-status" :class="{ active: auth.email_verified_at }"><span>{{ auth.email_verified_at ? 'Verified' : 'Verification required' }}</span><small>{{ auth.email }}</small></div>
            <button v-if="!auth.email_verified_at" class="outline" @click="sendVerification">Send verification email</button>
          </article>
        </section>
      </template>

      <template v-else-if="view === 'users'">
        <section class="stats-row"><article><span>Total users</span><strong>{{ admin.stats.users }}</strong></article><article><span>Active wallets</span><strong>{{ admin.stats.wallets }}</strong></article><article><span>Transactions</span><strong>{{ admin.stats.transactions }}</strong></article></section>
        <section class="table-card"><div class="section-head inside"><div><h2>Account directory</h2><p>Manage access across your organization</p></div><button class="outline">+ Add user</button></div><div class="table"><div class="table-row table-head"><span>User</span><span>Role</span><span>Wallets</span><span>Status</span><span></span></div><div v-for="user in admin.users" :key="user.id" class="table-row"><span class="user-cell"><i>{{ initials(user.name) }}</i><b>{{ user.name }}<small>{{ user.email }}</small></b></span><span>{{ label(user.role) }}</span><span>{{ user.wallets_count }}</span><span><em class="status" :class="user.status">{{ user.status }}</em></span><span><button class="table-action" @click="toggleUser(user)">{{ user.status === 'active' ? 'Suspend' : 'Activate' }}</button></span></div></div></section>
      </template>

      <template v-else-if="view === 'rates'">
        <section class="settings-strip"><form @submit.prevent="saveProfit"><div><span class="eyebrow">Trading revenue</span><h2>Profit spread</h2><p>Deducted from the output amount on every crypto trade.</p></div><label>Percentage<div class="percent-input"><input v-model="profitPercentage" type="number" min="0" max="25" step="0.01" required><span>%</span></div></label><button class="primary" :disabled="submitting">Save spread</button></form></section>
        <section class="rates-layout"><form class="form-card" @submit.prevent="saveRate"><span class="eyebrow">Fiat conversion</span><h2>Publish exchange rate</h2><label>From<select v-model="rate.from_currency_id" required><option value="" disabled>Select currency</option><option v-for="c in admin.currencies.filter(c=>c.type==='fiat')" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option></select></label><label>To<select v-model="rate.to_currency_id" required><option value="" disabled>Select currency</option><option v-for="c in admin.currencies.filter(c=>c.type==='fiat')" :key="c.id" :value="c.id">{{ c.code }} — {{ c.name }}</option></select></label><label>Rate<input v-model="rate.rate" type="number" step="0.000000000001" min="0" placeholder="1.000000" required></label><button class="primary wide" :disabled="submitting">Publish rate</button></form><section class="table-card"><div class="section-head inside"><div><h2>Supported assets</h2><p>Reference prices drive crypto quotes</p></div></div><div class="currency-list"><div v-for="currency in admin.currencies" :key="currency.id"><i>{{ currency.symbol }}</i><span><strong>{{ currency.code }} · {{ label(currency.type) }}</strong><small>{{ currency.name }} · {{ fmt(currency.market_price_usd) }}</small></span><em class="status" :class="currency.is_tradeable?'active':'suspended'">{{ currency.is_tradeable ? 'Tradeable' : 'Disabled' }}</em></div></div></section></section>
      </template>

      <template v-else-if="view === 'compliance'">
        <section class="stats-row"><article><span>Pending review</span><strong>{{ admin.stats.pending_kyc }}</strong></article><article><span>Verified users</span><strong>{{ admin.users.filter(u=>u.kyc_status==='approved').length }}</strong></article><article><span>Completed trades</span><strong>{{ admin.stats.trades }}</strong></article></section>
        <section class="table-card"><div class="section-head inside"><div><h2>KYC review queue</h2><p>Approve users before enabling crypto trading</p></div></div><div v-if="!admin.kyc_submissions.length" class="empty-state">No identity submissions yet.</div><div v-else class="table compliance-table"><div class="table-row table-head"><span>Customer</span><span>Document</span><span>Submitted</span><span>Status</span><span>Decision</span></div><div v-for="item in admin.kyc_submissions" :key="item.id" class="table-row"><span class="user-cell"><i>{{ initials(item.user.name) }}</i><b>{{ item.user.name }}<small>{{ item.user.email }}</small></b></span><span><b>{{ label(item.document_type) }}</b><a class="document-link" :href="`/api/admin/kyc/${item.id}/document/front`" target="_blank">Open document</a></span><span>{{ date(item.created_at) }}</span><span><em class="status" :class="item.status==='approved'?'active':item.status==='rejected'?'suspended':''">{{ item.status }}</em></span><span class="review-actions"><template v-if="item.status==='pending'"><button @click="reviewKyc(item,'approved')">Approve</button><button class="reject" @click="reviewKyc(item,'rejected')">Reject</button></template><small v-else>Reviewed</small></span></div></div></section>
      </template>

      <template v-else-if="view === 'operations'">
        <section class="stats-row"><article><span>Pending movements</span><strong>{{ admin.stats.pending_movements || 0 }}</strong></article><article><span>Open compliance cases</span><strong>{{ admin.stats.open_cases || 0 }}</strong></article><article><span>Last reconciliation</span><strong class="small-stat">{{ admin.reconciliation_runs[0]?.status || 'Not run' }}</strong></article></section>
        <section class="table-card"><div class="section-head inside"><div><h2>Money movement approvals</h2><p>Two-person review and provider settlement</p></div></div><div v-if="!admin.money_movements.length" class="empty-state">No money movements.</div><div v-else class="table operations-table"><div class="table-row table-head"><span>Customer</span><span>Movement</span><span>Amount</span><span>Status</span><span>Actions</span></div><div v-for="item in admin.money_movements" :key="item.id" class="table-row"><span><b>{{ item.user.name }}</b><small>{{ item.user.risk_level }} risk</small></span><span>{{ label(item.direction) }} · {{ item.wallet.currency.code }}</span><span>{{ fmt(item.amount,item.wallet.currency.code) }}</span><span><em class="status" :class="item.status==='completed'?'active':item.status==='rejected'?'suspended':''">{{ label(item.status) }}</em></span><span class="review-actions"><template v-if="item.status==='pending_review'"><button @click="decideMovement(item,'approve')">Approve</button><button class="reject" @click="decideMovement(item,'reject')">Reject</button></template><button v-else-if="item.status==='processing'||(item.direction==='deposit'&&item.status==='pending')" @click="decideMovement(item,'complete')">Confirm settlement</button><small v-else>—</small></span></div></div></section>
        <section class="table-card operations-section"><div class="section-head inside"><div><h2>Compliance cases</h2><p>Risk signals must be cleared before withdrawal approval</p></div></div><div v-if="!admin.compliance_cases.length" class="empty-state">No compliance cases.</div><div v-else class="table case-table"><div class="table-row table-head"><span>Reference</span><span>Customer</span><span>Reason</span><span>Severity</span><span>Decision</span></div><div v-for="item in admin.compliance_cases" :key="item.id" class="table-row"><span>{{ item.reference.slice(0,8) }}</span><span>{{ item.user.name }}</span><span>{{ item.reason }}</span><span><em class="status" :class="item.severity==='high'||item.severity==='critical'?'suspended':''">{{ item.severity }}</em></span><span class="review-actions"><button v-if="!['cleared','closed'].includes(item.status)" @click="resolveCase(item)">Clear</button><small v-else>{{ label(item.status) }}</small></span></div></div></section>
        <section class="settings-strip operations-section"><form @submit.prevent="runReconciliation"><div><span class="eyebrow">Ledger control</span><h2>Wallet reconciliation</h2><p>Compare every customer wallet balance with its double-entry ledger account.</p></div><div class="reconcile-result"><strong>{{ admin.reconciliation_runs[0]?.discrepancy_count ?? '—' }}</strong><small>latest discrepancies</small></div><button class="primary" :disabled="submitting">Run reconciliation</button></form></section>
      </template>
    </main>

    <div v-if="showTransfer" class="modal-wrap" @click.self="showTransfer=false"><form class="modal" @submit.prevent="sendTransfer"><button type="button" class="close" @click="showTransfer=false">×</button><span class="eyebrow">New transfer</span><h2>Send money</h2><p class="muted">Funds are converted using the latest published rate.</p><div v-if="error" class="alert">{{ error }}</div><label>From wallet<select v-model="transfer.from_wallet_id" required><option v-for="w in data.wallets" :key="w.id" :value="w.id">{{ w.currency.code }} · {{ fmt(w.balance,w.currency.code) }}</option></select></label><label>Destination wallet ID<input v-model="transfer.to_wallet_id" type="number" min="1" placeholder="Enter wallet ID" required></label><label>Amount<input v-model="transfer.amount" type="number" min="0.01" step="0.01" placeholder="0.00" required></label><label>Note <small>optional</small><input v-model="transfer.description" maxlength="255" placeholder="What is this for?"></label><button class="primary wide" :disabled="submitting">{{ submitting ? 'Sending…' : 'Confirm transfer' }}</button></form></div>
    <div v-if="maintenance" class="maintenance-overlay"><div><div class="brand-mark">A</div><span class="eyebrow">Scheduled maintenance</span><h2>Service temporarily unavailable</h2><p>Your balances remain recorded. Please try again after the maintenance window.</p><button class="primary" @click="maintenance=false">Try again</button></div></div>
  </div>
</template>
