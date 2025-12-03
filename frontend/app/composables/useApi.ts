import { deobfuscateResponse, isObfuscated, setObfuscationKey } from '../utils/responseDeobfuscator'

export const useApi = () => {
  const config = useRuntimeConfig()
  
  // Set the obfuscation key from runtime config (available in composables)
  if (config.public?.obfuscationKey) {
    setObfuscationKey(config.public.obfuscationKey)
  }

  const api = async (endpoint: string, options: RequestInit = {}) => {
    const baseUrl = config.public.apiBaseUrl
    const url = endpoint.startsWith('http') ? endpoint : `${baseUrl}${endpoint}`
    
    const isFormData = options.body instanceof FormData
    
    const headers: HeadersInit = {
      'Accept': 'application/json',
      ...options.headers,
    }

    // Don't set Content-Type for FormData, let browser set it with boundary
    if (!isFormData) {
      headers['Content-Type'] = 'application/json'
    }

    // Add auth token if available (safely access store only when needed)
    try {
      const authStore = useAuthStore()
      if (authStore && authStore.token) {
        headers['Authorization'] = `Bearer ${authStore.token}`
      }
    } catch (error) {
      // Pinia not initialized yet, continue without auth token
      // This is fine for public endpoints like site-settings
      console.warn('Auth store not available, request will be made without token')
    }

    // Convert body to JSON if it's an object (but not FormData)
    let body = options.body
    if (body && typeof body === 'object' && !isFormData) {
      body = JSON.stringify(body)
    }

    const response = await fetch(url, {
      ...options,
      headers,
      body,
    })

    // Get response text first (might be obfuscated)
    const responseText = await response.text()
    
    // Check if response is obfuscated (either by header or content format)
    const hasEncryptedHeader = response.headers.get('X-Response-Encrypted') === '1'
    const looksObfuscated = responseText && isObfuscated(responseText)
    const isEncrypted = hasEncryptedHeader || looksObfuscated
    
    if (!response.ok) {
      // Try to deobfuscate error response
      let errorData: any
      try {
        if (isEncrypted) {
          errorData = await deobfuscateResponse(responseText)
        } else {
          errorData = JSON.parse(responseText)
        }
      } catch {
        errorData = { message: 'An error occurred' }
      }
      
      const error: any = new Error(errorData.message || `HTTP error! status: ${response.status}`)
      // Preserve full error response data
      error.data = errorData
      error.status = response.status
      
      // Handle ban responses - logout user and redirect
      if (response.status === 403 && errorData.banned) {
        // User is banned, logout and redirect
        if (import.meta.client) {
          try {
            const authStore = useAuthStore()
            if (authStore) {
              authStore.clearAuth()
            }
            
            // Redirect to login
            const router = useRouter()
            router.push('/login').then(() => {
              // Show ban message after navigation
              nextTick(() => {
                import('primevue/usetoast').then(({ useToast }) => {
                  const toast = useToast()
                  toast.add({
                    severity: 'error',
                    summary: 'تم حظر حسابك',
                    detail: errorData.message || 'تم حظر حسابك من الموقع',
                    life: 10000,
                  })
                })
              })
            })
          } catch (e) {
            console.error('Error handling ban response:', e)
          }
        }
      }
      
      throw error
    }

    // Deobfuscate successful response if needed
    if (isEncrypted) {
      try {
        const deobfuscated = await deobfuscateResponse(responseText)
        return deobfuscated
      } catch (error) {
        // If deobfuscation fails, log error

        // Check if response format suggests it's actually obfuscated
        const definitelyObfuscated = responseText.includes('|') && responseText.length > 24
        
        if (definitelyObfuscated) {
          // Try to parse as plain JSON anyway (might work if key is close)
          try {
            const parsed = JSON.parse(responseText)
            return parsed
          } catch (parseError) {
            // If that also fails, throw a clear error
            throw new Error(`Failed to deobfuscate response. Keys may not match between frontend and backend. Original error: ${(error as Error).message}`)
          }
        } else {
          // Response might not be obfuscated, try to parse as plain JSON
          try {
            const parsed = JSON.parse(responseText)
            return parsed
          } catch (parseError) {
            throw new Error(`Failed to deobfuscate or parse response: ${(error as Error).message}`)
          }
        }
      }
    }
    
    // Try to parse as JSON (fallback for non-obfuscated responses)
    try {
      return JSON.parse(responseText)
    } catch (parseError) {
      return responseText
    }
  }

  return { api }
}

