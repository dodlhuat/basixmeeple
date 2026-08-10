import { Toast, type ToastType } from '@dodlhuat/basix/js/toast.js'
import { ICON_BASE_PATH } from '~/utils/iconSprite'

function show(content: string, type: ToastType, header?: string): void {
  new Toast({ content, header, type, closeable: true, iconBasePath: ICON_BASE_PATH }).show(4000)
}

export function useToast() {
  return {
    success: (content: string, header?: string) => show(content, 'success', header),
    error: (content: string, header?: string) => show(content, 'error', header),
    info: (content: string, header?: string) => show(content, 'info', header),
  }
}
