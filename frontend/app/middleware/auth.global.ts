import { getAuthToken } from '~/utils/authToken'

const PUBLIC_ROUTES = new Set(['/login', '/register'])

export default defineNuxtRouteMiddleware((to) => {
  const hasToken = !!getAuthToken()

  if (!hasToken && !PUBLIC_ROUTES.has(to.path)) {
    return navigateTo('/login')
  }

  if (hasToken && PUBLIC_ROUTES.has(to.path)) {
    return navigateTo('/collections')
  }
})
