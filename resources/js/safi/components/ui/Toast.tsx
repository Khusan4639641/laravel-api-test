import { X } from 'lucide-react';
import { cn } from '../../lib/utils';

export type ToastType = 'success' | 'error' | 'info';

export interface ToastItem {
  id: number;
  message: string;
  type?: ToastType;
}

export function ToastStack({ toasts, onDismiss }: { toasts: ToastItem[]; onDismiss: (id: number) => void }) {
  if (toasts.length === 0) {
    return null;
  }

  return (
    <div className="fixed right-4 top-4 z-[70] flex w-[min(360px,calc(100vw-32px))] flex-col gap-3">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          className={cn(
            'flex items-start gap-3 rounded-2xl border bg-white px-4 py-3 text-sm font-bold shadow-[0_18px_48px_rgba(11,23,18,0.14)]',
            toast.type === 'error' ? 'border-red-200 text-red-700' : toast.type === 'info' ? 'border-safi-border text-safi-green' : 'border-green-200 text-green-700'
          )}
        >
          <div className="flex-1 leading-6">{toast.message}</div>
          <button
            type="button"
            onClick={() => onDismiss(toast.id)}
            className="mt-0.5 flex h-6 w-6 cursor-pointer items-center justify-center rounded-full text-current opacity-70 transition-opacity hover:opacity-100"
            aria-label="Закрыть уведомление"
          >
            <X className="h-4 w-4" />
          </button>
        </div>
      ))}
    </div>
  );
}
