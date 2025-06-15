<?php
function getLanguage() {
    session_start();
    // Check session first
    if (isset($_SESSION['lang'])) {
        return $_SESSION['lang'];
    }
    
    // Check cookie (if session not set)
    if (isset($_COOKIE['lang'])) {
        $_SESSION['lang'] = $_COOKIE['lang'];
        return $_SESSION['lang'];
    }
    
    // Fallback to browser language (HTTP_ACCEPT_LANGUAGE)
    $browserLang = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
    $availableLangs = ['en', 'ro', 'es', 'fr'];
    if (in_array($browserLang, $availableLangs)) {
        $_SESSION['lang'] = $browserLang;
        return $browserLang;
    }
    
    // Default to English
    $_SESSION['lang'] = 'en';
    return 'en';
}

function setLanguage($lang) {
    session_start();
    $availableLangs = ['en', 'ro', 'es', 'fr'];
    if (in_array($lang, $availableLangs)) {
        $_SESSION['lang'] = $lang;
        setcookie('lang', $lang, time() + (86400 * 30), '/'); // Cookie lasts 30 days
    }
    header("Location: " . $_SERVER['HTTP_REFERER']); // Redirect back to the current page
    exit;
}

function loadTranslations($lang) {
    $translationsFile = __DIR__ . "/Translations/{$lang}.json";
    if (file_exists($translationsFile)) {
        return json_decode(file_get_contents($translationsFile), true);
    }
    return json_decode(file_get_contents(__DIR__ . "/Translations/en.json"), true); // Fallback to English
}