<?php

declare(strict_types=1);

set_time_limit(10);
ini_set('default_socket_timeout', '5');

const DB_HOST = 'localhost';
const DB_PORT = '3306';
const DB_NAME = 'u82301';
const DB_USER = 'u82301';
const DB_PASSWORD = '9281538';

$availableLanguages = [
    'Pascal',
    'C',
    'C++',
    'JavaScript',
    'PHP',
    'Python',
    'Java',
    'Haskell',
    'Clojure',
    'Prolog',
    'Scala',
    'Go',
];

$genderOptions = [
    'male' => 'Мужской',
    'female' => 'Женский',
];

/**
 * Безопасный вывод HTML.
 */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Длина строки с учётом мультибайтовости.
 */
function stringLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

/**
 * Установка cookie с данными ошибок (до конца сессии — без указания срока).
 */
function setErrorCookies(array $errors, array $values): void
{
    setcookie('form_errors', json_encode($errors, JSON_UNESCAPED_UNICODE), 0, '/');
    setcookie('form_values', json_encode($values, JSON_UNESCAPED_UNICODE), 0, '/');
}

/**
 * Удаление cookie ошибок после их использования.
 */
function clearErrorCookies(): void
{
    setcookie('form_errors', '', time() - 3600, '/');
    setcookie('form_values', '', time() - 3600, '/');
}

/**
 * Установка cookie со значениями полей на 1 год (при успешной отправке).
 */
function setSuccessCookies(array $values): void
{
    $expire = time() + 365 * 24 * 60 * 60;
    setcookie('saved_full_name', $values['full_name'], $expire, '/');
    setcookie('saved_phone', $values['phone'], $expire, '/');
    setcookie('saved_email', $values['email'], $expire, '/');
    setcookie('saved_birth_date', $values['birth_date'], $expire, '/');
    setcookie('saved_gender', $values['gender'], $expire, '/');
    setcookie('saved_languages', json_encode($values['languages'], JSON_UNESCAPED_UNICODE), $expire, '/');
    setcookie('saved_biography', $values['biography'], $expire, '/');
    setcookie('saved_contract_accepted', $values['contract_accepted'] ? '1' : '0', $expire, '/');
}

