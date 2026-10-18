# Quiz Quest: The Ultimate Trivia Challenge

QuizQuest is a browser-based PHP trivia application for creating questions, assembling quiz rounds, playing quizzes, tracking scores, and viewing a top-ten leaderboard.
In Quiz Quest, players take on the role of a Trivia Master, responsible for creating,
managing, and conducting an engaging quiz game. The goal is to efficiently organize
quiz rounds, record player scores, and ensure fair and accurate gameplay.
The game aims to test knowledge, enhance memory, and make learning fun while
ensuring seamless quiz management.

The application is implemented in a single PHP file and uses SQLite for persistent quiz content and score data. The active quiz session and current question are stored in the PHP session.

## Features

- Create multiple-choice questions (MCQ) or open-answer questions.
- Assign each question a creator and difficulty:
  - Easy
  - Medium
  - Hard
- Store MCQ options as a comma-separated list.
- Create named quiz rounds with themes and selected questions.
- Configure a time-limit value for each round.
- Start a quiz with a player name and selected round.
- Track the current question and score during play.
- Compare submitted answers case-insensitively after trimming whitespace.
- Automatically save completed scores by player, round, points, and date.
- Maintain temporary in-progress score records in `temp_scores`.
- Search recorded scores by player, quiz date, and difficulty.
- Update or delete stored scores.
- Delete questions and rounds.
- Display a leaderboard containing the ten players with the highest total points.

## Important API Note

QuizQuest is not currently a conventional REST or JSON API. It is a server-rendered PHP application driven by:

- The `tab` GET parameter for page navigation.
- Named POST form controls for create, delete, play, search, and update actions.
- PHP sessions for the active quiz.

The application returns HTML pages and redirects or reloads the current page. It does not define JSON response schemas, API authentication, versioned routes, or REST resources.

This README documents the current form-action interface and includes production recommendations for adding an API later.

## Requirements

- PHP 7.1 or later; PHP 8.x is recommended.
- PHP sessions enabled.
- PDO SQLite enabled.
- A PHP-compatible web server.
- A writable application directory.
- The stylesheet referenced at `../styles/style.css`, or a replacement stylesheet at that path.

Verify SQLite support:

```bash
php -m | grep -i sqlite
```

The output should include `PDO_SQLITE` and normally `sqlite3`.

## Installation

1. Place `QuizQuest.php` in a PHP-accessible directory.
2. Make sure the PHP process can create and write files in that directory.
3. Ensure the stylesheet exists at the relative path expected by the file:

   ```text
   ../styles/style.css
   ```

4. Start a PHP server from the directory containing `QuizQuest.php`.
5. Open the application in a browser.

### Run with PHP's built-in server

```bash
php -S localhost:8000
```

Open:

```text
http://localhost:8000/QuizQuest.php
```

Do not open the file using a `file://` URL; PHP must execute it through a web server.

## Runtime Database

On first access, the application creates:

```text
quizquest.db
```

The database is stored in the same directory as `QuizQuest.php`.

The following tables are initialized automatically:

| Table | Purpose |
|---|---|
| `users` | Unique player names used by saved scores |
| `questions` | Question text, type, difficulty, options, and correct answers |
| `rounds` | Round name, theme, selected question IDs, and time-limit value |
| `scores` | Completed quiz results |
| `temp_scores` | In-progress score and question position for a player/round pair |

To reset all development data, stop the server and delete `quizquest.db`:

```bash
rm quizquest.db
```

This permanently deletes questions, rounds, users, scores, and temporary score records.

## Application Navigation

The dashboard provides four areas:

| Area | Purpose |
|---|---|
| **Create Quiz & Rounds Manually** | Add questions, create rounds, and delete questions or rounds |
| **Play Quiz** | Select a player name and round, then answer questions |
| **View Leaderboard** | View the top ten players by total points |
| **Manage Scores & Data** | Search, update, and delete completed scores |

Navigation is controlled by the URL parameter:

```text
?tab=dashboard
?tab=questions
?tab=play_quiz
?tab=leaderboard
?tab=scores
```

The default tab is `dashboard`.

