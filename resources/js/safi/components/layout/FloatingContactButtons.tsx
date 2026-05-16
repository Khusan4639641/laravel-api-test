import React from 'react';
import { MessageCircle, Phone, Send } from 'lucide-react';

export function FloatingContactButtons() {
  return (
    <div className="fixed bottom-6 right-6 z-50 flex flex-col gap-3">
      <a
        href="#"
        className="flex h-12 w-12 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg shadow-green-500/30 transition-transform hover:scale-110"
        aria-label="WhatsApp"
      >
        <MessageCircle className="h-6 w-6" />
      </a>
      <a
        href="#"
        className="flex h-12 w-12 items-center justify-center rounded-full bg-[#0088cc] text-white shadow-lg shadow-blue-500/30 transition-transform hover:scale-110"
        aria-label="Telegram"
      >
        <Send className="h-5 w-5" />
      </a>
      <a
        href="tel:+77000000000"
        className="flex h-12 w-12 items-center justify-center rounded-full bg-green-800 text-white shadow-lg shadow-green-900/30 transition-transform hover:scale-110"
        aria-label="Телефон"
      >
        <Phone className="h-5 w-5" />
      </a>
    </div>
  );
}
