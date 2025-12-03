// https://nuxt.com/docs/api/configuration/nuxt-config
import Aura from '@primeuix/themes/aura';
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  
  app: {
    head: {
      htmlAttrs: {
        lang: 'ar',
      },
      charset: 'utf-8',
      viewport: 'width=device-width, initial-scale=1',
      style: [
        {
          innerHTML: `
            :root {
              --site-primary-color: #450924;
              --site-secondary-color: #ffffff;
              --site-button-color: #3D0821;
            }
          `,
          id: 'site-colors-initial'
        },
      ]
    }
  },

  alias: {
    '@assets': '/<rootDir>/assets', 
    '@img': '/<rootDir>/assets/images',
    '@icons': '/<rootDir>/assets/icons',
    '@flags': '/<rootDir>/assets/flags',
  },

  css: [
    'primeicons/primeicons.css',
    '~~/app/assets/css/fonts.css',
    '~~/app/assets/css/site-colors.css',
    '~~/app/assets/css/buttons.css',
    '~~/app/assets/css/wall.css',
    '~~/app/assets/css/primevue-custom.css'
  ],

  modules: [
    '@pinia/nuxt',
    '@nuxtjs/tailwindcss',
    '@primevue/nuxt-module',
    '@nuxt/icon'
  ],
  
  primevue: {
    autoImport: true,
    options: {
      theme: {
        preset: Aura,
        options: {
          darkModeSelector: false || 'none'
        }
      },
     
    }
  },
  
  runtimeConfig: {
    public: {
      apiBaseUrl: import.meta.env.NUXT_PUBLIC_API_BASE_URL || 'http://localhost:8000/api',
      reverbAppKey: import.meta.env.NUXT_PUBLIC_REVERB_APP_KEY || 'jof1w5m289qypyzfnsl5',
      reverbHost: import.meta.env.NUXT_PUBLIC_REVERB_HOST || 'localhost',
      reverbPort: import.meta.env.NUXT_PUBLIC_REVERB_PORT || '6001',
      reverbScheme: import.meta.env.NUXT_PUBLIC_REVERB_SCHEME || 'http',
      reverbCluster: import.meta.env.NUXT_PUBLIC_REVERB_CLUSTER || 'mt1',
      obfuscationKey: import.meta.env.NUXT_PUBLIC_OBFUSCATION_KEY || 'CHAT_OBFUSCATION_KEY_2025',
    }
  },
})
