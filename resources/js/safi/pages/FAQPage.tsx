import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, HelpCircle } from 'lucide-react';
import { Container } from '../components/ui/Container';
import { Button } from '../components/ui/Button';
import { faq } from '../data/faq';

export default function FAQPage() {
  const { t } = useTranslation();
  const [openItem, setOpenItem] = useState('0-0');

  return (
    <div className="bg-safi-bg text-safi-green">
      <section className="border-b border-safi-border bg-safi-bg py-16 md:py-24">
        <Container>
          <div className="mx-auto max-w-4xl text-center">
            <span className="safi-kicker">FAQ</span>
            <h1 className="mt-4 font-serif text-5xl font-semibold leading-[1.04] text-safi-green md:text-7xl">
              Часто задаваемые{' '}
              <span className="italic text-safi-gold">{t('faq.title2', 'вопросы')}</span>
            </h1>
            <p className="mx-auto mt-7 max-w-2xl text-base leading-8 text-safi-muted md:text-xl">
              Ответы на главные вопросы о компании, продукции, партнерстве, бонусах и выплатах.
            </p>
          </div>
        </Container>
      </section>

      <section className="bg-white py-16 md:py-24">
        <Container>
          <div className="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[0.32fr_0.68fr]">
            <aside className="rounded-3xl border border-safi-border bg-safi-cream p-7 lg:sticky lg:top-28 lg:self-start">
              <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-safi-green">
                <HelpCircle className="h-5 w-5" />
              </div>
              <h2 className="font-serif text-3xl font-semibold text-safi-green">Разделы</h2>
              <div className="mt-6 space-y-3">
                {faq.map((category, index) => (
                  <a
                    key={category.category}
                    href={`#faq-${index}`}
                    className="block rounded-full border border-safi-border bg-white px-4 py-3 text-[10px] font-extrabold uppercase tracking-[0.14em] text-safi-muted transition-colors hover:border-safi-green hover:text-safi-green"
                  >
                    {category.category}
                  </a>
                ))}
              </div>
            </aside>

            <div className="space-y-10">
              {faq.map((category, categoryIndex) => (
                <section key={category.category} id={`faq-${categoryIndex}`} className="scroll-mt-28">
                  <h2 className="mb-5 font-serif text-3xl font-semibold text-safi-green">{category.category}</h2>
                  <div className="space-y-4">
                    {category.questions.map((question, questionIndex) => {
                      const itemKey = `${categoryIndex}-${questionIndex}`;
                      const isOpen = openItem === itemKey;

                      return (
                        <article key={question.q} className="overflow-hidden rounded-3xl border border-safi-border bg-safi-bg">
                          <button
                            type="button"
                            onClick={() => setOpenItem(isOpen ? '' : itemKey)}
                            className="flex w-full items-center justify-between gap-5 p-6 text-left transition-colors hover:bg-safi-cream md:p-7"
                            aria-expanded={isOpen}
                          >
                            <span className="font-serif text-xl font-semibold leading-snug text-safi-green md:text-2xl">{question.q}</span>
                            <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-safi-green transition-transform ${isOpen ? 'rotate-180' : ''}`}>
                              <ChevronDown className="h-5 w-5" />
                            </span>
                          </button>
                          {isOpen && (
                            <div className="border-t border-safi-border bg-white px-6 py-6 text-sm leading-7 text-safi-muted md:px-7">
                              {question.a}
                            </div>
                          )}
                        </article>
                      );
                    })}
                  </div>
                </section>
              ))}
            </div>
          </div>
        </Container>
      </section>

      <section className="border-t border-safi-border bg-safi-green py-14 text-white">
        <Container>
          <div className="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
              <span className="text-[10px] font-extrabold uppercase tracking-[0.18em] text-safi-gold">Safi Life</span>
              <h2 className="mt-3 font-serif text-4xl font-semibold">Не нашли ответ?</h2>
              <p className="mt-3 max-w-2xl text-sm leading-7 text-white/70">
                Напишите нам, и консультант поможет с вопросом по продуктам, пакетам или регистрации.
              </p>
            </div>
            <Button to="/contacts" size="lg" variant="secondary">
              Связаться
            </Button>
          </div>
        </Container>
      </section>
    </div>
  );
}
