import { computed, ref } from 'vue'

export const languages = [
  { code: 'en', label: 'English' },
  { code: 'sv', label: 'Svenska' },
  { code: 'nl', label: 'Nederlands' },
  { code: 'fr', label: 'francais' },
  { code: 'de', label: 'Deutsch' },
  { code: 'pl', label: 'Polsk' },
  { code: 'vi', label: 'Tieng Viet' },
  { code: 'ja', label: 'Japanese' },
  { code: 'at', label: 'osterreichisch' },
  { code: 'ch', label: 'Schweizerdeutsch' },
  { code: 'sk', label: 'slovensky' },
  { code: 'lv', label: 'latviski' },
  { code: 'sl', label: 'slovenski' },
  { code: 'mx', label: 'Mexico' },
]

const en = {
  selectLanguage: 'Select Language',
  welcomeTitle: 'Please log in or create your account',
  login: 'Log in',
  createAccount: 'Create an account',
  supportCopied: 'Customer service: support@partner-hub.local',
  supportTitle: 'Customer Service',
  supportHeading: 'Online customer service',
  supportContactUs: 'Contact us',
  supportDesc: 'Password reset, deposit channels and withdrawal payments are handled manually by customer service.',
  supportInputPlaceholder: 'Type a message...',
  supportManualTip: 'Customer service will handle this manually. Please leave your account phone number and request.',
  supportSentTip: 'Message sent. Customer service will reply as soon as possible.',
  supportEmail: 'Email',
  supportTelegram: 'Telegram',
  supportNoticeTitle: 'Service Notice',
  supportNotice: 'For account password recovery, payment instructions, task issues or withdrawal review, please contact support and provide your registered phone number.',
  copy: 'Copy',
  copied: 'Copied',
  welcomeBack: 'Welcome back',
  loginSubtitle: 'Log in to view daily tasks and account data',
  loginTopTitle: 'LOG IN',
  loginPageTitle: 'Please log in to your account',
  phone: 'Mobile phone number',
  phoneRequired: 'Mobile phone number*',
  phonePlaceholder: 'Enter mobile phone number',
  usOnly: 'Only US+1 phone numbers are allowed',
  password: 'Login password',
  passwordInputRequired: 'Enter password*',
  passwordRequired: 'Login password',
  passwordPlaceholder: 'Enter password',
  forgotPassword: 'Forgot Password?',
  forgotPasswordTip: 'Please contact customer service to reset your password',
  loggingIn: 'Logging in...',
  loginSuccess: 'Login successful',
  loginFailed: 'Login failed',
  phonePasswordRequired: 'Enter US phone number and password',
  registerTopTitle: 'CREATE ACCOUNT',
  registerTitle: 'Create Account',
  registerSubtitle: 'Register with an invitation code to enter the workspace',
  passwordCreatePlaceholder: 'Please enter your password',
  confirmPassword: 'Confirm password*',
  confirmPasswordPlaceholder: 'Please enter Confirm password',
  withdrawalPassword: 'Withdrawal Password',
  withdrawalPasswordPlaceholder: 'Please enter your password',
  inviteCode: 'Invitation Code',
  inviteCodePlaceholder: 'Please enter Invitation Code',
  agreement: 'Registration Agreement',
  agreementRequired: 'Please agree to the Registration Agreement',
  agreementEmpty: 'There is no registration agreement.',
  registering: 'Registering...',
  registerSubmit: 'Confirm Registration',
  registerRequired: 'Complete all required registration information',
  inviteCodeRequired: 'The invitation code cannot be empty',
  passwordMismatch: 'Passwords do not match',
  registerSuccess: 'Registration successful',
  registerFailed: 'Registration failed',
  tasksEyebrow: 'Daily Orders',
  tasksTitle: 'Task Center',
  tasksSubtitle: 'Commissions are automatically added to your account balance after completion',
  todayIncome: 'Today income',
  availableBalance: 'Available balance',
  completed: 'Completed',
  pending: 'Pending',
  totalTasks: 'Total tasks',
  taskNoticeTitle: 'Task instructions',
  taskNoticeReady: 'Normal task goods are matched from active goods within the account balance, with 1% commission. Package tasks above the balance are configured manually by staff.',
  taskNoticeNeedBalance: 'Account balance must reach the configured minimum before starting tasks. Contact customer service for manual deposit instructions.',
  orders: 'Orders',
  todayTasks: 'Today tasks',
  items: 'items',
  loading: 'Loading...',
  noTasks: 'No tasks today. Please contact the administrator to configure tasks.',
  orderTask: 'Order task',
  orderAmount: 'Order amount',
  income: 'Income',
  completedAt: 'Completed at',
  packageTask: 'Package task',
  normalTask: 'Normal task',
  submitting: 'Submitting...',
  submit: 'Submit',
  loadTasksFailed: 'Failed to load tasks',
  balanceStartTip: 'Account balance must reach the configured minimum before submitting tasks',
  submitFailed: 'Submit failed',
  tasksTopTitle: 'TASKS',
  newMemberActivities: 'New Member Activities',
  dailyOrders: 'Daily Orders',
  commissionRatio: 'Commission Ratio',
  memberBenefits: 'Member Benefits',
  earnedCommissions: 'Earned Commissions',
  frozenAmount: 'Frozen amount',
  completedOrder: 'Completed order',
  startGrabbingOrders: 'Start Grabbing Orders',
  orderDescription: 'Order description',
  eachAccountCanMatch: 'Each account can match',
  ordersPerDay: 'orders per day',
  commissionRule: 'Normal order commission is fixed at 1%. Package tasks are configured manually by staff.',
  systemRule: 'The system is based on data matching technology and automatically matches products through cloud computing.',
  supervisionRule: 'Order data transactions are supervised and are legal and compliant.',
  pendingRule: 'Order tasks must be completed before new tasks can be received.',
  matchRule: 'After the platform matches orders for users, the order information is sent to the merchant backend. Please complete the order in time to avoid timeout effects.',
  startOptimization: 'Start Optimization',
  dataNo: 'Data No.',
  orderIncome: 'Order income',
  orderEvaluation: 'Order evaluation',
  allTasksCompleted: 'All tasks have been completed',
}

