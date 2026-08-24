import { useCallback, useState } from 'react';
import { TipTopPayPaymentIntent } from '../lib/api';
import { loadTipTopPayWidget, startTipTopPayment } from '../lib/tiptoppay';

export { loadTipTopPayWidget, startTipTopPayment };

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
      await loadTipTopPayWidget();

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
