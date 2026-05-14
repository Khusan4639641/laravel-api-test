import React from 'react';
import { MessageCircle, Phone, Send } from 'lucide-react';

export function FloatingContactButtons() {
  return (
    <div className="fixed bottom-5 right-5 z-50 flex flex-col gap-2.5 md:bottom-6 md:right-6">
      <a
        href="#"
        className="flex h-12 w-12 items-center justify-center rounded-full border border-safi-border bg-white text-[#1FA855] shadow-[0_16px_36px_rgba(11,23,18,0.12)] transition-transform hover:-translate-y-0.5"
        aria-label="WhatsApp"
      >
        <MessageCircle className="h-6 w-6" />
      </a>
      <a
        href="#"
        className="flex h-12 w-12 items-center justify-center rounded-full border border-safi-border bg-white text-[#2688C7] shadow-[0_16px_36px_rgba(11,23,18,0.12)] transition-transform hover:-translate-y-0.5"
        aria-label="Telegram"
      >
        <Send className="h-5 w-5" />
      </a>
      <a
        href="tel:+77000000000"
        className="flex h-12 w-12 items-center justify-center rounded-full border border-safi-green bg-safi-green text-white shadow-[0_16px_36px_rgba(11,23,18,0.18)] transition-transform hover:-translate-y-0.5 hover:bg-safi-green-hover"
        aria-label="Телефон"
      >
        <Phone className="h-5 w-5" />
      </a>
    </div>
  );
}
