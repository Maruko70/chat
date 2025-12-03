import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

export const useEcho = () => {
  const config = useRuntimeConfig()
  const authStore = useAuthStore()

  let echoInstance: Echo<any> | null = null

  const initEcho = () => {
    if (import.meta.client && !echoInstance) {
      // @ts-ignore
      window.Pusher = Pusher

      const token = authStore.token
      if (!token) {
        console.warn('No auth token available for Echo initialization')
        return null
      }

      echoInstance = new Echo({
        broadcaster: 'pusher',
        key: String(config.public.reverbAppKey || ''),
        wsHost: String(config.public.reverbHost || '158.69.209.151'),
        wsPort: parseInt(String(config.public.reverbPort || '6001')) || 6001,
        wssPort: parseInt(String(config.public.reverbPort || '6001')) || 6001,
        cluster: String(config.public.reverbCluster || 'mt1'),
        forceTLS: String(config.public.reverbScheme || 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${config.public.apiBaseUrl}/broadcasting/auth`,
        auth: {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        },
      })
    }

    return echoInstance
  }

  const getEcho = () => {
    if (!echoInstance) {
      return initEcho()
    }
    return echoInstance
  }

  const disconnect = () => {
    if (echoInstance) {
      echoInstance.disconnect()
      echoInstance = null
    }
  }

  return {
    initEcho,
    getEcho,
    disconnect,
  }
}

