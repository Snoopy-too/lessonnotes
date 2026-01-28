Product Requirements Document (PRD): Eikaiwa Lesson Review Portal
1. Project Overview
The goal of this project is to create a lightweight web application for a small English conversation school (Eikaiwa) in Japan. The app allows the teacher to paste a list of bilingual translations from a lesson into a dashboard. The app then automatically parses this text and organizes it into a student-facing portal categorized by class and date.
2. Target Users
The Teacher (Admin): Needs a fast, "one-action" way to upload lesson notes.
The Students: Need a mobile-friendly, simple way to review the specific translations from their class.
3. Functional Requirements
3.1 Class Structure
The system must support three distinct classes:
Thursday 9 PM
Friday 10 AM
Saturday 2 PM
3.2 The Teacher Dashboard (Admin)
Authentication: A simple password-protected login or a secure URL to prevent public access to the upload tool.
Data Entry Form:
Class Selection: A dropdown menu to select one of the three classes.
Date Selection: A date picker (defaulting to the current date).
Text Area: A large text box where the teacher pastes the lesson notes.
Processing Engine: Upon clicking "Save," the app must parse the text (see Section 4) and save individual entries to the database linked to that class and date.
3.3 Student Front-End
Landing Page: Displays three large, clear buttons representing the three classes.
Class Archive Page: When a class is selected, the app displays a list of buttons, each labeled with a date (e.g., "October 12, 2023") where lessons were recorded. Dates should be sorted with the most recent at the top.
Lesson View Page: When a date is clicked, the app displays the full list of translations for that day in a clean, easy-to-read list format.
4. Data Parsing Logic (Crucial)
The app must be able to parse a specific text format. The developer should use Regular Expressions (Regex) or a line-by-line string parser to handle the following patterns:
Format A (English to Japanese):
code
Text
Original: [English Sentence]
(Jp): [Japanese Kanji/Kana]
(Romaji): [Japanese Romaji]
Format B (Japanese to English):
code
Text
Original: [Japanese Kanji/Kana or Romaji]
(English): [English Sentence]
Format C (Vocabulary/Short phrases):
code
Text
Original: [Word]
(English/Jp): [Translation]
Parsing Rules:
Entries are separated by an empty line or a divider (e.g., -------).
The Original: tag always starts a new record.
The (Romaji): line is optional and may not always be present.
The app must detect if the translation is labeled as (Jp), (English), or (Romaji) and store them in the correct database columns.
5. Technical Specifications
5.1 Tech Stack
Language: PHP (8.x preferred)
Database: MySQL
Styling: Minimalist CSS (Mobile-first design is a priority as students will likely use smartphones).
5.2 Database Schema Suggestion
classes table: id, class_name, slug
lessons table: id, class_id, lesson_date
translations table:
id
lesson_id (foreign key)
original_text (Text)
translated_text (Text)
romaji_text (Text, nullable)
sort_order (Integer)
6. User Interface (UI) Requirements
6.1 Admin UI
Clean, "Utility" feel.
Success message after saving that includes a "View Live Page" link.
6.2 Student UI
Typography: Large enough for easy reading on mobile. Support for Japanese fonts (e.g., Noto Sans JP).
Colors: High contrast for readability.
Layout:
Date buttons: Full-width buttons for easy tapping.
Translation Cards: Each entry should be visually separated (e.g., a light gray border or card style) so students don't confuse which English goes with which Japanese.
7. Non-Functional Requirements
Performance: The app should load quickly on Japanese mobile networks.
Security: Sanitize all text inputs to prevent SQL injection.
Reliability: The parser should be "forgiving" of extra spaces or accidental double line breaks in the pasted text.
8. Acceptance Criteria
Teacher can select "Thursday 9PM," paste 10 translations, and hit save.
The system correctly identifies which line is "Original," which is "Translation," and which is "Romaji."
The "Thursday 9PM" student page immediately shows a new button with today's date.
Clicking that date displays the 10 translations in a clear, mobile-friendly list.
Entries without Romaji display correctly without broken layout or empty brackets.