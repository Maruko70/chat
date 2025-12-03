<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <Card class="w-full max-w-md">
      <template #title>Login</template>
      <template #content>
        <form @submit.prevent="handleLogin" class="space-y-4">
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
              Email
            </label>
            <InputText
              id="email"
              v-model="form.email"
              type="email"
              class="w-full"
              placeholder="your@email.com"
              required
            />
          </div>
          
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
              Password
            </label>
            <InputText
              id="password"
              v-model="form.password"
              type="password"
              class="w-full"
              placeholder="••••••••"
              required
            />
          </div>
          
          <div v-if="error" class="text-red-600 text-sm">
            {{ error }}
          </div>
          
          <Button
            type="submit"
            :loading="loading"
            class="w-full"
            label="Login"
          />
        </form>
        
        <div class="mt-4 text-center text-sm">
          <NuxtLink to="/register" class="text-blue-600 hover:underline">
            Don't have an account? Register
          </NuxtLink>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  layout: false,
})

const authStore = useAuthStore()
const router = useRouter()

const form = reactive({
  email: '',
  password: '',
})

const loading = ref(false)
const error = ref('')

const handleLogin = async () => {
  loading.value = true
  error.value = ''
  
  try {
    await authStore.login(form.email, form.password)
    await router.push('/chat')
  } catch (err: any) {
    error.value = err.message || 'Login failed. Please check your credentials.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (authStore.isAuthenticated) {
    router.push('/chat')
  }
})
</script>

