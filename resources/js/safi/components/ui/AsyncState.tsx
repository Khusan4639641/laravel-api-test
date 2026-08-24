import React from 'react';
import { AlertCircle, LoaderCircle, PackageOpen, RefreshCw } from 'lucide-react';
import { cn } from '../../lib/utils';
import { Button } from './Button';

interface StatePanelProps {
  title: string;
  description?: string;
  icon: React.ElementType;
  className?: string;
  children?: React.ReactNode;
}

function StatePanel({ title, description, icon: Icon, className, children }: StatePanelProps) {
  return (
    <div
      className={cn(
        'flex min-h-[220px] flex-col items-center justify-center rounded-3xl border border-safi-border bg-white p-8 text-center shadow-[0_18px_48px_rgba(11,23,18,0.05)]',
        className
      )}
    >
      <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-safi-cream text-safi-green">
        <Icon className="h-5 w-5" />
      </div>
      <h3 className="font-serif text-2xl font-semibold text-safi-green">{title}</h3>
      {description && <p className="mt-3 max-w-xl text-sm leading-7 text-safi-muted">{description}</p>}
      {children && <div className="mt-6">{children}</div>}
    </div>
  );
}

export function LoadingState({
  title = 'Загружаем данные',
  description = 'Подождите, идет загрузка актуальной информации.',
  className,
}: {
  title?: string;
  description?: string;
  className?: string;
}) {
  return (
    <StatePanel
      title={title}
      description={description}
      icon={LoaderCircle}
      className={cn('[&>div:first-child>svg]:animate-spin', className)}
    />
  );
}

export function ErrorState({
  title = 'Не удалось загрузить данные',
  description,
  onRetry,
  className,
}: {
  title?: string;
  description?: string | null;
  onRetry?: () => void;
  className?: string;
}) {
  return (
    <StatePanel
      title={title}
      description={description || 'Проверьте соединение и попробуйте снова.'}
      icon={AlertCircle}
      className={className}
    >
      {onRetry && (
        <Button type="button" variant="outline" onClick={onRetry}>
          <RefreshCw className="h-4 w-4" />
          Повторить
        </Button>
      )}
    </StatePanel>
  );
}

export function EmptyState({
  title = 'Данные пока не добавлены',
  description = 'Раздел появится здесь после публикации данных.',
  className,
}: {
  title?: string;
  description?: string;
  className?: string;
}) {
  return <StatePanel title={title} description={description} icon={PackageOpen} className={className} />;
}