const sv = { ...en, selectLanguage: 'Valj sprak', welcomeTitle: 'Logga in eller skapa ditt konto', login: 'Logga in', createAccount: 'Skapa konto', registerTitle: 'Skapa konto', registerSubmit: 'Bekrafta registrering', tasksTitle: 'Uppgiftscenter', todayIncome: 'Dagens inkomst', availableBalance: 'Tillgangligt saldo', completed: 'Klara', pending: 'Vantar', totalTasks: 'Totalt', submit: 'Skicka', tasksTopTitle: 'UPPGIFTER', startGrabbingOrders: 'Starta order', orderDescription: 'Orderbeskrivning' }
const nl = { ...en, selectLanguage: 'Taal selecteren', welcomeTitle: 'Log in of maak je account aan', login: 'Inloggen', createAccount: 'Account aanmaken', registerTitle: 'Account aanmaken', registerSubmit: 'Registratie bevestigen', tasksTitle: 'Taakcentrum', todayIncome: 'Inkomen vandaag', availableBalance: 'Beschikbaar saldo', completed: 'Voltooid', pending: 'In afwachting', totalTasks: 'Totaal taken', submit: 'Indienen', tasksTopTitle: 'TAKEN', startGrabbingOrders: 'Start orders', orderDescription: 'Orderbeschrijving' }
const fr = { ...en, selectLanguage: 'Choisir la langue', welcomeTitle: 'Veuillez vous connecter ou creer votre compte', login: 'Connexion', createAccount: 'Creer un compte', registerTitle: 'Creer un compte', registerSubmit: 'Confirmer l inscription', tasksTitle: 'Centre des taches', todayIncome: 'Revenu du jour', availableBalance: 'Solde disponible', completed: 'Termine', pending: 'En attente', totalTasks: 'Total des taches', submit: 'Soumettre', tasksTopTitle: 'TACHES', startGrabbingOrders: 'Demarrer les commandes', orderDescription: 'Description de commande' }
const de = { ...en, selectLanguage: 'Sprache wahlen', welcomeTitle: 'Bitte einloggen oder Konto erstellen', login: 'Einloggen', createAccount: 'Konto erstellen', registerTitle: 'Konto erstellen', registerSubmit: 'Registrierung bestatigen', tasksTitle: 'Aufgabencenter', todayIncome: 'Heutiges Einkommen', availableBalance: 'Verfugbares Guthaben', completed: 'Abgeschlossen', pending: 'Ausstehend', totalTasks: 'Aufgaben gesamt', submit: 'Einreichen', tasksTopTitle: 'AUFGABEN', startGrabbingOrders: 'Bestellungen starten', orderDescription: 'Bestellbeschreibung' }
const pl = { ...en, selectLanguage: 'Wybierz jezyk', welcomeTitle: 'Zaloguj sie lub utworz konto', login: 'Zaloguj', createAccount: 'Utworz konto', registerTitle: 'Utworz konto', registerSubmit: 'Potwierdz rejestracje', tasksTitle: 'Centrum zadan', todayIncome: 'Dzisiejszy dochod', availableBalance: 'Dostepne saldo', completed: 'Ukonczone', pending: 'Oczekujace', totalTasks: 'Lacznie zadan', submit: 'Przeslij', tasksTopTitle: 'ZADANIA', startGrabbingOrders: 'Rozpocznij zamowienia', orderDescription: 'Opis zamowienia' }
const vi = { ...en, selectLanguage: 'Chon ngon ngu', welcomeTitle: 'Vui long dang nhap hoac tao tai khoan', login: 'Dang nhap', createAccount: 'Tao tai khoan', registerTitle: 'Tao tai khoan', registerSubmit: 'Xac nhan dang ky', tasksTitle: 'Trung tam nhiem vu', todayIncome: 'Thu nhap hom nay', availableBalance: 'So du kha dung', completed: 'Da hoan thanh', pending: 'Dang cho', totalTasks: 'Tong nhiem vu', submit: 'Gui', tasksTopTitle: 'NHIEM VU', startGrabbingOrders: 'Bat dau don hang', orderDescription: 'Mo ta don hang' }
const ja = { ...en, selectLanguage: 'Gengo sentaku', welcomeTitle: 'Login matawa account sakusei', login: 'Login', createAccount: 'Account sakusei', registerTitle: 'Account sakusei', registerSubmit: 'Toroku kakunin', tasksTitle: 'Task center', todayIncome: 'Today income', availableBalance: 'Available balance', completed: 'Completed', pending: 'Pending', totalTasks: 'Total tasks', submit: 'Submit' }
const mx = { ...en, selectLanguage: 'Seleccionar idioma', welcomeTitle: 'Inicia sesion o crea tu cuenta', login: 'Iniciar sesion', createAccount: 'Crear una cuenta', registerTitle: 'Crear cuenta', registerSubmit: 'Confirmar registro', tasksTitle: 'Centro de tareas', todayIncome: 'Ingresos de hoy', availableBalance: 'Saldo disponible', completed: 'Completado', pending: 'Pendiente', totalTasks: 'Tareas totales', submit: 'Enviar' }

const dictionary = { en, sv, nl, fr, de, pl, vi, ja, mx }
const aliases = { at: 'de', ch: 'de', sk: 'de', lv: 'en', sl: 'en' }
const currentCode = ref(localStorage.getItem('language_code') || 'en')

export const setLanguage = (item) => {
  currentCode.value = item.code
  localStorage.setItem('language_code', item.code)
  localStorage.setItem('language_label', item.label)
}

export const useI18n = () => {
  const language = computed(() => languages.find((item) => item.code === currentCode.value) || languages[0])
  const t = (key) => {
    const code = aliases[currentCode.value] || currentCode.value
    return dictionary[code]?.[key] || dictionary.en[key] || key
  }

  return { language, languages, setLanguage, t }
}
