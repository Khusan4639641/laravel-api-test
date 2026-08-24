<?php

return [
    'required' => 'Поле «:attribute» обязательно.',
    'email' => 'Поле «:attribute» должно содержать корректный email.',
    'string' => 'Поле «:attribute» должно быть строкой.',
    'numeric' => 'Поле «:attribute» должно быть числом.',
    'integer' => 'Поле «:attribute» должно быть целым числом.',
    'min' => ['string' => 'Поле «:attribute» должно содержать не менее :min символов.', 'numeric' => 'Поле «:attribute» должно быть не меньше :min.'],
    'max' => ['string' => 'Поле «:attribute» должно содержать не более :max символов.', 'numeric' => 'Поле «:attribute» должно быть не больше :max.'],
    'confirmed' => 'Подтверждение поля «:attribute» не совпадает.',
    'unique' => 'Такое значение поля «:attribute» уже используется.',
    'exists' => 'Выбранное значение поля «:attribute» некорректно.',
];