## Creating Questions

Open **Create Quiz & Rounds Manually** and complete the question form:

- **Question:** The question text.
- **Creator's name:** The author or creator.
- **Type of Question:** `MCQ` or `Open`.
- **Difficulty:** `Easy`, `Medium`, or `Hard`.
- **Options:** For MCQ questions, enter options separated by commas.
- **Correct Answer:** The answer used for scoring.

Example MCQ values:

```text
Question: Which planet is known as the Red Planet?
Options: Earth, Mars, Jupiter, Venus
Correct Answer: Mars
```

For open questions, the answer is entered as text. During scoring, the submitted answer is trimmed and compared to the stored answer without case sensitivity.

## Creating Rounds

A round is a named collection of selected questions.

1. Enter a round name.
2. Enter a theme.
3. Enter the time-limit value in seconds.
4. Select one or more existing questions.
5. Select **Add Round**.

The selected question IDs are stored as JSON in the `rounds.question_ids` field.

### Time-limit note

The current application stores and displays the round time limit, but it does not enforce a countdown or reject late answers during gameplay. A timer must be implemented separately if timed play is required.

## Playing a Quiz

1. Open **Play Quiz**.
2. Enter a player name.
3. Select a round.
4. Select **Start Quiz**.
5. Answer each question.
6. Select **Submit Answer** after each response.
7. Complete the quiz or select **Finish Quiz** when the final question screen is reached.

MCQ questions display radio-button options. Open questions display a text field.

A correct response adds one point. An incorrect response adds zero points. The score is stored in the PHP session during play and updated in `temp_scores` after each answer.

When the quiz is completed, the application:

- Creates the player in `users` if the name is new.
- Inserts a row into `scores`.
- Deletes the matching `temp_scores` row.
- Displays a completion message.
- Clears the PHP session.

## Leaderboard

The leaderboard groups completed scores by player and sums their points:

- Ordered by total points descending.
- Limited to the top ten players.

The leaderboard is cumulative across all saved quiz attempts in the database.

## Managing Scores

Open **Manage Scores & Data** to search completed scores by:

- Player name, using a partial match.
- Exact quiz date.
- Difficulty.

The score table displays player, round, points, difficulty, and date.

Each result can be:

- Updated by changing the points value and selecting **Update**.
- Permanently removed with **Delete**.

### Difficulty display note

The current score query joins difficulty using the first question ID in a round's JSON question list. For rounds containing questions with different difficulties, the displayed difficulty represents the first selected question rather than the complete round.

## Form Actions and Routes

### Navigation routes

| Request | Purpose |
|---|---|
| `GET ?tab=dashboard` | Dashboard |
| `GET ?tab=questions` | Question and round management |
| `GET ?tab=play_quiz` | Quiz start or active quiz screen |
| `GET ?tab=leaderboard` | Top-ten leaderboard |
| `GET ?tab=scores` | Score search and management |

### POST actions

| Form control | Main parameters | Purpose |
|---|---|---|
| `create_question` | `text`, `creator`, `type`, `difficulty`, `options`, `correct` | Inserts a question |
| `create_round` | `name`, `theme`, `questions[]`, `time_limit` | Inserts a round |
| `delete_question` | `question_id` | Deletes a question |
| `delete_round` | `round_id` | Deletes a round |
| `start_quiz` | `player_name`, `round_id` | Initializes the session quiz |
| `answer` | `selected` | Scores the current answer and advances the session |
| `finish_quiz` | None | Completes and saves the current quiz |
| `search_scores` | `player`, `quiz_date`, `difficulty` | Filters completed scores |
| `update_score` | `score_id`, `points` | Changes a saved score |
| `delete_score` | `score_id` | Deletes a saved score |

Requests are submitted through ordinary HTML forms and return HTML. They are not JSON API calls.

## Database Schema

### `users`

```sql
user_id INTEGER PRIMARY KEY AUTOINCREMENT
name TEXT NOT NULL UNIQUE
```

### `questions`

