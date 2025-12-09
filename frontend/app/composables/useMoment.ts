/**
 * Lazy-loaded moment.js composable
 * Only loads moment when actually needed
 */

let momentInstance: any = null
let momentLoading = false

export const useMoment = () => {
  const loadMoment = async () => {
    if (momentInstance) {
      return momentInstance
    }
    
    if (momentLoading) {
      // Wait for ongoing load
      while (momentLoading) {
        await new Promise(resolve => setTimeout(resolve, 50))
      }
      return momentInstance
    }
    
    momentLoading = true
    try {
      const momentModule = await import('moment')
      momentInstance = momentModule.default
      return momentInstance
    } finally {
      momentLoading = false
    }
  }
  
  return {
    moment: () => loadMoment(),
    // Helper function that uses moment once loaded
    formatDate: async (date: string | Date, format: string) => {
      const moment = await loadMoment()
      return moment(date).format(format)
    },
    fromNow: async (date: string | Date) => {
      const moment = await loadMoment()
      return moment(date).fromNow()
    },
  }
}

