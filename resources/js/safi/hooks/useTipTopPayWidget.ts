import { useCallback, useState } from 'react';
import { TipTopPayPaymentIntent } from '../lib/api';

const TIPTOPPAY_WIDGET_SRC = 'https://widget.tiptoppay.kz/bundles/widget.js';
let widgetScriptPromise: Promise<void> | null = null;

interface TipTopWidgetInstance {
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

export function useTipTopPayWidget() {
  const [isWidgetLoading, setIsWidgetLoading] = useState(false);

  const startPayment = useCallback(async (
    intent: TipTopPayPaymentIntent,
    callbacks: {
      onSuccess?: (result: unknown) => void | Promise<void>;
      onFail?: (error: unknown) => void | Promise<void>;
    } = {},
  ) => {
    setIsWidgetLoading(true);

    try {
      await loadTipTopPayWidgetScript();

      if (!window.tiptop?.Widget) {
        throw new Error('Не удалось загрузить платежный виджет. Попробуйте позже.');
      }

      const widget = new window.tiptop.Widget();
      let callbackCalled = false;
      const resolveSuccess = async (result: unknown) => {
        if (callbackCalled) {
          return;
        }

        callbackCalled = true;
        await callbacks.onSuccess?.(result);
      };

      widget.oncomplete = (result: unknown) => {
        void resolveSuccess(result);
      };

      const result = await widget.start(intent);
      await resolveSuccess(result);

      return result;
    } catch (error) {
      if (callbacks.onFail) {
        await callbacks.onFail(error);
        return undefined;
      }

      throw error;
    } finally {
      setIsWidgetLoading(false);
    }
  }, []);

  return { isWidgetLoading, startPayment };
}

function loadTipTopPayWidgetScript() {
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
