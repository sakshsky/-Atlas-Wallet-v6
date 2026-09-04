import { createApp } from 'vue'
import axios from 'axios'
import App from './App.vue'

axios.defaults.withCredentials = true
axios.defaults.withXSRFToken = true
axios.defaults.headers.common.Accept = 'application/json'
axios.interceptors.response.use(response => response, error => { if (error.response?.status === 503) window.dispatchEvent(new CustomEvent('atlas-maintenance')); return Promise.reject(error) })

createApp(App).mount('#app')
