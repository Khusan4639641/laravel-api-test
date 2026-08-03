import { useEffect, useRef, useState } from 'react';
import { Loader2, Search, X } from 'lucide-react';
import { searchTransferPartners, TransferPartner } from '../lib/api';
import { NoTranslate } from './ui/NoTranslate';
import { useUiText } from '../i18n/useUiText';

interface AsyncPartnerSelectProps {
  value: TransferPartner | null;
  onChange: (partner: TransferPartner | null) => void;
  disabled?: boolean;
}

export default function AsyncPartnerSelect({ value, onChange, disabled = false }: AsyncPartnerSelectProps) {
  const ui = useUiText();
  const [query, setQuery] = useState(value ? partnerLabel(value) : '');
  const [options, setOptions] = useState<TransferPartner[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const rootRef = useRef<HTMLDivElement | null>(null);
  const selectedIdRef = useRef<number | null>(value?.id ?? null);

  useEffect(() => {
    if (value) {
      selectedIdRef.current = value.id;
      setQuery(partnerLabel(value));
      return;
    }

    if (selectedIdRef.current !== null) {
      selectedIdRef.current = null;
      setQuery('');
    }
  }, [value]);

  useEffect(() => {
    const onPointerDown = (event: PointerEvent) => {
      if (rootRef.current && !rootRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('pointerdown', onPointerDown);

    return () => document.removeEventListener('pointerdown', onPointerDown);
  }, []);

  useEffect(() => {
    const trimmedQuery = query.trim();

    if (value && trimmedQuery === partnerLabel(value)) {
      return;
    }

    if (trimmedQuery.length < 2) {
      setOptions([]);
      setIsLoading(false);
      setError('');
      return;
    }

    let cancelled = false;
    setIsLoading(true);
    setError('');

    const timeout = window.setTimeout(async () => {
      try {
        const partners = await searchTransferPartners(trimmedQuery, 20);

        if (!cancelled) {
          setOptions(partners);
          setIsOpen(true);
        }
      } catch {
        if (!cancelled) {
          setOptions([]);
          setError(ui('Не удалось загрузить партнёров'));
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false);
        }
      }
    }, 300);

    return () => {
      cancelled = true;
      window.clearTimeout(timeout);
    };
  }, [query, ui, value]);

  return (
    <div ref={rootRef} className="relative">
      <div className="flex items-center gap-2 rounded-2xl border border-safi-border bg-safi-cream px-4 py-3 focus-within:border-safi-green focus-within:ring-2 focus-within:ring-safi-gold/25">
        <Search className="h-4 w-4 shrink-0 text-safi-muted" />
        <input
          type="text"
          value={query}
          disabled={disabled}
          placeholder={ui('Поиск по ID, имени, login, email или телефону')}
          translate="no"
          data-notranslate="true"
          onFocus={() => {
            if (query.trim().length >= 2) {
              setIsOpen(true);
            }
          }}
          onChange={(event) => {
            selectedIdRef.current = null;
            setQuery(event.target.value);
            onChange(null);
            setIsOpen(true);
          }}
          className="min-w-0 flex-1 bg-transparent text-sm font-bold text-safi-green outline-none placeholder:text-safi-muted/70 disabled:cursor-not-allowed"
        />
        {isLoading && <Loader2 className="h-4 w-4 animate-spin text-safi-muted" />}
        {value && !disabled && (
          <button
            type="button"
            onClick={() => {
              selectedIdRef.current = null;
              onChange(null);
              setQuery('');
              setOptions([]);
            }}
            className="rounded-full p-1 text-safi-muted transition-colors hover:bg-white hover:text-safi-green"
            aria-label={ui('Очистить получателя')}
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>

      {isOpen && query.trim().length >= 2 && (
        <div className="absolute left-0 right-0 z-30 mt-2 max-h-72 overflow-y-auto rounded-2xl border border-safi-border bg-white p-2 shadow-[0_18px_38px_rgba(11,23,18,0.16)]">
          {error && <div className="px-3 py-3 text-sm font-bold text-red-700">{error}</div>}

          {!error && !isLoading && options.length === 0 && (
            <div className="px-3 py-3 text-sm font-bold text-safi-muted">{ui('Партнёры не найдены')}</div>
          )}

          {!error && options.map((partner) => (
            <button
              key={partner.id}
              type="button"
              onClick={() => {
                selectedIdRef.current = partner.id;
                onChange(partner);
                setQuery(partnerLabel(partner));
                setIsOpen(false);
              }}
              className="w-full rounded-xl px-3 py-3 text-left transition-colors hover:bg-safi-cream"
            >
              <NoTranslate as="span" className="block text-sm font-extrabold text-safi-green">
                #{partner.id} — {partner.name}
              </NoTranslate>
              <NoTranslate as="span" className="mt-1 block text-xs font-bold text-safi-muted">
                {[partner.login && `login: ${partner.login}`, partner.email, partner.phone].filter(Boolean).join(' · ') || 'Партнёр Safi'}
              </NoTranslate>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

function partnerLabel(partner: TransferPartner) {
  return `#${partner.id} — ${partner.name}`;
}
