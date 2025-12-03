export default defineNuxtPlugin((nuxtApp) => {
  const { api } = useApi()
  
  nuxtApp.provide('api', api)
})

