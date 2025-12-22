<?php
session_start();

// SQLite Database Setup
$db_file = __DIR__ . '/quizquest.db';
$pdo = new PDO('sqlite:' . $db_file);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create Tables
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);
CREATE TABLE IF NOT EXISTS questions (
    question_id INTEGER PRIMARY KEY AUTOINCREMENT,
    text TEXT NOT NULL,
    creator TEXT NOT NULL,
    type TEXT CHECK(type IN ('MCQ','Open')) NOT NULL,
    difficulty TEXT CHECK(difficulty IN ('Easy','Medium','Hard')) NOT NULL,
    options TEXT,
    correct_answer TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS rounds (
    round_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    theme TEXT,
    question_ids TEXT NOT NULL,
    time_limit INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS scores (
    score_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    round_id INTEGER NOT NULL,
    points INTEGER NOT NULL,
    quiz_date DATE NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(user_id),
    FOREIGN KEY(round_id) REFERENCES rounds(round_id)
);
CREATE TABLE IF NOT EXISTS temp_scores (
    temp_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_name TEXT NOT NULL,
    round_id INTEGER NOT NULL,
    current_score INTEGER NOT NULL,
    current_question INTEGER NOT NULL,
    last_update DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_name, round_id)
);
");

//  Function to handle question creation
if(isset($_POST['create_question'])){
    $text = $_POST['text'];
    $creator = $_POST['creator'];
    $type = $_POST['type'];
    $difficulty = $_POST['difficulty'];
    $options = $type=='MCQ' ? json_encode(array_map('trim', explode(',', $_POST['options']))) : NULL;
    $correct = $_POST['correct'];
    $stmt = $pdo->prepare("INSERT INTO questions (text,creator,type,difficulty,options,correct_answer) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$text,$creator,$type,$difficulty,$options,$correct]);
}

// Function to handle round creation
if(isset($_POST['create_round'])){
    $name = $_POST['name'];
    $theme = $_POST['theme'];
    $question_ids = json_encode($_POST['questions'] ?? []);
    $time_limit = $_POST['time_limit'];
    $stmt = $pdo->prepare("INSERT INTO rounds (name,theme,question_ids,time_limit) VALUES (?,?,?,?)");
    $stmt->execute([$name,$theme,$question_ids,$time_limit]);
}

// Delete Questions
if(isset($_POST['delete_question'])) $pdo->prepare("DELETE FROM questions WHERE question_id=?")->execute([$_POST['question_id']]);

// Delete Rounds
if(isset($_POST['delete_round'])) $pdo->prepare("DELETE FROM rounds WHERE round_id=?")->execute([$_POST['round_id']]);

// Delete Scores
if(isset($_POST['delete_score'])) $pdo->prepare("DELETE FROM scores WHERE score_id=?")->execute([$_POST['score_id']]);

// Update Scores
if(isset($_POST['update_score'])) $pdo->prepare("UPDATE scores SET points=? WHERE score_id=?")->execute([$_POST['points'],$_POST['score_id']]);

//  This handles all the stuff related to quiz play

//  Start Quiz
if(isset($_POST['start_quiz'])){
    $_SESSION['player_name'] = $_POST['player_name'];
    $_SESSION['round_id'] = $_POST['round_id'];
    $_SESSION['score'] = 0;

    $round_id = $_POST['round_id'];
    $round = $pdo->query("SELECT * FROM rounds WHERE round_id=$round_id")->fetch(PDO::FETCH_ASSOC);
    $question_ids = json_decode($round['question_ids']);
    if($question_ids){
        $placeholders = implode(',', array_fill(0,count($question_ids),'?'));
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE question_id IN ($placeholders)");
        $stmt->execute($question_ids);
        $_SESSION['questions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $_SESSION['current'] = 0;
    }
}

// Handle Answer Submission & Live Score Tracking
if(isset($_POST['answer'])){
    $q_index = $_SESSION['current'];
    $question = $_SESSION['questions'][$q_index];
    $selected = trim($_POST['selected']);
    if(strtolower($selected) == strtolower($question['correct_answer'])) $_SESSION['score']++;

    // Save temp score in DB
    $stmt = $pdo->prepare("
        INSERT INTO temp_scores (user_name, round_id, current_score, current_question)
        VALUES (?,?,?,?)
        ON CONFLICT(user_name, round_id) DO UPDATE SET
            current_score=excluded.current_score,
            current_question=excluded.current_question,
            last_update=CURRENT_TIMESTAMP
    ");
    $stmt->execute([$_SESSION['player_name'], $_SESSION['round_id'], $_SESSION['score'], $q_index+1]);

    $_SESSION['current']++;
}

// Function to handle quiz completion
if (
    isset($_POST['finish_quiz']) ||
    (isset($_SESSION['current']) && isset($_SESSION['questions']) && is_array($_SESSION['questions']) && $_SESSION['current'] >= count($_SESSION['questions']))
){
    $user_name = $_SESSION['player_name'];
    $round_id = $_SESSION['round_id'];
    $points = $_SESSION['score'];
    $quiz_date = date('Y-m-d');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE name=?");
    $stmt->execute([$user_name]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$user){
        $pdo->prepare("INSERT INTO users (name) VALUES (?)")->execute([$user_name]);
        $user_id = $pdo->lastInsertId();
    } else $user_id = $user['user_id'];

    $stmt = $pdo->prepare("INSERT INTO scores (user_id,round_id,points,quiz_date) VALUES (?,?,?,?)");
    $stmt->execute([$user_id,$round_id,$points,$quiz_date]);

    // Remove temp score
    $pdo->prepare("DELETE FROM temp_scores WHERE user_name=? AND round_id=?")->execute([$user_name,$round_id]);

    $total_questions = (isset($_SESSION['questions']) && is_array($_SESSION['questions'])) ? count($_SESSION['questions']) : 0;
    $quiz_message = "Quiz Complete! Score: $points / $total_questions";
    session_unset();
}

// Fetch data  both rounds and questions
$rounds = $pdo->query("SELECT * FROM rounds")->fetchAll(PDO::FETCH_ASSOC);
$questions = $pdo->query("SELECT * FROM questions")->fetchAll(PDO::FETCH_ASSOC);

// Apply Search Filters for Scores
$where = []; $params = [];
if(isset($_POST['search_scores'])){
    if(!empty($_POST['player'])){ $where[] = "u.name LIKE ?"; $params[] = "%".$_POST['player']."%"; }
    if(!empty($_POST['quiz_date'])){ $where[] = "s.quiz_date=?"; $params[] = $_POST['quiz_date']; }
    if(!empty($_POST['difficulty'])){ $where[] = "q.difficulty=?"; $params[] = $_POST['difficulty']; }
}
$whereSQL = $where ? "WHERE ".implode(" AND ",$where) : "";
$query = "
SELECT s.score_id, u.name as player, r.name as round_name, s.points, s.quiz_date, q.difficulty
FROM scores s
JOIN users u ON s.user_id=u.user_id
JOIN rounds r ON s.round_id=r.round_id
JOIN questions q ON json_extract(r.question_ids,'$[0]') = q.question_id
$whereSQL
";
$stmt = $pdo->prepare($query); $stmt->execute($params); $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Leaderboard Data
$leaders = $pdo->query(" 
SELECT u.name, SUM(s.points) AS total_points
FROM scores s
JOIN users u ON s.user_id=u.user_id
GROUP BY u.user_id
ORDER BY total_points DESC
LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Tab Navigation
$tab = $_GET['tab'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html>
<head>
<title>Quiz Quest</title>
<link rel="stylesheet" type="text/css" href="../styles/style.css">
</head>

<body>
<div class="container">
<!-- Dashboard -->
<?php if($tab=='dashboard'): ?>
<div class="dashboard">
<h1>Quiz Quest</h1>
<p>The Ultimate Trivia Challenge — Test your knowledge, create <br>quizzes, and compete for the leaderboard!</p>
<br>
<div class="menu">
<a href="?tab=questions" class="card">✏️🖨️<br> Create Quiz & Rounds Manually</a>
<a href="?tab=play_quiz" class="card">▶️ <br> Play Quiz</a>
<a href="?tab=leaderboard" class="card">🏆 <br> View Leaderboard</a>
<a href="?tab=scores" class="card">🪛🔐 <br> Manage Scores & Data</a>
</div>
</div>
<?php endif; ?>
<?php if(isset($quiz_message)) echo "<h2>$quiz_message</h2>"; ?>

<!-- Questions & Rounds -->
<?php if($tab=='questions'): ?>
<h2>Create Questions</h2>
<form method="post">
<label>Question:<input type="text" name="text" required></label>
<label>Creator's name:<input type="text" name="creator" required></label>
<label>Type of Question:<select name="type">
    <option>MCQ</option>
    <option>Open</option>
</select></label>
<label>Difficulty:<select name="difficulty" >
    <option>Easy</option>
    <option>Medium</option>
    <option>Hard</option>
</select></label>
<label>Options (comma for MCQ):<input type="text" name="options" placeholder="Option A , Option B , Option C"></label>
<label>Correct Answer:<input type="text" name="correct" required></label>
<button type="submit" class="create_question" name="create_question">Add Question</button>
</form>

<div class="table-container">
<table>
<tr>
    <th>Question</th>
    <th>Creator</th>
    <th>Type</th>
    <th>Difficulty</th>
    <th>Correct</th>
    <th>Delete</th>
</tr>
<?php foreach($questions as $q){ ?>
<tr>
<td><?= $q['text'] ?></td>
<td><?= $q['creator'] ?></td>
<td><?= $q['type'] ?></td>
<td><?= $q['difficulty'] ?></td>
<td><?= $q['correct_answer'] ?></td>
<td>
<form method="post">
    <input type="hidden" name="question_id" value="<?= $q['question_id'] ?>">
    <button type="submit" class="delete-btn" name="delete_question">Delete</button>
</form>
</td>
</tr>
<?php } ?>
</table>
</div>

<hr>
<h2>Create & Manage Rounds</h2>
<form method="post">
<label>Round Name:<input type="text" name="name" required></label>
<label>Theme:<input type="text" name="theme" required></label>
<label>Time Limit (seconds):<input type="number" name="time_limit" value="60"></label>
<br>    Select Questions:<br>
<?php foreach($questions as $q){ ?>
<label class="checkbox-glass"><input type="checkbox" name="questions[]" value="<?= $q['question_id'] ?>"><?= $q['text'] ?></label>
<?php } ?>
<button class="goback-btn" type="submit" name="create_round">Add Round</button>
</form>

<div class="table-container">
<table>
<tr>
    <th>Round</th>
    <th>Theme</th>
    <th>Time Limit</th>
    <th>Delete</th>
</tr>
<?php foreach($rounds as $r){ ?>
<tr>
<td><?= $r['name'] ?></td>
<td><?= $r['theme'] ?></td>
<td><?= $r['time_limit'] ?>s</td>
<td>
<form method="post">
    <input type="hidden" name="round_id" value="<?= $r['round_id'] ?>">
    <button type="submit" class="delete-btn" name="delete_round">Delete</button>
</form>
</td>
</tr>
<?php } ?>
</table>
</div>

<div class="goback">
    <form method="get">
        <button class="goback-btn" type="submit" name="tab" value="dashboard">⬅️ Go Back</button>
    </form>
</div>
<?php endif; ?>

<!-- Play Quiz -->
<?php if($tab=='play_quiz'): ?>
    <h2>Play Quiz</h2> <br>
<?php if(!isset($_SESSION['questions']) || empty($_SESSION['questions'])): ?>
<form method="post">
<label>Player Name:<input type="text" name="player_name" required></label>
<label>Select Round:<select name="round_id" required>
<?php foreach($rounds as $r) echo "<option value='{$r['round_id']}'>{$r['name']} ({$r['theme']})</option>"; ?>
</select>
</label>
<button type="submit" class="start-btn" name="start_quiz">Start Quiz</button>
</form>
<?php else: ?>
<?php $q_index = $_SESSION['current']; if($q_index < count($_SESSION['questions'])){ $q = $_SESSION['questions'][$q_index]; ?>
<h3>Question <?= $q_index+1 ?> / <?= count($_SESSION['questions']) ?></h3>
<p><?= $q['text'] ?></p>
<h4>Current Score: <?= $_SESSION['score'] ?> / <?= $q_index ?></h4>
<form method="post">
<?php if($q['type']=='MCQ'){
$options = is_string($q['options']) ? json_decode($q['options'], true) : [];
if(!is_array($options)) $options = [];
foreach($options as $opt){ ?>
<label class="checkbox-glass">
<input type="radio" name="selected" value="<?= htmlspecialchars($opt) ?>" required><?= htmlspecialchars($opt) ?>
</label>
<?php } } else { ?>
<input type="text" name="selected" required style="margin:10px 0; padding:12px; border-radius:10px; border:2px solid rgba(255,255,255,0.3);">
<?php } ?>
<button type="submit" class="start-btn" name="answer">Submit Answer</button>
</form>
<?php } else { ?>
<form method="post"><button type="submit" name="finish_quiz">Finish Quiz</button></form>
<?php } endif; ?>
<div class="goback"> <form method="get"> <button class="goback-btn" type="submit" name="tab" value="dashboard">⬅️ Go Back</button> 
</form> 
</div>
<?php endif; ?>

<!-- Leaderboard -->
<?php if($tab=='leaderboard'): ?>
<h2>Leaderboard</h2>
<table>
<tr><th>Rank</th><th>Player</th><th>Total Points</th></tr>
<?php $rank=1; foreach($leaders as $l){ ?>
<tr><td><?= $rank++ ?></td><td><?= $l['name'] ?></td><td><?= $l['total_points'] ?></td></tr>
<?php } ?>
</table>
<div class="goback">
    <form method="get">
        <button class="goback-btn" type="submit" name="tab" value="dashboard">⬅️ Go Back</button>
    </form>
</div>
<?php endif; ?>

<!-- Scores -->
<?php if($tab=='scores'): ?>
<h2>Manage Scores & Data</h2>
<form method="post">
<label>Search by Player:<input type="text" name="player"></label>
<label>Quiz Date:<input type="date" name="quiz_date"></label>
<label>Difficulty:<select name="difficulty">
    <option value="">--Any--</option>
    <option>Easy</option>
    <option>Medium</option>
    <option>Hard</option>
</select></label>
<button type="submit" class="start-btn" name="search_scores">Search</button>
</form>

<div class="table-container">
<table>
<tr>
    <th>Player</th>
    <th>Round</th>
    <th>Points</th>
    <th>Difficulty</th>
    <th>Date</th>
    <th>Update</th>
    <th>Delete</th>
</tr>
<?php foreach($scores as $s){ ?>
<tr>
<td><?= $s['player'] ?></td>
<td><?= $s['round_name'] ?></td>
<td><?= $s['points'] ?></td>
<td><?= $s['difficulty'] ?></td>
<td><?= $s['quiz_date'] ?></td>
<td>
<form method="post" style="display:inline">
    <input type="hidden" name="score_id" value="<?= $s['score_id'] ?>">
    <input type="number" name="points" value="<?= $s['points'] ?>" style="width:60px">
    <button type="submit" class="start-btn" name="update_score">Update</button>
</form>
</td>
<td>
<form method="post" style="display:inline">
    <input type="hidden" name="score_id" value="<?= $s['score_id'] ?>">
    <button type="submit" class="delete-btn" name="delete_score">Delete</button>
</form>
</td>
</tr>
<?php } ?>
</table>
</div>

<div class="goback">
    <form method="get">
        <button class="goback-btn" type="submit" name="tab" value="dashboard">⬅️ Go Back</button>
    </form>
</div>
<?php endif; ?>

</div>
</body>
</html>
