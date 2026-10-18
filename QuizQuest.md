# QuizQuest
## User Manual and Technical Action Reference

**Application file:** `QuizQuest.php`  
**Database:** SQLite (`quizquest.db`)  
**Application type:** Server-rendered PHP quiz system

---

## 1. What QuizQuest Does

QuizQuest allows an administrator or quiz creator to build trivia questions and rounds, while players can take rounds and earn points.

The application has four main areas:

1. Create and manage questions and rounds.
2. Play a quiz.
3. View the leaderboard.
4. Manage completed scores.

The current version is an HTML application, not a JSON REST API. The technical section in this manual documents the existing GET parameters and POST form actions.

---

## 2. Starting QuizQuest

### Requirements

- PHP 7.1 or later; PHP 8.x recommended.
- PDO SQLite enabled.
- PHP sessions enabled.
- A writable application directory.
- The stylesheet at `../styles/style.css`.

### Run locally

From the directory containing `QuizQuest.php`:

```bash
php -S localhost:8000
```

Open:

```text
http://localhost:8000/QuizQuest.php
```

The application creates `quizquest.db` automatically on first access.

---

## 3. Dashboard

The dashboard displays four navigation cards:

- **Create Quiz & Rounds Manually**
- **Play Quiz**
- **View Leaderboard**
- **Manage Scores & Data**

The dashboard URL is:

```text
QuizQuest.php?tab=dashboard
```

If no `tab` is supplied, the dashboard is shown.

---

## 4. Creating Questions

### 4.1 Open the question manager

Select **Create Quiz & Rounds Manually** from the dashboard, or open:

```text
QuizQuest.php?tab=questions
```

### 4.2 Add a question

Complete the following fields:

| Field | Instructions |
|---|---|
| Question | Enter the question text |
| Creator's name | Enter the author or creator |
| Type of Question | Choose `MCQ` or `Open` |
| Difficulty | Choose `Easy`, `Medium`, or `Hard` |
| Options | For MCQ, enter options separated by commas |
| Correct Answer | Enter the answer used for scoring |

For an MCQ, an example is:

```text
Question: What is 2 + 2?
Options: 3, 4, 5
Correct Answer: 4
```

For an open question, enter the expected answer in **Correct Answer**. The player's answer is trimmed and compared without case sensitivity.

### 4.3 View questions

Existing questions are listed with:

- Question text
- Creator
- Type
- Difficulty
- Correct answer
- Delete control

The current interface displays correct answers in the administration view. Restrict access to this screen if players should not see answer keys.

### 4.4 Delete a question

Select **Delete** beside the question.

A deleted question is removed from the `questions` table. If an existing round references that question, the round still contains its old question ID, but the deleted question will not be loaded when the round starts.

For predictable results, delete or recreate affected rounds after deleting their questions.

---

## 5. Creating Quiz Rounds

A round is a named group of selected questions.

### 5.1 Create a round

On the question-management page:

1. Enter a **Round Name**.
2. Enter a **Theme**.
3. Enter a **Time Limit** in seconds.
4. Select one or more questions.
5. Select **Add Round**.

The round list shows:

- Round name
- Theme
- Time limit
- Delete control

### 5.2 Time limit behavior

The time limit is saved and displayed, but the current application does not run a countdown or prevent answers after the limit. Treat the value as descriptive metadata until timing logic is implemented.

### 5.3 Delete a round

Select **Delete** beside the round. This removes the round definition. Existing score rows that refer to the deleted round may become difficult to display because the score query expects a matching round record.

---

## 6. Playing a Quiz

### 6.1 Start a round

1. Select **Play Quiz** from the dashboard.
2. Enter a player name.
3. Select a round.
4. Select **Start Quiz**.

The application loads the selected round's questions into the current PHP session and sets the score to zero.

### 6.2 Answer questions

For each question:

- Select one radio option for an MCQ.
- Type an answer for an open question.
- Select **Submit Answer**.

The current score and question number are displayed.

A correct answer adds one point. An incorrect answer adds zero points.