// ─── Обработка POST ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'birth_date' => trim((string) ($_POST['birth_date'] ?? '')),
        'gender' => trim((string) ($_POST['gender'] ?? '')),
        'languages' => array_values(array_unique(array_map('strval', $_POST['languages'] ?? []))),
        'biography' => trim((string) ($_POST['biography'] ?? '')),
        'contract_accepted' => isset($_POST['contract_accepted']),
    ];

    $errors = [];

    // ФИО: только буквы (unicode), пробелы, дефис
    if ($values['full_name'] === '') {
        $errors['full_name'] = 'Укажите ФИО.';
    } elseif (stringLength($values['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    } elseif (!preg_match('/^[\p{L}\s\-]+$/u', $values['full_name'])) {
        $errors['full_name'] = 'ФИО может содержать только буквы, пробелы и дефис.';
    }

    // Телефон: цифры, +, пробелы, дефис, скобки
    if ($values['phone'] === '') {
        $errors['phone'] = 'Укажите телефон.';
    } elseif (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $values['phone'])) {
        $errors['phone'] = 'Допустимы: цифры, +, пробелы, дефис, скобки (7–20 символов).';
    }

    // E-mail: регулярное выражение
    if ($values['email'] === '') {
        $errors['email'] = 'Укажите e-mail.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $values['email'])) {
        $errors['email'] = 'Допустимы: латинские буквы, цифры, точки, дефисы, @. Пример: user@example.com';
    } elseif (stringLength($values['email']) > 255) {
        $errors['email'] = 'E-mail не должен превышать 255 символов.';
    }

    // Дата рождения
    if ($values['birth_date'] === '') {
        $errors['birth_date'] = 'Укажите дату рождения.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['birth_date'])) {
        $errors['birth_date'] = 'Дата должна быть в формате ГГГГ-ММ-ДД. Допустимы только цифры и дефисы.';
    } else {
        $birthDate = DateTimeImmutable::createFromFormat('Y-m-d', $values['birth_date']);
        $birthDateErrors = DateTimeImmutable::getLastErrors();
        if ($birthDateErrors === false) {
            $birthDateErrors = ['warning_count' => 0, 'error_count' => 0];
        }
        $isBirthDateValid = $birthDate instanceof DateTimeImmutable
            && $birthDate->format('Y-m-d') === $values['birth_date']
            && $birthDateErrors['warning_count'] === 0
            && $birthDateErrors['error_count'] === 0;

        if (!$isBirthDateValid) {
            $errors['birth_date'] = 'Введите корректную дату рождения.';
        } elseif ($birthDate > new DateTimeImmutable('today')) {
            $errors['birth_date'] = 'Дата рождения не может быть в будущем.';
        }
    }

    // Пол
    if (!array_key_exists($values['gender'], $genderOptions)) {
        $errors['gender'] = 'Выберите допустимый пол.';
    }

    // Языки программирования
    if ($values['languages'] === []) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($values['languages'] as $language) {
            if (!in_array($language, $availableLanguages, true)) {
                $errors['languages'] = 'Список содержит недопустимое значение.';
                break;
            }
        }
    }

    // Биография: допустимы буквы, цифры, пробелы, знаки пунктуации
    if ($values['biography'] === '') {
        $errors['biography'] = 'Напишите биографию.';
    } elseif (stringLength($values['biography']) > 2000) {
        $errors['biography'] = 'Биография не должна превышать 2000 символов.';
    } elseif (!preg_match('/^[\p{L}\p{N}\s\p{P}\p{S}\r\n]+$/u', $values['biography'])) {
        $errors['biography'] = 'Допустимы: буквы, цифры, пробелы и знаки пунктуации.';
    }

    // Контракт
    if (!$values['contract_accepted']) {
        $errors['contract_accepted'] = 'Необходимо ознакомиться с контрактом.';
    }

    // ─── Есть ошибки → сохраняем в cookies и редирект GET ───
    if ($errors !== []) {
        setErrorCookies($errors, $values);
        header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
        exit;
    }

    // ─── Нет ошибок → сохраняем в БД ───
    $dbError = null;
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
            DB_USER,
            DB_PASSWORD,
            [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $pdo->beginTransaction();

        $submissionStatement = $pdo->prepare(
            'INSERT INTO submissions (full_name, phone, email, birth_date, gender, biography, contract_accepted)
             VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_accepted)'
        );

        $submissionStatement->execute([
            ':full_name' => $values['full_name'],
            ':phone' => $values['phone'],
            ':email' => $values['email'],
            ':birth_date' => $values['birth_date'],
            ':gender' => $values['gender'],
            ':biography' => $values['biography'],
            ':contract_accepted' => 1,
        ]);

        $submissionId = (int) $pdo->lastInsertId();

        $languageSelectStatement = $pdo->prepare('SELECT id FROM programming_languages WHERE name = :name');
        $submissionLanguageStatement = $pdo->prepare(
            'INSERT INTO submission_languages (submission_id, language_id) VALUES (:submission_id, :language_id)'
        );

        foreach ($values['languages'] as $language) {
            $languageSelectStatement->execute([':name' => $language]);
            $languageId = $languageSelectStatement->fetchColumn();

            if ($languageId === false) {
                throw new RuntimeException('Не найден язык программирования: ' . $language);
            }

            $submissionLanguageStatement->execute([
                ':submission_id' => $submissionId,
                ':language_id' => (int) $languageId,
            ]);
        }

        $pdo->commit();

        // Успех → сохраняем значения в cookies на 1 год
        setSuccessCookies($values);
        clearErrorCookies();

        // Устанавливаем cookie для сообщения об успехе (до конца сессии)
        setcookie('form_success', '1', 0, '/');

        header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
        exit;

    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Ошибка БД → сохраняем в cookies и редирект
        $errors['_db'] = 'Не удалось сохранить данные: ' . $exception->getMessage();
        setErrorCookies($errors, $values);
        header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
        exit;
    }
}

