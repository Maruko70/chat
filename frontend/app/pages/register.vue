<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <Card class="w-full max-w-md">
      <template #title>Register</template>
      <template #content>
        <form @submit.prevent="handleRegister" class="space-y-4">
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
              Name
            </label>
            <InputText
              id="name"
              v-model="form.name"
              type="text"
              class="w-full"
              placeholder="Your Name"
              required
            />
          </div>
          
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
          
          <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
              Confirm Password
            </label>
            <InputText
              id="password_confirmation"
              v-model="form.password_confirmation"
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
            label="Register"
          />
        </form>
        
        <div class="mt-4 text-center text-sm">
          <NuxtLink to="/login" class="text-blue-600 hover:underline">
            Already have an account? Login
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
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const loading = ref(false)
const error = ref('')

const handleRegister = async () => {
  if (form.password !== form.password_confirmation) {
    error.value = 'Passwords do not match'
    return
  }
  
  loading.value = true
  error.value = ''
  
  try {
    await authStore.register(
      form.name,
      form.email,
      form.password,
      form.password_confirmation
    )
    await router.push('/chat')
  } catch (err: any) {
    error.value = err.message || 'Registration failed. Please try again.'
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

