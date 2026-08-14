<script setup lang="ts">
import { ApiError } from '~/utils/apiClient'

definePageMeta({ layout: 'auth' })
useHead({ title: 'Registrieren – BasixMeeple' })

const route = useRoute()
const { register } = useAuth()

const token = ref(typeof route.query.token === 'string' ? route.query.token : '')
const name = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const error = ref<string | null>(null)
const submitting = ref(false)

// Purely presentational: shows a small "übernommen" confirmation on the
// token field when it arrived via the invite link's ?token= query param,
// instead of leaving that prefill silently invisible. Doesn't affect the
// field's v-model, required-ness, or editability — the invite flow is
// untouched.
const tokenPrefilled = computed(() => typeof route.query.token === 'string' && route.query.token.length > 0)

async function onSubmit() {
  error.value = null
  submitting.value = true

  try {
    await register({
      token: token.value,
      name: name.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    await navigateTo('/collections')
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Registrierung fehlgeschlagen.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form class="card auth-card" @submit.prevent="onSubmit">
    <div class="auth-card-intro">
      <span class="auth-icon-badge">
        <svg class="icon-svg"><use :href="iconHref('badge')" /></svg>
      </span>
      <div class="auth-card-intro-text">
        <h1>Registrieren</h1>
        <p class="text-secondary">
          Du wurdest zu einer Sammlung eingeladen. Vervollständige dein Konto, um beizutreten.
        </p>
      </div>
    </div>

    <div class="form-group" :class="{ error: !!error, success: tokenPrefilled && !error }">
      <label for="token">Einladungscode</label>
      <input id="token" v-model="token" type="text" required />
      <p v-if="tokenPrefilled && !error" class="form-hint">Aus deinem Einladungslink übernommen.</p>
    </div>

    <div class="form-group" :class="{ error: !!error }">
      <label for="name">Name</label>
      <input id="name" v-model="name" type="text" autocomplete="name" required />
    </div>

    <div class="form-group" :class="{ error: !!error }">
      <label for="password">Passwort</label>
      <input id="password" v-model="password" type="password" autocomplete="new-password" required />
    </div>

    <div class="form-group" :class="{ error: !!error }">
      <label for="password-confirmation">Passwort bestätigen</label>
      <input
        id="password-confirmation"
        v-model="passwordConfirmation"
        type="password"
        autocomplete="new-password"
        required
      />
      <p v-if="error" class="form-hint">{{ error }}</p>
    </div>

    <button
      type="submit"
      class="button button-primary button-lg auth-submit"
      :class="{ 'is-loading': submitting }"
      :disabled="submitting"
    >
      {{ submitting ? 'Registrieren …' : 'Konto erstellen' }}
    </button>

    <NuxtLink to="/login" class="auth-alt-link">
      <svg class="icon-svg"><use :href="iconHref('login')" /></svg>
      Schon registriert? Anmelden
    </NuxtLink>
  </form>
</template>

<style lang="scss" scoped>
.auth-card {
  width: 100%;
  max-width: 400px;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  padding: 2rem 1.75rem;
  border-radius: 1.5rem;
  box-shadow:
    0 20px 48px -20px rgba(0, 0, 0, 0.28),
    0 2px 8px rgba(0, 0, 0, 0.06);

  // One composed "the card just arrived" moment — matching the stats
  // dashboard's stagger language, kept restrained to the card's top-level
  // groups rather than every individual label/input.
  > * {
    animation: auth-rise-in 0.45s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
  }

  @for $i from 1 through 8 {
    > *:nth-child(#{$i}) {
      animation-delay: #{50 * ($i - 1)}ms;
    }
  }
}

@keyframes auth-rise-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.auth-card-intro {
  display: flex;
  align-items: flex-start;
  gap: 0.875rem;
}

.auth-card-intro-text {
  min-width: 0;

  h1 {
    margin: 0 0 0.2rem;
    font-size: 1.625rem;
  }

  p {
    margin: 0;
  }
}

.auth-icon-badge {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 50%;
  background: var(--accent-color-tint);
  color: var(--accent-color);
  margin-top: 0.1rem;
}

.auth-submit {
  width: 100%;
  margin-top: 0.25rem;
}

.auth-alt-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  align-self: center;
  color: var(--secondary-text);
  font-size: 0.875rem;
  font-weight: 500;

  .icon-svg {
    width: 1rem;
    height: 1rem;
  }

  &:hover,
  &:focus-visible {
    color: var(--accent-color);
  }
}

@media (prefers-reduced-motion: reduce) {
  .auth-card > * {
    animation: none;
  }
}
</style>
