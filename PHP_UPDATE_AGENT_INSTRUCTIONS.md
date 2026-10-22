# Agent Directives: Eikaiwa Lesson Review Portal - Full Hexagonal Architecture Refactoring & PHP 8.2.35 Alignment

> **Target Project:** `C:\xampp\htdocs\lessonnotes`  
> **Runtime Environment:** PHP 8.2.35 (ZTS Visual C++ 2019 x64) / Apache 2.4 / MySQL (XAMPP Windows)  
> **PRD Source:** [product_requirements_document.md](file:///C:/xampp/htdocs/lessonnotes/product_requirements_document.md)  
> **Lead Architect Directive:** Security-First Principal Engineer Governance  

---

## 1. Context & Architectural Assessment

The server has updated to **PHP 8.2.35**. 

`lessonnotes` is an Eikaiwa (English conversation school) lesson notes portal where teachers paste bilingual translation text which the app parses and displays to students organized by class and date. Currently, the project is structured with legacy procedural scripts:
* Direct root scripts: [class.php](file:///C:/xampp/htdocs/lessonnotes/class.php), [lesson.php](file:///C:/xampp/htdocs/lessonnotes/lesson.php), [index.php](file:///C:/xampp/htdocs/lessonnotes/index.php).
* Procedural parser in [includes/LessonParser.php](file:///C:/xampp/htdocs/lessonnotes/includes/LessonParser.php).
* Untyped database setup in [setup/init_database.php](file:///C:/xampp/htdocs/lessonnotes/setup/init_database.php) and [config/database.php](file:///C:/xampp/htdocs/lessonnotes/config/database.php).
* Zero strict typing declarations (`declare(strict_types=1);` is completely missing).

All agents assigned to this codebase are directed to conduct a complete refactor into **Hexagonal Architecture (Clean Architecture)** with strict typing, robust parsing logic, and bulletproof XSS defenses.

---

## 2. Mandatory Hexagonal Architecture Structure

Create and organize the application under a new `src/` directory with PSR-4 autoloader (`LessonNotes\` namespace):

```
src/
├── Domain/
│   ├── Entities/
│   │   ├── LessonClass.php           # ID, name, schedule_day, schedule_time, slug
│   │   ├── LessonRecord.php          # ID, class_id, lesson_date, raw_text, created_at
│   │   └── TranslationEntry.php      # ID, lesson_id, original, translated, romaji, sort_order
│   ├── Repositories/                 # Ports (Interfaces)
│   │   ├── LessonClassRepositoryInterface.php
│   │   └── LessonRepositoryInterface.php
│   └── Services/
│       ├── ParserInterface.php
│       └── RegexBilingualParser.php  # Handles Format A (EN->JP), Format B (JP->EN), Format C (vocab)
│
├── Application/
│   ├── DTO/
│   │   ├── ParseLessonRequest.php
│   │   ├── SaveLessonRequest.php
│   │   └── LessonSummaryResponse.php
│   └── UseCases/
│       ├── ParseAndSaveLessonUseCase.php
│       ├── GetClassLessonsUseCase.php
│       └── GetLessonDetailUseCase.php
│
└── Infrastructure/
    ├── Container/
    │   └── DIContainer.php           # Dependency Injection container
    ├── Persistence/
    │   ├── DatabaseConnection.php    # PDO factory with UTF-8 mb4
    │   ├── PdoLessonClassRepository.php
    │   └── PdoLessonRepository.php
    └── Security/
        ├── AuthGuard.php             # Teacher authentication & session guard
        └── SanitizeHelper.php        # HTML escaping for bilingual rendering
```

---

## 3. Strict Coding & Security Standards

### 3.1 Strict Typing
Every PHP file MUST begin with:
```php
<?php
declare(strict_types=1);
```
No implicit scalar conversions. Use typed arguments, return types, and class properties.

### 3.2 Robust Domain Parsing Logic
The translation parser (`src/Domain/Services/RegexBilingualParser.php`) must be thoroughly unit tested and strictly typed:
* Parse Format A (English to Japanese):
  ```
  Original: [English Sentence]
  (Jp): [Japanese Kanji/Kana]
  (Romaji): [Japanese Romaji]
  ```
* Parse Format B (Japanese to English):
  ```
  Original: [Japanese Kanji/Kana or Romaji]
  (English): [English Sentence]
  ```
* Support flexible whitespace, line breaks, and optional Romaji fields.
* Return typed domain entity collections: `TranslationEntry[]`.
* If a block cannot be parsed, reject or flag with explicit validation errors rather than silently losing student notes.

### 3.3 DOM Sanitization (XSS Immunity)
* Since lesson notes contain user-pasted Japanese and English text with punctuation, quotes, and parentheticals, never output unescaped variables in HTML:
  ```php
  // MANDATORY:
  <?= htmlspecialchars((string)$entry->getTranslatedText(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  ```
* Content-Security-Policy headers must be emitted in student and admin headers.

### 3.4 Dependency Injection (Zero Static God Classes)
* All controllers and root pages must instantiate services exclusively via `src/Infrastructure/Container/DIContainer.php`.
* No static PDO variables in `config/database.php`.

---

## 4. Database Governance (MySQL InnoDB)

Ensure database tables adhere to the following schema with **InnoDB** and explicit Foreign Keys:

```sql
CREATE TABLE IF NOT EXISTS `lesson_classes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `schedule_day` VARCHAR(20) DEFAULT NULL,
    `schedule_time` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lessons` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `class_id` INT UNSIGNED NOT NULL,
    `lesson_date` DATE NOT NULL,
    `raw_text` MEDIUMTEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_lessons_class` FOREIGN KEY (`class_id`) REFERENCES `lesson_classes` (`id`) ON DELETE RESTRICT,
    INDEX `idx_class_date` (`class_id`, `lesson_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `translation_entries` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lesson_id` INT UNSIGNED NOT NULL,
    `original_text` TEXT NOT NULL,
    `translated_text` TEXT NOT NULL,
    `romaji_text` TEXT DEFAULT NULL,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT `fk_translations_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
    INDEX `idx_lesson_order` (`lesson_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Agent Implementation Checklist

1. **Setup `src/` & Autoloader:** Create PSR-4 autoloader and DI container.
2. **Build Domain Entities & Parser:** Implement `LessonClass`, `LessonRecord`, `TranslationEntry`, and `RegexBilingualParser` with full type coverage.
3. **Build Repositories:** Implement `PdoLessonClassRepository` and `PdoLessonRepository` with prepared statements and transactions.
4. **Implement Application UseCases:** `ParseAndSaveLessonUseCase` and `GetLessonDetailUseCase`.
5. **Convert Root Presentation Files:**
   * Refactor [index.php](file:///C:/xampp/htdocs/lessonnotes/index.php) (class select landing page).
   * Refactor [class.php](file:///C:/xampp/htdocs/lessonnotes/class.php) (date archive buttons).
   * Refactor [lesson.php](file:///C:/xampp/htdocs/lessonnotes/lesson.php) (clean translation card display).
   * Refactor [admin/new.php](file:///C:/xampp/htdocs/lessonnotes/admin/new.php) (teacher upload interface).
6. **Verification:** Validate all PHP files with `C:\xampp\php\php.exe -l` to ensure zero syntax or type warnings under PHP 8.2.35.
