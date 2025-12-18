import { boot } from 'quasar/wrappers'
import axios from 'axios'

export default boot(({ app }) => {
  const api = axios.create({
    baseURL: process.env.API_BASE_URL || 'http://localhost:8080',
  })

  app.config.globalProperties.$api = api
})

export { axios }
