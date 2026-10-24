# Agent Directives: Eikaiwa Lesson Review Portal - PHP 8.5.12 Modernization & Hexagonal Architecture

> **Target Project:** `C:\xampp\htdocs\lessonnotes`  
> **Runtime Environment:** PHP 8.5.12 (ZTS Visual C++ 2022 x64) / Apache 2.4.58 (Win64) / MySQL (XAMPP Windows)  
> **PRD Source:** [product_requirements_document.md](file:///C:/xampp/htdocs/lessonnotes/product_requirements_document.md)  
> **Lead Architect Directive:** Security-First Principal Engineer Governance  

---

## 1. Executive Summary & PHP 8.5.12 Context

The server's PHP runtime has been upgraded to **PHP 8.5.12**.

`lessonnotes` is currently a legacy procedural application ([`class.php`](file:///C:/xampp/htdocs/lessonnotes/class.php), [`lesson.php`](file:///C:/xampp/htdocs/lessonnotes/lesson.php), [`includes/LessonParser.php`](file:///C:/xampp/htdocs/lessonnotes/includes/LessonParser.php)). All agents assigned to this codebase are directed to refactor it into **Hexagonal Architecture (Clean Architecture)** using modern PHP 8.5 language capabilities.

---

## 2. Modernizing with PHP 8.5 Idioms

All refactored application code must reside in `src/` under the `LessonNotes\` PSR-4 namespace.

### 2.1 Domain Entities with Asymmetric Visibility & Property Hooks
Define clean, self-validating entities under `src/Domain/Entities/`:

```php
<?php
declare(strict_types=1);

namespace LessonNotes\Domain\Entities;

use InvalidArgumentException;

final class TranslationEntry
{
    public function __construct(
        public private(set) ?int $id,
        public private(set) int $lessonId,
        public private(set) string $originalText,
        public private(set) string $translatedText,
        public private(set) ?string $romajiText = null,
        public private(set) int $sortOrder = 0
    ) {
        if (trim($this->originalText) === '') {
            throw new InvalidArgumentException('Original text cannot be empty.');
        }
        if (trim($this->translatedText) === '') {
            throw new InvalidArgumentException('Translated text cannot be empty.');
        }
    }

    // Property hook for sanitized display text
    public string $displayOriginal {
        get => htmlspecialchars($this->originalText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public string $displayTranslated {
        get => htmlspecialchars($this->translatedText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
```

### 2.2 Modern Parser Service with `array_all` & `array_find`
Refactor the bilingual parser in `src/Domain/Services/RegexBilingualParser.php`:
* Eliminate fragile manual loops and replace with clean stream-like operations using `array_find()`, `array_any()`, and `array_all()`.
* Validate that each lesson block contains both original and translated text before producing `TranslationEntry` value objects.

### 2.3 Strict Typing & Elimination of Implicitly Nullable Parameters
Audit all function signatures in `includes/functions.php` and new classes. In PHP 8.5:
* **FORBIDDEN (Throws `E_DEPRECATED`):**
  ```php
  function getLessonsByClass(int $classId, string $order = null)
  ```
* **MANDATORY:**
  ```php
  function getLessonsByClass(int $classId, ?string $order = null): array
  ```

### 2.4 Use of `#[Override]` Attribute
Tag all methods in `src/Infrastructure/Persistence/PdoLessonRepository.php` and `PdoLessonClassRepository.php` with `#[Override]` to ensure compile-time contract enforcement.

---

## 3. Mandatory Backend Governance Rules

1. **Strict Types:** `declare(strict_types=1);` must be at the very top of EVERY `.php` file.
2. **Dependency Injection:** Use `src/Infrastructure/Container/DIContainer.php` to resolve use cases ([`ParseAndSaveLessonUseCase`](file:///C:/xampp/htdocs/lessonnotes/src/Application/UseCases/ParseAndSaveLessonUseCase.php), etc.). No global or static database connections.
3. **DOM Output Sanitization:** Always escape rendered Kanji, Kana, Romaji, and English strings in HTML views to prevent XSS:
   ```php
   <?= htmlspecialchars((string)$entry->getTranslatedText(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
   ```
4. **Database Governance (MySQL InnoDB):** Ensure tables (`lesson_classes`, `lessons`, `translation_entries`) use `ENGINE=InnoDB` with explicit foreign keys:
   * `lessons.class_id` -> `FOREIGN KEY (class_id) REFERENCES lesson_classes(id) ON DELETE RESTRICT`
   * `translation_entries.lesson_id` -> `FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE`

---

## 4. Agent Verification Checklist

1. **Syntax & Deprecation Lint:** Run `C:\xampp\php\php.exe -l` on all PHP files in `src/`, `admin/`, and root.
2. **Parser Unit Verification:** Test bilingual translation parsing against Format A (EN->JP), Format B (JP->EN), and Format C (Vocabulary).
3. **Foreign Key Verification:** Verify MySQL schema enforces InnoDB cascading deletes on lesson entries.
