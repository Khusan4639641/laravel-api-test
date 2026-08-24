import { TipTopPayPaymentIntent } from './api';

const TIPTOPPAY_WIDGET_SRC = 'https://widget.tiptoppay.kz/bundles/widget.js';
let widgetScriptPromise: Promise<void> | null = null;

export interface TipTopWidgetInstance {
  start: (params: TipTopPayPaymentIntent) => Promise<unknown> | unknown;
  oncomplete?: (result: unknown) => void;
}

declare global {
  interface Window {
    tiptop?: {
      Widget: new () => TipTopWidgetInstance;
    };
  }
}

export function loadTipTopPayWidget() {
  if (typeof window === 'undefined') {
    return Promise.reject(new Error('Платежный виджет доступен только в браузере.'));
  }

  if (window.tiptop?.Widget) {
    return Promise.resolve();
  }

  if (widgetScriptPromise) {
    return widgetScriptPromise;
  }

  widgetScriptPromise = new Promise<void>((resolve, reject) => {
    const existingScript = document.querySelector<HTMLScriptElement>(`script[src="${TIPTOPPAY_WIDGET_SRC}"]`);

    if (existingScript) {
      if (window.tiptop?.Widget || existingScript.dataset.loaded === 'true') {
        resolve();
        return;
      }

      existingScript.addEventListener('load', () => resolve(), { once: true });
      existingScript.addEventListener('error', () => reject(new Error('Не удалось загрузить платежный виджет. Попробуйте позже.')), { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = TIPTOPPAY_WIDGET_SRC;
    script.async = true;
    script.onload = () => {
      script.dataset.loaded = 'true';
      resolve();
    };
    script.onerror = () => reject(new Error('Не удалось загрузить платежный виджет. Попробуйте позже.'));
    document.head.appendChild(script);
  });

  return widgetScriptPromise;
}

export async function startTipTopPayment(intent: TipTopPayPaymentIntent) {
  await loadTipTopPayWidget();

  if (!window.tiptop?.Widget) {
    throw new Error('Не удалось загрузить платежный виджет. Попробуйте позже.');
  }

  const widget = new window.tiptop.Widget();
  return widget.start(intent);
}