Answer matching is case-insensitive and ignores leading or trailing whitespace. The current implementation performs exact text matching after those normalizations; it does not accept synonyms or partial answers.

### 6.3 Finish the quiz

After the final answer, the application automatically detects that all questions have been answered and saves the score. If the final question screen is reached, **Finish Quiz** is also available.

When a quiz is completed, the application:

1. Finds or creates the player in `users`.
2. Inserts a completed result in `scores`.
3. Removes the player's temporary row from `temp_scores`.
4. Displays a completion message such as:

   ```text
   Quiz Complete! Score: 4 / 5
   ```

5. Clears the PHP session.

### 6.4 Temporary progress

After each submitted answer, QuizQuest stores the current score and question index in `temp_scores`. This is intended as live progress tracking.

The current interface does not provide a resume screen, but the stored data can be used by a future resume feature.

---

## 7. Leaderboard

Open **View Leaderboard** or:

```text
QuizQuest.php?tab=leaderboard
```

The leaderboard shows up to ten players, ordered by the sum of all their saved points.

Columns:

- Rank
- Player
- Total Points

Scores are cumulative across all completed attempts, not limited to one round or one date.

---

## 8. Managing Scores

Open **Manage Scores & Data** or:

```text
QuizQuest.php?tab=scores
```

### 8.1 Search scores

You can filter by:

- **Player:** Partial name match.
- **Quiz Date:** Exact date.
- **Difficulty:** Easy, Medium, or Hard.

Select **Search** to apply the filters.

### 8.2 Update a score

In the score table:

1. Change the points value.
2. Select **Update**.

This changes the stored `scores.points` value and therefore affects the leaderboard total.

### 8.3 Delete a score

Select **Delete** beside a score. The record is permanently removed and no longer contributes to the leaderboard.

---

## 9. Form-Action Reference

QuizQuest uses ordinary HTML forms. It does not currently provide JSON responses.

### 9.1 Navigation parameters

The `tab` GET parameter controls the visible page:

| URL | Page |
|---|---|
| `?tab=dashboard` | Dashboard |
| `?tab=questions` | Questions and rounds |
| `?tab=play_quiz` | Start or continue quiz |
| `?tab=leaderboard` | Leaderboard |
| `?tab=scores` | Score management |

Example:

```text
http://localhost:8000/QuizQuest.php?tab=play_quiz
```

### 9.2 Create question

```http
POST /QuizQuest.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

create_question=1&text=What+is+2%2B2%3F&creator=Admin&type=MCQ&difficulty=Easy&options=3%2C+4%2C+5&correct=4
```

Fields:

| Field | Description |
|---|---|
| `create_question` | Submit marker |
| `text` | Question text |
| `creator` | Question creator |
| `type` | `MCQ` or `Open` |
| `difficulty` | `Easy`, `Medium`, or `Hard` |
| `options` | Comma-separated options for MCQ |
| `correct` | Correct answer |

### 9.3 Create round

```http
POST /QuizQuest.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

create_round=1&name=General+Knowledge&theme=Trivia&time_limit=60&questions%5B%5D=1&questions%5B%5D=2
```

Fields:

| Field | Description |
|---|---|
| `create_round` | Submit marker |
| `name` | Round name |
| `theme` | Round theme |
| `time_limit` | Stored time-limit value in seconds |
| `questions[]` | One or more question IDs |

### 9.4 Delete question

```http
POST /QuizQuest.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

delete_question=1&question_id=12
```

### 9.5 Delete round

```http
POST /QuizQuest.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

delete_round=1&round_id=3
```

### 9.6 Start quiz

```http
POST /QuizQuest.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

start_quiz=1&player_name=Alex&round_id=3
```

This sets `player_name`, `round_id`, `score`, `questions`, and `current` in the PHP session.

### 9.7 Submit an answer

```http
POST /QuizQuest.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

answer=1&selected=Mars
```

The application compares `selected` with the current question's `correct_answer`, updates the session score, stores a temporary score record, and advances to the next question.

### 9.8 Finish quiz

```http
POST /QuizQuest.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

finish_quiz=1
```

