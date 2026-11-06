<?php

$_['heading_title'] = 'Payop Payment Gateway';

$_['text_payop'] = '<a href="https://payop.com" target="_blank"><img src="/extension/payop/admin/view/image/payment/payop.png" style="max-width:130px; height:auto;"></a>';
$_['text_extension'] = 'Расширения';
$_['text_success'] = 'Настройки успешно сохранены!';
$_['text_edit'] = 'Редактирование Payop Payment Gateway';

$_['entry_public_id'] = 'Публичный ключ';
$_['entry_secret_key'] = 'Секретный ключ';
$_['entry_order_status_wait'] = 'Статус заказа при ожидании оплаты';
$_['entry_order_status_success'] = 'Статус заказа при успешной оплате';
$_['entry_order_status_error'] = 'Статус заказа при неудачной оплате';
$_['entry_status'] = 'Статус';
$_['entry_sort_order'] = 'Порядок сортировки';
$_['entry_ipn_url'] = 'IPN URL';

$_['help_public_id'] = 'Этот параметр можно найти в настройках проекта в личном кабинете Payop.';
$_['help_secret_key'] = 'Этот параметр можно найти в настройках проекта в личном кабинете Payop.';
$_['help_ipn_url'] = 'Скопируйте этот URL и добавьте его в настройках IPN на сайте Payop.';

$_['error_permission'] = 'У вас нет прав для управления этим модулем!';
$_['error_public_id'] = 'Поле обязательно для заполнения.';
$_['error_secret_key'] = 'Поле обязательно для заполнения.';

$_['entry_project_id'] = 'ID проекта';
$_['entry_jwt_token'] = 'JWT Token';
$_['help_project_id'] = 'Укажите ID проекта из кабинета Payop. Он может отличаться от Public Key. Нужен только для загрузки методов.';
$_['help_jwt_token'] = 'Используется только для загрузки методов проекта. Обычный Hosted Page и проверка invoice работают без JWT.';
$_['help_payment_buttons'] = 'Основной Payop Hosted Page остаётся доступным. Добавляйте дополнительные варианты без ограничения количества. Для включённых вариантов заполните название на каждом языке магазина. Описание необязательно. Перед выбором нового Payment Method ID загрузите методы.';
$_['entry_button_name'] = 'Название для покупателя';
$_['entry_button_description'] = 'Описание для покупателя';
$_['entry_integration_type'] = 'Тип интеграции';
$_['entry_payment_method'] = 'Платёжный метод';
$_['text_payment_buttons'] = 'Дополнительные варианты оплаты';
$_['text_hosted_page'] = 'Hosted Page';
$_['text_payment_method'] = 'Hosted Page with Payment Method ID';
$_['text_saved_method'] = 'Сохранённый ID метода:';
$_['text_select_method'] = 'Выберите платёжный метод';
$_['text_methods_loaded'] = 'Методы загружены. Текущие выбранные значения сохранены.';
$_['text_buttons_javascript'] = 'Для редактирования вариантов нужен JavaScript. При сохранении других настроек существующие варианты сохраняются.';
$_['button_load_methods'] = 'Загрузить платёжные методы';
$_['button_add'] = 'Добавить вариант';
$_['button_remove'] = 'Удалить вариант';
$_['error_buttons'] = 'Некорректные данные вариантов оплаты. Настройки не сохранены.';
$_['error_button_method'] = 'Для каждого варианта с Payment Method ID выберите метод, доступный этому проекту.';
$_['error_button_name'] = 'Заполните название на каждом языке магазина для всех включённых вариантов.';
$_['error_methods_credentials'] = 'Укажите ID проекта и JWT Token для загрузки методов. Существующие варианты сохранены.';
$_['error_methods_auth'] = 'Payop отклонил JWT Token или доступ к проекту. Проверьте токен и ID проекта. Существующие варианты сохранены.';
$_['error_methods_network'] = 'Не удалось подключиться к Payop. Повторите загрузку методов. Существующие варианты сохранены.';
$_['error_methods_response'] = 'Payop не вернул список методов. Проверьте ID проекта и JWT Token и повторите попытку. Существующие варианты сохранены.';

$_['entry_revolut_fields'] = 'Запрашивать дополнительные данные плательщика Revolut';