// ─── GET: чтение cookies и подготовка данных для формы ───

$errors = [];
$successMessage = null;
$dbError = null;

// Проверяем cookie успеха
if (isset($_COOKIE['form_success'])) {
    $successMessage = 'Данные успешно сохранены.';
    setcookie('form_success', '', time() - 3600, '/');
}

// Проверяем cookies ошибок
if (isset($_COOKIE['form_errors'])) {
    $errors = json_decode($_COOKIE['form_errors'], true) ?: [];
    if (isset($errors['_db'])) {
        $dbError = $errors['_db'];
        unset($errors['_db']);
    }
    // Удаляем cookies ошибок после использования
    clearErrorCookies();
}

// Начальные значения: из cookies ошибок (если были) или из сохранённых (1 год)
if (isset($_COOKIE['form_values'])) {
    $cookieValues = json_decode($_COOKIE['form_values'], true);
    if (is_array($cookieValues)) {
        $values = [
            'full_name' => (string) ($cookieValues['full_name'] ?? ''),
            'phone' => (string) ($cookieValues['phone'] ?? ''),
            'email' => (string) ($cookieValues['email'] ?? ''),
            'birth_date' => (string) ($cookieValues['birth_date'] ?? ''),
            'gender' => (string) ($cookieValues['gender'] ?? ''),
            'languages' => (array) ($cookieValues['languages'] ?? []),
            'biography' => (string) ($cookieValues['biography'] ?? ''),
            'contract_accepted' => !empty($cookieValues['contract_accepted']),
        ];
    } else {
        $values = [
            'full_name' => '',
            'phone' => '',
            'email' => '',
            'birth_date' => '',
            'gender' => '',
            'languages' => [],
            'biography' => '',
            'contract_accepted' => false,
        ];
    }
} else {
    // Значения из сохранённых cookies (на 1 год)
    $values = [
        'full_name' => (string) ($_COOKIE['saved_full_name'] ?? ''),
        'phone' => (string) ($_COOKIE['saved_phone'] ?? ''),
        'email' => (string) ($_COOKIE['saved_email'] ?? ''),
        'birth_date' => (string) ($_COOKIE['saved_birth_date'] ?? ''),
        'gender' => (string) ($_COOKIE['saved_gender'] ?? ''),
        'languages' => isset($_COOKIE['saved_languages']) ? (json_decode($_COOKIE['saved_languages'], true) ?: []) : [],
        'biography' => (string) ($_COOKIE['saved_biography'] ?? ''),
        'contract_accepted' => ($_COOKIE['saved_contract_accepted'] ?? '0') === '1',
    ];
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация разработчика</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="page">
    <div class="card">
        <div class="card__header">
            <p class="eyebrow">Регистрация</p>
            <h1>Анкета разработчика</h1>
            <p class="subtitle">Заполните форму, чтобы зарегистрироваться. Все поля обязательны.</p>
        </div>

        <?php if ($successMessage !== null): ?>
            <div class="alert alert--success"><?php echo escape($successMessage); ?></div>
        <?php endif; ?>

        <?php if ($dbError !== null): ?>
            <div class="alert alert--error"><?php echo escape($dbError); ?></div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="alert alert--error">
                Пожалуйста, исправьте ошибки в форме:
                <ul style="margin:8px 0 0;padding-left:20px;">
                    <?php foreach ($errors as $errorMsg): ?>
                        <li><?php echo escape($errorMsg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="post" novalidate>
            <div class="form-grid">

                <!-- ФИО -->
                <div class="field field--full">
                    <label for="full_name">ФИО</label>
                    <input
                        id="full_name"
                        name="full_name"
                        type="text"
                        maxlength="150"
                        value="<?php echo escape($values['full_name']); ?>"
                        class="<?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>"
                        placeholder="Например, Петров Алексей Сергеевич"
                    >
                    <?php if (isset($errors['full_name'])): ?>
                        <span class="error-text"><?php echo escape($errors['full_name']); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Телефон -->
                <div class="field">
                    <label for="phone">Телефон</label>
                    <input
                        id="phone"
                        name="phone"
                        type="tel"
                        value="<?php echo escape($values['phone']); ?>"
                        class="<?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                        placeholder="+7 999 000-00-00"
                    >
                    <?php if (isset($errors['phone'])): ?>
                        <span class="error-text"><?php echo escape($errors['phone']); ?></span>
                    <?php endif; ?>
                </div>

                <!-- E-mail -->
                <div class="field">
                    <label for="email">E-mail</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="<?php echo escape($values['email']); ?>"
                        class="<?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                        placeholder="developer@example.com"
                    >
                    <?php if (isset($errors['email'])): ?>
                        <span class="error-text"><?php echo escape($errors['email']); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Дата рождения -->
                <div class="field">
                    <label for="birth_date">Дата рождения</label>
                    <input
                        id="birth_date"
                        name="birth_date"
                        type="date"
                        value="<?php echo escape($values['birth_date']); ?>"
                        class="<?php echo isset($errors['birth_date']) ? 'is-invalid' : ''; ?>"
                    >
                    <?php if (isset($errors['birth_date'])): ?>
                        <span class="error-text"><?php echo escape($errors['birth_date']); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Пол -->
                <div class="field">
                    <fieldset class="fieldset <?php echo isset($errors['gender']) ? 'fieldset--invalid' : ''; ?>">
                        <legend>Пол</legend>
                        <div class="radio-group">
                            <?php foreach ($genderOptions as $genderValue => $genderLabel): ?>
                                <label class="radio-option">
                                    <input
                                        type="radio"
                                        name="gender"
                                        value="<?php echo escape($genderValue); ?>"
                                        <?php echo $values['gender'] === $genderValue ? 'checked' : ''; ?>
                                    >
                                    <?php echo escape($genderLabel); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (isset($errors['gender'])): ?>
                            <span class="error-text"><?php echo escape($errors['gender']); ?></span>
                        <?php endif; ?>
                    </fieldset>
                </div>

                <!-- Языки программирования -->
                <div class="field field--full">
                    <label for="languages">Любимые языки программирования</label>
                    <select
                        id="languages"
                        name="languages[]"
                        multiple
                        class="<?php echo isset($errors['languages']) ? 'is-invalid' : ''; ?>"
                    >
                        <?php foreach ($availableLanguages as $language): ?>
                            <option
                                value="<?php echo escape($language); ?>"
                                <?php echo in_array($language, $values['languages'], true) ? 'selected' : ''; ?>
                            >
                                <?php echo escape($language); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Удерживайте Ctrl (или Cmd на Mac) для выбора нескольких</span>
                    <?php if (isset($errors['languages'])): ?>
                        <span class="error-text"><?php echo escape($errors['languages']); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Биография -->
                <div class="field field--full">
                    <label for="biography">Биография</label>
                    <textarea
                        id="biography"
                        name="biography"
                        rows="5"
                        class="<?php echo isset($errors['biography']) ? 'is-invalid' : ''; ?>"
                        placeholder="Расскажите о своём опыте, проектах и увлечениях..."
                    ><?php echo escape($values['biography']); ?></textarea>
                    <?php if (isset($errors['biography'])): ?>
                        <span class="error-text"><?php echo escape($errors['biography']); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Контракт -->
                <div class="field field--full">
                    <label class="checkbox-option <?php echo isset($errors['contract_accepted']) ? 'checkbox-option--invalid' : ''; ?>">
                        <input
                            type="checkbox"
                            name="contract_accepted"
                            value="1"
                            <?php echo $values['contract_accepted'] ? 'checked' : ''; ?>
                        >
                        Я ознакомился(-лась) с условиями контракта и согласен(-на) с его положениями
                    </label>
                    <?php if (isset($errors['contract_accepted'])): ?>
                        <span class="error-text"><?php echo escape($errors['contract_accepted']); ?></span>
                    <?php endif; ?>
                </div>

            </div>

            <button class="button" type="submit">Отправить анкету</button>
        </form>
    </div>
</div>
</body>
</html>