The application saves the current session score to `scores`, deletes the corresponding temporary score, shows the completion message, and clears the session.

### 9.9 Search scores

```http
POST /QuizQuest.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

search_scores=1&player=Alex&quiz_date=2026-10-18&difficulty=Easy
```

All filters are optional.

### 9.10 Update score

```http
POST /QuizQuest.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

update_score=1&score_id=10&points=8
```

### 9.11 Delete score

```http
POST /QuizQuest.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

delete_score=1&score_id=10
```

---

## 10. Database Reference

### `users`

Stores unique player names.

| Column | Description |
|---|---|
| `user_id` | Auto-incrementing identifier |
| `name` | Unique player name |

### `questions`

Stores question content and answer keys.

| Column | Description |
|---|---|
| `question_id` | Auto-incrementing identifier |
| `text` | Question text |
| `creator` | Author name |
| `type` | `MCQ` or `Open` |
| `difficulty` | `Easy`, `Medium`, or `Hard` |
| `options` | JSON array for MCQ questions |
| `correct_answer` | Stored answer used for scoring |

### `rounds`

Stores quiz-round configuration.

| Column | Description |
|---|---|
| `round_id` | Auto-incrementing identifier |
| `name` | Round name |
| `theme` | Round theme |
| `question_ids` | JSON array of question IDs |
| `time_limit` | Stored time limit in seconds |

### `scores`

Stores completed attempts.

| Column | Description |
|---|---|
| `score_id` | Auto-incrementing identifier |
| `user_id` | Linked player |
| `round_id` | Linked round |
| `points` | Number of correct answers or manually updated points |
| `quiz_date` | Date of completion |

### `temp_scores`

Stores progress while an attempt is active.

| Column | Description |
|---|---|
| `temp_id` | Auto-incrementing identifier |
| `user_name` | Player name used for the attempt |
| `round_id` | Active round |
| `current_score` | Score so far |
| `current_question` | Next/current question index used by the application |
| `last_update` | Last progress timestamp |

Each player/round pair is unique in this table.

---

## 11. Troubleshooting

### SQLite driver error

If PHP reports `could not find driver`, enable PDO SQLite:

```bash
sudo apt install php-sqlite3
```

Restart the server after installation.

### Styling is missing

Check that the stylesheet exists at:

```text
../styles/style.css
```

The path is relative to the URL directory of `QuizQuest.php`.

### The quiz session disappears

The active quiz is stored in the PHP session. Check that browser cookies are enabled and the PHP session directory is writable.

### A round displays fewer questions

If a question was deleted after a round was created, its ID remains in the round's JSON list but the question can no longer be loaded. Recreate the round using existing questions.

### The time limit has no effect

The application stores the time limit but does not implement a countdown or server-side timeout.

### A score appears under an unexpected difficulty

The current score query uses the first question in the round to display difficulty. A mixed-difficulty round can therefore show only the first question's difficulty.

---

## 12. Security and Administration Notes

The current version should be treated as a local or classroom application unless additional controls are added.

Recommended improvements include:

- Add authentication and role-based permissions.
- Restrict question and answer-key management to administrators.
- Add CSRF tokens to every POST form.
- Escape every database value before printing it into HTML.
- Validate question IDs, round IDs, points, and time limits on the server.
- Use prepared statements for every database lookup, including round loading.
- Add confirmation and authorization for deletion and score updates.
- Enforce time limits on the server if timed play is required.
- Prevent users from tampering with session and score state.
- Log administrative changes.
- Back up `quizquest.db` before maintenance.

---

## 13. Quick Start Checklist

1. Enable PHP, PDO SQLite, and sessions.
2. Place `QuizQuest.php` in a writable web directory.
3. Confirm `../styles/style.css` is available.
4. Start the PHP server.
5. Open QuizQuest in a browser.
6. Create at least one question.
7. Create a round and select questions.
8. Open **Play Quiz**.
9. Enter a player name and select the round.
10. Submit answers and finish the quiz.
11. Review the result on the leaderboard or score-management page.
