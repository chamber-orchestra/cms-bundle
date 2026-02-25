const ru = {
    'validator.not_valid': 'Это поле заполненно неправильно.',
    'validator.missed': 'Это поле необходимо заполнить.',
    'validator.not_valid_type': 'Проверьте правильность введённого значения.',
    'validator.not_valid_email': 'Введите правильный Email адрес.',
    'validator.not_valid_url': 'Введите правильный URL.',
    'validator.not_valid_tel': 'Введите правильный номер телефона.',
    'validator.not_match_pattern': 'Введённое значение не соответствует шаблону {{ pattern }}.',
    'validator.too_short': 'Введённое значение должно быть длинее {{ minlength }} символов.',
    'validator.too_long': 'Введённое значение должно быть короче {{ maxlength }} символов.',
    'validator.step_mismatch': 'Пожалуйста, выберите значение кратное {{ step }}.',
    'validator.overflow': 'Введённое значение должно быть меньше или равно {{ max }}.',
    'validator.underflow': 'Введённое значение должно быть больше или равно {{ min }}.',
    'validator.smth_went_wrong': 'Что то пошло не так.',
    'validator.network_error': 'При выполнении запроса возникли проблемы, проверьте подключение к интернету.',
    'validator.access_denied': 'Не удалось определить пользователя. Доступ запрещён.',
    'Foreign key constraint violation error occured.': 'Удаление невозможно, у этого объекта есть зависимости.'
};

export default {
    locale: 'ru',
    translation: ru
};