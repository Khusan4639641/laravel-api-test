# TipTop Pay Site Requirements Checklist

Внутренний checklist готовности сайта Safi Life перед отправкой на проверку TipTop Pay.

## Public Requirements

- [ ] Информация об онлайн-оплате размещена: `/payment`
- [ ] Виджет TipTop Pay интегрирован
- [ ] Контакты для спорных ситуаций есть: `/contacts`
- [ ] Договор оферты под Казахстан есть: `/legal/offer`
- [ ] Цены отображаются в тенге
- [ ] Политика конфиденциальности есть: `/legal/privacy`
- [ ] Реквизиты и информация о ЮЛ есть: `/legal/requisites`
- [ ] Доставка описана: `/legal/delivery`
- [ ] Возврат описан: `/legal/refund`

## Production Checks

- [ ] HTTPS работает на production
- [ ] Товары имеют реальные фото, описание, цену, остаток
- [ ] Checkout требует телефон и адрес доставки
- [ ] Admin orders показывают телефон и адрес доставки
- [ ] Payment success/fail pages работают
- [ ] `.env` содержит `TIPTOPPAY_PUBLIC_TERMINAL_ID`
- [ ] Webhook routes работают
- [ ] Test payment проверен

## Internal Readiness Page

Скрытая страница проверки доступна только `super_admin`:

`/admin/payment-readiness`

Перед отправкой сайта на проверку TipTop Pay нужно открыть страницу на production и пройти все пункты вручную после заполнения реальных реквизитов и платежных ключей.