```sql
question_id INTEGER PRIMARY KEY AUTOINCREMENT
text TEXT NOT NULL
creator TEXT NOT NULL
type TEXT CHECK(type IN ('MCQ','Open')) NOT NULL
difficulty TEXT CHECK(difficulty IN ('Easy','Medium','Hard')) NOT NULL
options TEXT
correct_answer TEXT NOT NULL
```

`options` contains a JSON array for MCQ questions and is `NULL` for open questions.

### `rounds`

```sql
round_id INTEGER PRIMARY KEY AUTOINCREMENT
name TEXT NOT NULL
theme TEXT
question_ids TEXT NOT NULL
time_limit INTEGER NOT NULL
```

`question_ids` contains a JSON array of question IDs.

### `scores`

```sql
score_id INTEGER PRIMARY KEY AUTOINCREMENT
user_id INTEGER NOT NULL
round_id INTEGER NOT NULL
points INTEGER NOT NULL
quiz_date DATE NOT NULL
```

### `temp_scores`

```sql
temp_id INTEGER PRIMARY KEY AUTOINCREMENT
user_name TEXT NOT NULL
round_id INTEGER NOT NULL
current_score INTEGER NOT NULL
current_question INTEGER NOT NULL
last_update DATETIME DEFAULT CURRENT_TIMESTAMP
UNIQUE(user_name, round_id)
```

## Session State

During a quiz, the application stores these values in `$_SESSION`:

| Session key | Meaning |
|---|---|
| `player_name` | Current player's name |
| `round_id` | Active round ID |
| `score` | Current score |
| `questions` | Questions loaded for the active round |
| `current` | Zero-based index of the current question |

The session is cleared after the final score is saved.

## Security and Production Considerations

The application uses prepared statements for most inserts, updates, and filtered score queries. Before public deployment, consider:

- CSRF tokens on every state-changing form.
- Authentication and authorization for question, round, and score administration.
- Server-side validation and strict integer casting for IDs and points.
- Escaping all database-derived values before HTML output; several current views render values directly.
- Replacing the interpolated round lookup with a prepared statement.
- Authorization and confirmation for delete operations.
- Validation that selected question IDs exist before creating a round.
- Handling references to deleted questions so rounds cannot silently contain missing questions.
- Enforcing a non-negative score and any intended maximum.
- Enforcing the configured time limit with server-side timing logic.
- Preventing players from changing `player_name`, `round_id`, or score state through forged requests.
- Secure error logging rather than exposing database errors.
- Backups and access restrictions for `quizquest.db`.

## Troubleshooting

### `could not find driver`

Enable PDO SQLite. On Debian or Ubuntu:

```bash
sudo apt install php-sqlite3
```

Restart the web server after enabling the extension.

### The database cannot be created

Make sure the application directory is writable by the PHP process.

### The page has no styling

The PHP file references:

```text
../styles/style.css
```

Create the expected file or update the stylesheet reference.

### A round has fewer questions than expected

A round stores question IDs rather than question copies. If a selected question is later deleted, it is no longer returned when the round starts. Recreate the round with valid questions.

### A quiz appears to restart or lose progress

The active quiz is stored in the PHP session. Confirm that cookies are enabled, PHP sessions are writable, and the player remains on the same host and port.

### The time limit does not stop the quiz

Time limits are currently stored as round metadata only. No countdown or server-side timeout is implemented in this version.

## Backup and Reset

Back up the database before maintenance:

```bash
cp quizquest.db quizquest-backup-$(date +%Y%m%d-%H%M%S).db
```

Restore a backup after stopping active writes:

```bash
mv quizquest.db quizquest-before-restore.db
cp quizquest-backup-YYYYMMDD-HHMMSS.db quizquest.db
```

To reset development data completely:

```bash
rm quizquest.db
```

## Project Structure

```text
QuizQuest.php             Application, database initialization, game flow, and HTML UI
quizquest.db              SQLite database created at runtime
../styles/style.css       Stylesheet expected by the application
README_QuizQuest.md       Project README and technical documentation
QUIZQUEST_USER_MANUAL.md  User manual and form-action reference
```

## License

No license is currently specified. Add a `LICENSE` file before distributing or deploying the application if a specific license is intended.

